<?php

/**
 * Ограничение числа попыток входа и запросов восстановления пароля.
 *
 * Считаем по трём ключам сразу, каждый со своим пределом за окно:
 *  - пара «адрес + учётная запись» — отказ после {@see LOGIN_IP_ACCOUNT_LIMIT}
 *    неудач;
 *  - один адрес по всем учётным записям — отказ после {@see LOGIN_IP_LIMIT}
 *    неудач; это правило ловит перебор учётных записей с одного узла;
 *  - одна учётная запись по всем адресам — отказа нет никогда, только
 *    задержка ответа.
 *
 * Последнее — не смягчение, а условие: отказ по одной лишь учётной записи
 * и есть способ заблокировать чужой вход, засыпав его неверными паролями.
 * Поэтому наглухо закрываются только ключи, которые целиком в руках
 * атакующего (его адрес и его пара), а общий по учётной записи счётчик
 * замедляет подбор, не отбирая вход у владельца.
 *
 * Отсюда общее правило: отказать можно только по ключу, в который входит
 * достоверно известный адрес клиента ({@see ClientIp::getTrusted()}). Когда
 * адрес неизвестен — приложение за прокси, не объявленным доверенным, — он
 * у всех запросов один, и ключ «адрес и учётная запись» становится ключом
 * по одной учётной записи. Отказ по нему закрыл бы вход владельцу от чужих
 * пяти неверных паролей, поэтому в этом случае предел по паре не отказывает,
 * а только задерживает ответ, как и предел по учётной записи.
 *
 * Проверка и запись попытки — две операции, поэтому пачка одновременных
 * запросов успевает пройти проверку до того, как хоть одна попытка записана:
 * первый всплеск проходит целиком, а дальше предел уже действует. Закрыть
 * это можно только блокировкой строки на каждую попытку входа, что дороже
 * самой защиты.
 *
 * Сбой обращения к базе попытку разрешает: счётчики — защита, а не условие
 * входа, и неприменённая миграция или недоступная таблица не должны
 * оставлять приложение без входа вовсе. Сбой уходит в лог.
 *
 * Счётчик — не состояние блокировки, а число записей за окно: отказ снимается
 * сам, когда попытки выходят за окно, и отдельного срока разблокировки хранить
 * не нужно. Пока отказ действует, новые попытки не записываются, поэтому
 * и продлить его перебором нельзя.
 *
 * Ключ счётчика по учётной записи — введённый адрес, а не найденный
 * пользователь: существование учётной записи здесь не проверяется вовсе,
 * иначе ограничение само стало бы способом узнать, кто зарегистрирован.
 */
class AuthThrottle extends LPMBaseObject
{
    /** Вход */
    const ACTION_LOGIN = 1;
    /** Запрос письма для восстановления пароля */
    const ACTION_PASS_RECOVERY = 2;

    /** Счёт по адресу клиента */
    const SCOPE_IP = 1;
    /** Счёт по паре «адрес клиента + учётная запись» */
    const SCOPE_IP_ACCOUNT = 2;
    /** Счёт по учётной записи */
    const SCOPE_ACCOUNT = 3;

    /** Окно, за которое считаются неудачные попытки входа, в секундах */
    const LOGIN_WINDOW = 15 * 60;
    /** Неудач с одного адреса по одной учётной записи до отказа */
    const LOGIN_IP_ACCOUNT_LIMIT = 5;
    /** Неудач с одного адреса по всем учётным записям до отказа */
    const LOGIN_IP_LIMIT = 30;
    /** Неудач по одной учётной записи, после которых ответ задерживается */
    const LOGIN_ACCOUNT_SOFT_LIMIT = 10;
    /** На сколько секунд задерживается ответ после мягкого предела */
    const LOGIN_ACCOUNT_DELAY = 1;

