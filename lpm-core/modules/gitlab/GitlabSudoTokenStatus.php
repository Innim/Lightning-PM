<?php
/**
 * Состояние общего токена интеграции с GitLab (`GITLAB_TOKEN`).
 *
 * Личные токены пользователей таск перевыписывает сам, а этот задан в
 * конфигурации, и заменить его может только администратор. Когда он истечёт,
 * встанет вся интеграция - вплоть до самой возможности выписывать личные
 * токены, - поэтому о приближении срока администратора предупреждают заранее.
 *
 * Срок спрашивается у GitLab не чаще раза в {@see self::CHECK_INTERVAL_SEC}
 * и кладётся в сессию администратора. Это кэш, а не состояние приложения:
 * потерять его безопасно, следующий запрос спросит заново.
 */
class GitlabSudoTokenStatus
{
    /** За сколько дней до истечения предупреждать администратора. */
    const WARN_DAYS = 30;

    /** Как часто спрашивать срок у GitLab, секунды. */
    const CHECK_INTERVAL_SEC = 21600;

    /** Ключ, под которым состояние кэшируется в сессии. */
    const SESSION_KEY = 'gitlabSudoTokenState';

    /**
     * Состояние токена, о котором стоит предупредить текущего пользователя.
     *
     * @return GitlabSudoTokenStatus|null Состояние либо null, если
     * предупреждать не о чем: пользователь не администратор, интеграция
     * не настроена, срок токена неизвестен или до истечения ещё далеко.
     */
    public static function getWarning()
    {
        $engine = LightningEngine::getInstance();
        if (empty($engine)) {
            return null;
        }

        $user = $engine->getUser();
        if (empty($user) || !$user->isAdmin()) {
            return null;
        }

        $state = self::loadState($engine->gitlab());
        if (empty($state)) {
            return null;
        }

        $status = new self($state['expired'], $state['expiresAt']);

        return $status->needsWarning() ? $status : null;
    }

    /**
     * Состояние токена из кэша сессии, при необходимости обновлённое из GitLab.
     *
     * @param  GitlabIntegration $gitlab Интеграция.
     * @return array|null <code>['checkedAt' => int, 'expired' => bool,
     * 'expiresAt' => string]</code> либо null, если интеграция не настроена.
     */
    private static function loadState(GitlabIntegration $gitlab)
    {
        if (!$gitlab->isAvailable()) {
            return null;
        }

        $session = Session::getInstance();
        $state = $session->get(self::SESSION_KEY);

        if (is_array($state) && isset($state['checkedAt'])
                && $state['checkedAt'] + self::CHECK_INTERVAL_SEC > time()) {
            return $state;
        }

        $info = $gitlab->getSudoTokenInfo();

        // Отметка о проверке ставится и при неудаче: иначе недоступный GitLab
        // опрашивался бы при отрисовке каждой страницы
        $state = [
            'checkedAt' => time(),
            'expired' => !empty($info['expired']),
            'expiresAt' => empty($info['expiresAt']) ? '' : $info['expiresAt'],
        ];
        $session->set(self::SESSION_KEY, $state);

        return $state;
    }

    /**
     * Токен уже недействителен - истёк или отозван.
     * @var bool
     */
    private $_expired;

    /**
     * Дата истечения в формате `Y-m-d`; пустая строка у бессрочного токена
     * либо если срок узнать не удалось.
     * @var string
     */
    private $_expiresAt;

    /**
     * @param bool   $expired   Токен уже недействителен.
     * @param string $expiresAt Дата истечения в формате `Y-m-d` или пустая строка.
     */
    public function __construct($expired, $expiresAt)
    {
        $this->_expired = (bool)$expired;
        $this->_expiresAt = (string)$expiresAt;
    }

    /**
     * Истёк ли токен.
     * @return bool
     */
    public function isExpired()
    {
        if ($this->_expired) {
            return true;
        }

        $daysLeft = $this->getDaysLeft();

        return $daysLeft !== null && $daysLeft <= 0;
    }

    /**
     * Дата истечения токена в виде `дд-мм-гггг`.
     * @return string Пустая строка, если дата неизвестна.
     */
    public function getExpiresAtStr()
    {
        $expiresTs = $this->getExpiresTs();
        if ($expiresTs === null) {
            return '';
        }

        return DateTimeUtils::date(
            DateTimeFormat::DAY_OF_MONTH_2 . '-' .
            DateTimeFormat::MONTH_NUMBER_2_DIGITS . '-' .
            DateTimeFormat::YEAR_NUMBER_4_DIGITS,
            $expiresTs
        );
    }

    /**
     * Сколько дней осталось до истечения.
     * @return int|null Отрицательное значение - срок уже прошёл;
     * null, если дата истечения неизвестна.
     */
    public function getDaysLeft()
    {
        $expiresTs = $this->getExpiresTs();
        if ($expiresTs === null) {
            return null;
        }

        return (int)floor(($expiresTs - strtotime('today')) / 86400);
    }

    /**
     * Нужно ли предупреждать администратора прямо сейчас.
     * @return bool
     */
    private function needsWarning()
    {
        if ($this->_expired) {
            return true;
        }

        $daysLeft = $this->getDaysLeft();

        return $daysLeft !== null && $daysLeft <= self::WARN_DAYS;
    }

    /**
     * Момент истечения токена.
     * @return int|null null, если дата неизвестна.
     */
    private function getExpiresTs()
    {
        if ($this->_expiresAt === '') {
            return null;
        }

        $expiresTs = strtotime($this->_expiresAt);

        return $expiresTs === false ? null : $expiresTs;
    }
}
