<?php

class PassRecoveryPage extends LPMPage
{
    private $_show ;
    /**
     * Пользователь, для которого восстанавливается пароль.
     * Заполняется только после проверки ссылки восстановления.
     * @var int
     */
    private $_userId = 0;
    /**
     * Ключ восстановления из ссылки. Заполняется только после того,
     * как совпал с актуальным ключом пользователя, поэтому всегда
     * содержит значение из базы, а не пришедшее из запроса.
     * @var string
     */
    private $_recoveryKey = '';

    public function __construct()
    {
        parent::__construct('pass-recovery', 'Восстановление пароля', false, true);
        $this->_pattern = 'pass-recovery';
        array_push($this->_js, 'project');
    }
        
    public function init()
    {
        if (!parent::init()) {
            return false;
        }
           
        if (!empty($_POST) && !CsrfToken::check()) {
            $this->_engine->addError(
                'Страница устарела. Запросите письмо заново или откройте ссылку из письма ещё раз'
            );
        } elseif (!empty($_POST)) {
            $rawPost = $_POST;
            foreach ($_POST as $key => $value) {
                $_POST[$key] = is_string($value) ? trim($value) : $value;
            }

            // Пароль берём как есть: пробелы по краям - его часть,
            // а сверяется хэш ровно от того, что ввёл пользователь.
            foreach (['newPass', 'rePass'] as $passField) {
                if (isset($rawPost[$passField]) && is_string($rawPost[$passField])) {
                    $_POST[$passField] = $rawPost[$passField];
                }
            }
            if (isset($_POST['remail'])) {
                $this->requestRecoveryEmail((string)$_POST['remail']);
            } elseif (isset($_POST['newPass']) && isset($_POST['rePass']) && isset($_POST['userId']) && isset($_POST['key'])) {
                if (!is_string($_POST['newPass']) || !is_string($_POST['rePass'])
                        || !is_scalar($_POST['userId']) || !is_string($_POST['key'])) {
                    $this->_engine->addError('Некорректные данные формы');
                } elseif ($_POST['newPass'] != $_POST['rePass']) {
                    $this->_engine->addError('Пароли не совпадают');
                    $this->_show = 'changePassForm';
                } else {
                    $this->updatePass($_POST['newPass'], (int)$_POST['userId'], $_POST['key']);
                }
            }
        } elseif ($this->getPUID() == 'reclink') {
            $key = (string)$this->getAddParam(0);
            $userId = $this->_engine->getParams()->getQueryArg('userId');

            $userId = (int)base64_decode(urldecode((string)$userId));
            if (!empty($key) && !empty($userId)) {
                if ($this->checkUrlKey($key, $userId)) {
                    $this->_userId = $userId;
                    $this->_recoveryKey = $key;
                    $this->_show = 'changePassForm';
                }
            } else {
                $this->_engine->addError('Некорректная ссылка');
            }
        }

        // Ветка, не выбравшая что показывать (протухший токен, неверная ссылка,
        // некорректные данные), всё равно должна оставить пользователю форму:
        // шаблон рисует тело по getShow(), иначе он увидит пустую карточку
        // и не сможет ничего сделать, кроме как уйти со страницы.
        if (empty($this->_show)) {
            $this->_show = 'emailForm';
        }

        return $this;
    }
    
    /**
     * Проверяет, что ключ восстановления совпадает с актуальным ключом пользователя.
     * @param  string $key    Ключ из ссылки восстановления.
     * @param  int    $userId Идентификатор пользователя.
     * @return bool
     */
    private function checkUrlKey($key, $userId)
    {
        try {
            $savedKey = PassRecoveryKey::loadActualKey($userId);
        } catch (Exception $e) {
            $this->_engine->addError('Ошибка чтения из базы');
            return false;
        }

        // hash_equals - чтобы по времени ответа нельзя было подбирать ключ посимвольно
        if ($savedKey === null || !hash_equals((string)$savedKey, (string)$key)) {
            $this->_engine->addError('Запись не найдена');
            return false;
        }

        return true;
    }
    
    /**
     * Обрабатывает запрос письма для восстановления пароля.
     * @param string $email Адрес, указанный в форме.
     */
    private function requestRecoveryEmail($email)
    {
        // Форма публичная, поэтому проверяем формат до обращения к базе.
        // Про формат сказать можно: он не зависит от того, зарегистрирован
        // адрес или нет.
        if (!Validation::checkEmail($email)) {
            $this->_engine->addError('Введён некорректный email');
            return;
        }

        $retryAfter = AuthThrottle::checkPassRecovery($email);
        if ($retryAfter > 0) {
            $this->_engine->addError(sprintf(
                'Слишком много запросов на восстановление. Попробуйте через %s',
                AuthThrottle::formatRetryAfter($retryAfter)
            ));
            return;
        }

        // Запрос считаем до того, как узнаем, есть ли такой пользователь:
        // счётчик не должен зависеть от того, зарегистрирован адрес или нет.
        AuthThrottle::registerPassRecoveryRequest($email);

        try {
            $user = User::loadByEmail($email);
        } catch (Exception $e) {
            $this->_engine->addError('Ошибка чтения из базы');
            return;
        }

        if ($user) {
            $this->sendRecoveryEmail($user->userId, $user->firstName, $email);
        }

        // Ответ один на все исходы - письмо ушло, пользователя нет, письмо ему
        // уже отправляли, отправка не удалась: по разным ответам перебирается,
        // кто зарегистрирован. Причины неудачи видны в логе.
        //
        // Время ответа при этом остаётся разным: зарегистрированному адресу
        // письмо отправляется прямо в запросе, и это сотни миллисекунд.
        // Выравнивать его здесь нечем - очереди писем в приложении нет,
        // а под mod_php ответ не отдать до конца работы скрипта. Канал
        // закрывается не тут, а ограничением числа запросов с одного адреса
        // клиента. Оно работает только там, где этот адрес известен
        // достоверно, то есть заданы доверенные прокси ({@see ClientIp});
        // пока их нет, перебор адресов по времени ответа остаётся возможен.
        $this->_show = 'successEmail';
    }