    /** Окно, за которое считаются запросы восстановления, в секундах */
    const RECOVERY_WINDOW = 60 * 60;
    /** Запросов восстановления с одного адреса за окно */
    const RECOVERY_IP_LIMIT = 10;
    /** Запросов восстановления на один адрес почты за окно */
    const RECOVERY_ACCOUNT_LIMIT = 3;

    /**
     * Сколько хранить записи о попытках, в секундах. Дольше самого длинного
     * окна: записи нужны только пока попадают в окно.
     */
    const KEEP_ATTEMPTS = 24 * 60 * 60;

    /**
     * Одна из скольких записей запускает удаление протухших. Отдельного
     * планировщика в приложении нет, поэтому уборка идёт попутно с записью.
     */
    const CLEANUP_RATE = 20;

    /**
     * Проверяет, можно ли сейчас пробовать войти под этим адресом почты.
     *
     * Метод не только отвечает, но и выдерживает паузу, когда по учётной
     * записи уже много неудач: это и есть мягкое ограничение, которое
     * замедляет подбор, не отказывая владельцу во входе.
     *
     * @param  string $email Адрес, введённый в форме входа.
     * @return int Сколько секунд осталось до конца отказа; 0 — попытку
     *             можно проверять.
     */
    public static function checkLogin($email)
    {
        $pairLimit = [self::SCOPE_IP_ACCOUNT, self::LOGIN_IP_ACCOUNT_LIMIT];
        $hardLimits = [[self::SCOPE_IP, self::LOGIN_IP_LIMIT]];
        $softLimits = [[self::SCOPE_ACCOUNT, self::LOGIN_ACCOUNT_SOFT_LIMIT]];

        if (ClientIp::getTrusted() === '') {
            $softLimits[] = $pairLimit;
        } else {
            $hardLimits[] = $pairLimit;
        }

        $retryAfter = self::checkLimits(self::ACTION_LOGIN, $email, $hardLimits, self::LOGIN_WINDOW);
        if ($retryAfter > 0) {
            return $retryAfter;
        }

        if (self::isSoftLimitReached(self::ACTION_LOGIN, $email, $softLimits, self::LOGIN_WINDOW)) {
            sleep(self::LOGIN_ACCOUNT_DELAY);
        }

        return 0;
    }

    /**
     * Записывает неудачную попытку входа.
     * @param string $email Адрес, введённый в форме входа.
     */
    public static function registerLoginFailure($email)
    {
        self::addAttempts(self::ACTION_LOGIN, $email);
    }

    /**
     * Снимает накопленные неудачи после удачного входа.
     *
     * Счётчик по всем учётным записям с этого адреса не трогаем: иначе
     * перебор сбрасывался бы себе предел входом в собственную учётную запись.
     *
     * @param string $email Адрес, под которым вошли.
     */
    public static function registerLoginSuccess($email)
    {
        self::removeAttempts(self::ACTION_LOGIN, self::SCOPE_IP_ACCOUNT, self::pairKey($email));
        self::removeAttempts(self::ACTION_LOGIN, self::SCOPE_ACCOUNT, self::accountKey($email));
    }

    /**
     * Проверяет, можно ли сейчас запрашивать письмо для восстановления пароля.
     * @param  string $email Адрес, введённый в форме восстановления.
     * @return int Сколько секунд осталось до конца отказа; 0 — запрос можно
     *             обрабатывать.
     */
    public static function checkPassRecovery($email)
    {
        // Предел по адресу почты здесь отказывает, а не задерживает, хотя
        // ключ и в руках у кого угодно: он защищает от заваливания чужого
        // ящика письмами, а отнимает всего лишь возможность запросить письмо
        // в ближайший час. Приложение и так не шлёт второе письмо, пока
        // действует ссылка из первого, то есть сутки.
        return self::checkLimits(self::ACTION_PASS_RECOVERY, $email, [
            [self::SCOPE_ACCOUNT, self::RECOVERY_ACCOUNT_LIMIT],
            [self::SCOPE_IP, self::RECOVERY_IP_LIMIT],
        ], self::RECOVERY_WINDOW);
    }

