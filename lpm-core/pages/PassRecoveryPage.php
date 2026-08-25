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
            foreach ($_POST as $key => $value) {
                $_POST[$key] = is_string($value) ? trim($value) : $value;
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
        // Сообщение здесь то же, что и при отсутствии пользователя, чтобы
        // по ответу нельзя было отличить одно от другого.
        if (!Validation::checkEmail($email)) {
            $this->_engine->addError('Пользователь с таким email не зарегистрирован');
            return;
        }

        try {
            $user = User::loadByEmail($email);
        } catch (Exception $e) {
            $this->_engine->addError('Ошибка чтения из базы');
            return;
        }

        if (!$user) {
            $this->_engine->addError('Пользователь с таким email не зарегистрирован');
            return;
        }

        if ($this->sendRecoveryEmail($user->userId, $user->firstName, $email)) {
            $this->_show = 'successEmail';
        }
    }

    private function sendRecoveryEmail($userId, $firstName, $email)
    {
        $expFormat = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+1, date("Y"));
        $expDate = date("Y-m-d H:i:s", $expFormat);
        $key = SecureRandomHelper::hex(16);

        try {
            // Проверим, нет ли актуального письма
            if (PassRecoveryKey::loadActualKey($userId) !== null) {
                $this->_engine->addError('Письмо уже было отправлено на данный email');
                return false;
            }

            PassRecoveryKey::save($userId, $key, $expDate);
        } catch (Exception $e) {
            $this->_engine->addError('Ошибка записи в базу');
            return false;
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

        if (EmailNotifier::getInstance()->send($email, $firstName, $subject, $message)) {
            return true;
        }

        $this->_engine->addError('Не удалось отправить письмо, попробуйте позже или свяжитесь с администратором.');
        // TODO: удалить из базы? или дать возможность запросить отправку еще раз
        return false;
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

        if (!Validation::checkPass($newPass, PASSWORD_MAX_LENGTH, PASSWORD_MIN_LENGTH, true)) {
            $this->_engine->addError(sprintf(
                'Пароль должен быть от %d до %d символов - используйте латинские буквы, цифры или знаки',
                PASSWORD_MIN_LENGTH,
                PASSWORD_MAX_LENGTH
            ));
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