    /**
     * Отправляет письмо со ссылкой восстановления, если актуальной ссылки
     * у пользователя ещё нет.
     *
     * Наружу неудача не выводится - о ней пишется в лог, см. вызывающий метод.
     * @param int    $userId    Идентификатор пользователя.
     * @param string $firstName Имя пользователя для обращения в письме.
     * @param string $email     Адрес, на который уходит письмо.
     */
    private function sendRecoveryEmail($userId, $firstName, $email)
    {
        $expFormat = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+1, date("Y"));
        $expDate = date("Y-m-d H:i:s", $expFormat);
        $key = SecureRandomHelper::hex(16);

        try {
            // Проверим, нет ли актуального письма
            if (PassRecoveryKey::loadActualKey($userId) !== null) {
                return;
            }

            PassRecoveryKey::save($userId, $key, $expDate);
        } catch (Exception $e) {
            LPMLog::exception($e, LPMLog::CH_APP, ['userId' => $userId]);
            return;
        }

        $href = "pass-recovery/reclink/" . $key . "/?userId=" . urlencode(base64_encode($userId));
        $recoveryLink ='<a href="'. SITE_URL . $href .'"> ' . SITE_URL .  $href . '</a>';
        $lines = [
            "Здравствуйте, $firstName.",
            "Для восстановления пароля перейдите по ссылке:",
            "$recoveryLink",
            "Ссылка будет действительна в течении суток.",
        ];
        $subject = "Восстановление пароля";
        $message = implode("<br>", $lines);

        if (!EmailNotifier::getInstance()->send($email, $firstName, $subject, $message)) {
            LPMLog::error('Не удалось отправить письмо восстановления пароля', LPMLog::CH_EMAIL, [
                'userId' => $userId,
            ]);

            // Ключ живёт сутки, и пока он есть, письмо больше не отправляется.
            // Неотправленный ключ поэтому надо убрать: иначе пользователь,
            // которому письмо не дошло, весь день запрашивал бы его заново
            // и получал бы в ответ ту же страницу, а письма бы не было.
            try {
                PassRecoveryKey::removeByKey($key);
            } catch (Exception $e) {
                LPMLog::exception($e, LPMLog::CH_APP, ['userId' => $userId]);
            }
        }
    }
    
    /**
     * Задаёт пользователю новый пароль, если передан актуальный ключ восстановления.
     * @param string $newPass Новый пароль.
     * @param int    $userId  Идентификатор пользователя.
     * @param string $key     Ключ из ссылки восстановления.
     */
    private function updatePass($newPass, $userId, $key)
    {
        $userId = (int)$userId;

        // Ключ проверяем первым: пока ссылка не подтверждена, ни возвращать
        // пользователя в форму, ни запоминать пришедшие значения нельзя.
        if (!$this->checkUrlKey($key, $userId)) {
            return;
        }

        $this->_userId = $userId;
        $this->_recoveryKey = (string)$key;

        $passError = User::validatePassword($newPass);
        if ($passError !== null) {
            $this->_engine->addError($passError);
            $this->_show = 'changePassForm';
            return;
        }

        $salt = User::blowfishSalt();

        try {
            User::updatePassword($userId, User::passwordHash($newPass, $salt));

            // Пароль восстанавливают в том числе когда доступ увели,
            // поэтому ранее выданные куки должны перестать работать
            LPMAuth::removeSessions($userId);

            PassRecoveryKey::removeByKey($key);
        } catch (Exception $e) {
            $this->_engine->addError('Ошибка записи в БД');
            return;
        }

        $this->_show = 'recoverySuccess';
    }
    
    /**
     * Пользователь, для которого восстанавливается пароль.
     * @return int 0, если ссылка восстановления не проверена.
     */
    public function getUserId()
    {
        return $this->_userId;
    }

    /**
     * Проверенный ключ восстановления - шестнадцатеричная строка.
     * @return string Пустая строка, если ссылка восстановления не проверена.
     */
    public function getKey()
    {
        return $this->_recoveryKey;
    }
    
    public function getShow()
    {
        return $this->_show;
    }
}