    /**
     * Записывает запрос письма для восстановления пароля.
     *
     * Считаются все запросы, а не только приведшие к письму: счётчик не должен
     * зависеть от того, нашёлся ли пользователь.
     *
     * @param string $email Адрес, введённый в форме восстановления.
     */
    public static function registerPassRecoveryRequest($email)
    {
        self::addAttempts(self::ACTION_PASS_RECOVERY, $email);
    }

    /**
     * Срок ожидания словами, для сообщения пользователю.
     * @param  int $seconds Результат checkLogin()/checkPassRecovery().
     * @return string Например, «15 минут».
     */
    public static function formatRetryAfter($seconds)
    {
        $minutes = max(1, (int)ceil($seconds / 60));

        return $minutes . ' ' . DeclensionHelper::minutes($minutes);
    }

    /**
     * Проверяет пределы, которые доступ не закрывают, а только задерживают
     * ответ.
     * @param  int    $action Что считаем, ACTION_*.
     * @param  string $email  Адрес из формы.
     * @param  array  $limits Пары [область счёта, предел].
     * @param  int    $window Окно счёта в секундах.
     * @return bool true, если хотя бы один предел выбран.
     */
    private static function isSoftLimitReached($action, $email, array $limits, $window)
    {
        foreach ($limits as list($scope, $limit)) {
            $key = self::scopeKey($scope, $email);
            if ($key === '') {
                continue;
            }

            if (self::countAttempts($action, $scope, $key, $window) >= $limit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет пределы, при которых доступ закрывается совсем.
     * @param  int    $action Что считаем, ACTION_*.
     * @param  string $email  Адрес из формы.
     * @param  array  $limits Пары [область счёта, предел].
     * @param  int    $window Окно счёта в секундах.
     * @return int Сколько секунд ждать; 0 — ни один предел не превышен.
     */
    private static function checkLimits($action, $email, array $limits, $window)
    {
        $retryAfter = 0;
        $exceeded = 0;

        foreach ($limits as list($scope, $limit)) {
            $key = self::scopeKey($scope, $email);
            if ($key === '') {
                continue;
            }

            $oldest = self::getOldestAttempt($action, $scope, $key, $window, $limit);
            if ($oldest === 0) {
                continue;
            }

            // Отказ снимается, когда самая старая из учтённых попыток выйдет
            // за окно: тогда их станет меньше предела.
            $wait = $oldest + $window - time();
            if ($wait > $retryAfter) {
                $retryAfter = $wait;
                $exceeded = $scope;
            }
        }

        if ($retryAfter > 0) {
            LPMLog::warning('Превышен предел попыток авторизации', LPMLog::CH_APP, [
                'action' => $action,
                'scope' => $exceeded,
                'retryAfter' => $retryAfter,
            ]);
        }

        return max(0, $retryAfter);
    }

    /**
     * Записывает попытку по всем областям счёта сразу.
     * @param int    $action Что считаем, ACTION_*.
     * @param string $email  Адрес из формы.
     */
    private static function addAttempts($action, $email)
    {
        $scopes = [self::SCOPE_IP, self::SCOPE_IP_ACCOUNT, self::SCOPE_ACCOUNT];
        $date = time();

        foreach ($scopes as $scope) {
            $key = self::scopeKey($scope, $email);
            if ($key === '') {
                continue;
            }

            try {
                self::buildAndSaveToDbV2([
                    'INSERT' => [
                        'action'   => (int)$action,
                        'scope'    => (int)$scope,
                        'scopeKey' => $key,
                        'date'     => $date,
                    ],
                    'INTO' => LPMTables::AUTH_ATTEMPTS,
                ]);
            } catch (\Throwable $e) {
                self::logStorageFailure($e);
                return;
            }
        }

        self::cleanup();
    }

    /**
     * @param  int    $action Что считаем, ACTION_*.
     * @param  int    $scope  Область счёта, SCOPE_*.
     * @param  string $key    Ключ счётчика.
     * @param  int    $window Окно счёта в секундах.
     * @return int Число попыток за окно.
     */
    private static function countAttempts($action, $scope, $key, $window)
    {
        $row = self::loadCounters('COUNT(*) AS `cnt`', $action, $scope, $key, $window);

        return empty($row) ? 0 : (int)$row['cnt'];
    }

    /**
     * Время самой старой попытки за окно, если предел уже выбран.
     * @param  int    $action Что считаем, ACTION_*.
     * @param  int    $scope  Область счёта, SCOPE_*.
     * @param  string $key    Ключ счётчика.
     * @param  int    $window Окно счёта в секундах.
     * @param  int    $limit  Предел числа попыток за окно.
     * @return int Unix time самой старой попытки либо 0, если предел не выбран.
     */
    private static function getOldestAttempt($action, $scope, $key, $window, $limit)
    {
        $row = self::loadCounters(
            'COUNT(*) AS `cnt`, MIN(`date`) AS `oldest`',
            $action,
            $scope,
            $key,
            $window
        );

        return empty($row) || (int)$row['cnt'] < $limit ? 0 : (int)$row['oldest'];
    }

    /**
     * Читает счётчики за окно.
     * @param  string $select Список выражений выборки.
     * @param  int    $action Что считаем, ACTION_*.
     * @param  int    $scope  Область счёта, SCOPE_*.
     * @param  string $key    Ключ счётчика.
     * @param  int    $window Окно счёта в секундах.
     * @return array|null Строка результата либо null, если прочитать не удалось.
     */
    private static function loadCounters($select, $action, $scope, $key, $window)
    {
        try {
            $res = self::loadFromDV2([
                'SELECT' => $select,
                'FROM'   => LPMTables::AUTH_ATTEMPTS,
                'WHERE'  => self::attemptsWhere($action, $scope, $key, $window),
            ]);
        } catch (\Throwable $e) {
            self::logStorageFailure($e);
            return null;
        }

        return $res->fetch_assoc();
    }

    /**
     * Сообщает, что счётчики попыток недоступны: попытки в это время
     * ничем не ограничены.
     * @param \Throwable $e Ошибка обращения к базе.
     */
    private static function logStorageFailure(\Throwable $e)
    {
        LPMLog::exception($e, LPMLog::CH_APP, [
            'note' => 'Счётчики попыток авторизации недоступны, ограничение не применяется',
        ]);
    }

    /**
     * @param  int    $action Что считаем, ACTION_*.
     * @param  int    $scope  Область счёта, SCOPE_*.
     * @param  string $key    Ключ счётчика.
     * @param  int    $window Окно счёта в секундах.
     * @return array Условие выборки попыток за окно.
     */
    private static function attemptsWhere($action, $scope, $key, $window)
    {
        return [
            'action'   => (int)$action,
            'scope'    => (int)$scope,
            'scopeKey' => $key,
            'date'     => ['>=' => time() - (int)$window],
        ];
    }

    /**
     * @param int    $action Что считаем, ACTION_*.
     * @param int    $scope  Область счёта, SCOPE_*.
     * @param string $key    Ключ счётчика.
     */
    private static function removeAttempts($action, $scope, $key)
    {
        if ($key === '') {
            return;
        }

        try {
            self::buildAndSaveToDbV2([
                'DELETE' => LPMTables::AUTH_ATTEMPTS,
                'WHERE'  => [
                    'action'   => (int)$action,
                    'scope'    => (int)$scope,
                    'scopeKey' => $key,
                ],
            ]);
        } catch (\Throwable $e) {
            self::logStorageFailure($e);
        }
    }

    /**
     * Ключ счётчика для области.
     * @param  int    $scope Область счёта, SCOPE_*.
     * @param  string $email Адрес из формы.
     * @return string Пустая строка, если для этой области ключа нет —
     *                тогда правило не применяется.
     */
    private static function scopeKey($scope, $email)
    {
        switch ($scope) {
            case self::SCOPE_IP:
                // Только достоверно установленный адрес: за прокси, который
                // не объявлен доверенным, он один на всех, и предел по нему
                // закрыл бы вход сразу всем пользователям.
                $ip = ClientIp::getTrusted();
                return $ip === '' ? '' : self::hash('ip:' . $ip);
            case self::SCOPE_IP_ACCOUNT:
                return self::pairKey($email);
            case self::SCOPE_ACCOUNT:
                return self::accountKey($email);
            default:
                return '';
        }
    }

    /**
     * Ключ счётчика по паре «адрес + учётная запись».
     *
     * Здесь годится и адрес прокси: предел по паре от этого только строже,
     * а закрыть вход всем сразу он не может — в ключ входит учётная запись.
     *
     * @param  string $email Адрес из формы.
     * @return string Пустая строка, если ключ построить не из чего.
     */
    private static function pairKey($email)
    {
        $ip = ClientIp::getTrusted();
        if ($ip === '') {
            $ip = ClientIp::getRemoteAddr();
        }

        $account = self::normalizeEmail($email);

        return $ip === '' || $account === '' ? '' : self::hash('ip-account:' . $ip . ':' . $account);
    }

    /**
     * Ключ счётчика по учётной записи.
     * @param  string $email Адрес из формы.
     * @return string Пустая строка, если адрес пустой.
     */
    private static function accountKey($email)
    {
        $account = self::normalizeEmail($email);

        return $account === '' ? '' : self::hash('account:' . $account);
    }

    /**
     * Приводит адрес к виду, в котором его сравнивает база.
     *
     * Колонка с адресами сравнивается без учёта регистра и диакритики
     * (`utf8mb3_general_ci`), то есть `úsER@example.com` и `user@example.com` —
     * одна и та же учётная запись. Ключ счётчика должен склеивать их так же,
     * иначе предел обходится перебором написаний одного адреса.
     *
     * Совпадение приблизительное: база сводит вместе и то, чего разложением
     * не получить. Но перебирать при этом становится нечего — вариантов
     * написания остаётся столько же, сколько самих адресов.
     *
     * @param  string $email Адрес из формы.
     * @return string Пустая строка, если адреса нет.
     */
    private static function normalizeEmail($email)
    {
        $email = mb_strtolower(trim((string)$email));

        // Проверяем метод, а не класс: на инстанции без intl класса нет вовсе,
        // а при disable_classes от него остаётся заглушка без методов.
        if ($email === '' || !method_exists('Normalizer', 'normalize')) {
            return $email;
        }

        $decomposed = Normalizer::normalize($email, Normalizer::FORM_KD);
        if (!is_string($decomposed)) {
            return $email;
        }

        // После разложения диакритика стоит отдельными знаками - убираем их.
        $folded = preg_replace('/\p{Mn}+/u', '', $decomposed);

        return is_string($folded) && $folded !== '' ? $folded : $email;
    }

    /**
     * @param  string $value Значение ключа.
     * @return string Хэш длиной 64 символа — под колонку `scopeKey`.
     */
    private static function hash($value)
    {
        return hash('sha256', $value);
    }

    /**
     * Удаляет записи, которые уже не попадают ни в одно окно.
     */
    private static function cleanup()
    {
        if (mt_rand(1, self::CLEANUP_RATE) !== 1) {
            return;
        }

        try {
            self::buildAndSaveToDbV2([
                'DELETE' => LPMTables::AUTH_ATTEMPTS,
                'WHERE'  => [
                    'date' => ['<' => time() - self::KEEP_ATTEMPTS],
                ],
            ]);
        } catch (\Throwable $e) {
            self::logStorageFailure($e);
        }
    }
}
