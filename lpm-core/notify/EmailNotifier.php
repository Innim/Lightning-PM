<?php
class EmailNotifier extends LPMBaseObject
{
    private static $_instance;
    /**
     * @return EmailNotifier
     */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new EmailNotifier(!defined('EMAIL_NOTIFY_ENABLED') || EMAIL_NOTIFY_ENABLED);
        }
        
        return self::$_instance;
    }
    
    const PREF_ADD_ISSUE     = 1;
    const PREF_EDIT_ISSUE    = 2;
    const PREF_ISSUE_STATE   = 3;
    const PREF_ISSUE_COMMENT = 4;

    const PREF_ROLE_PM = 1000;
    
    // TODO: переделать флаг чтобы отключал только оповещения, но не запросы восстановления пароля например
    /**
     * @var bool
     */
    private $_enabled;

    /**
     *
     * @var MailgunSender
     */
    private $_mail;
    
    public function __construct($enabled = true)
    {
        parent::__construct();
        
        $this->_enabled = $enabled;
        $this->_mail = MailgunSender::create(
            LPMOptions::getInstance()->fromEmail,
            LPMOptions::getInstance()->fromName
        );
    }
    
    /**
     * Возвращает список пользователей, имеющих указанные идентификаторы,
     * которым можно отправить email.
     *
     * Возможность отправки определяется по настройках пользователя,
     * а также по текущему состоянию (заблокированным пользователям не отправляется).
     *
     * @param array $userIds
     * @param int $pref одна из констант <code>EmailNotifier::PREF_*</code>
     * @param bool $exceptMe исключать ли текущего пользователя из списка
     * @param int $prefRole роль, для которой проверяется настройка $pref. 
     *                      Одна из констант <code>EmailNotifier::PREF_ROLE_*</code> 
     * @return User[]
     */
    public function getUsers4Send($userIds, $pref, $exceptMe = true, $prefRole = 0)
    {
        $userIds = array_unique($userIds);
        
        $prefField = $this->getPrefField($pref, $prefRole);
        
        if ($exceptMe && LPMAuth::$current) {
            ArrayUtils::remove($userIds, LPMAuth::$current->getUserId());
        }
        
        return empty($userIds)
                ? []
                : User::loadList(
                    "`%1\$s`.`userId` IN (" . implode(',', $userIds) . ") " .
                     ($prefField !== 1 ? "AND `%2\$s`.`" . $prefField . "` = 1" : ""),
                    true
                );
    }
    
    /**
     * Выполняет проверку прав, потом делает рассылку
     *
     * @param string $subject
     * @param string $text
     * @param User[] $users
     * @param int $pref одна из констант <code>EmailNotifier::PREF_*</code>
     * @param int $prefRole роль, для которой проверяется настройка $pref.
     *                      Одна из констант <code>EmailNotifier::PREF_ROLE_*</code>
     * @return int[] список идентификаторов пользователей, которым отправлено письмо
     */
    public function sendMail2Allowed($subject, $text, $userIds, $pref, $prefRole = 0)
    {
        return $this->sendMail2Users($subject, $text, $this->getUsers4Send($userIds, $pref, true, $prefRole));
    }
    
    /**
     * Отправляет письмо указанным пользователям.
     * 
     * @param string $subject
     * @param string $text
     * @param User[] $users
     * @return int[] список идентификаторов пользователей, которым отправлено письмо
     */
    public function sendMail2Users($subject, $text, $users)
    {
        if (empty($users)) {
            return [];
        }

        $sentToIds = [];
        $text .= "\n\n--\n" . LPMOptions::getInstance()->emailSubscript;
        foreach ($users as $user) {
            $this->send(
                $user->email,
                $user->getName(),
                $subject,
                $text
            );
            $sentToIds[] = $user->getID();
        }

        return $sentToIds;
    }
    
    public function send($toEmail, $toName, $subject, $messText)
    {
        if (!$this->_enabled) {
            return false;
        }
        
        $mess = new EmailMessage(
            $subject,
            $messText,
            $toEmail,
            $toName,
            false
        );
        if (LPMGlobals::isDebugMode()) {
            GMLog::getInstance()->logIt(
                GMLog::getInstance()->logsPath . '/emails/' .
                DateTimeUtils::mysqlDate(null, false) . '-' .
                DateTimeUtils::date('H-i-s') . '.html',
                "Send email\r\n" .
                'To: ' . $mess->getToName() . ' <' . $mess->getToEmail() . ">\r\n" .
                'Subject: ' . $mess->getSubject() . "\r\n\r\n" .
                $mess->getMessage(),
                'email'
            );

            if (defined('EMAIL_NOTIFY_LOG_ONLY_IN_DEBUG') && EMAIL_NOTIFY_LOG_ONLY_IN_DEBUG) {
                // в дебаге не отправляем, а только логируем
                return true;
            }
        }

        return $this->_mail->send($mess);
    }
    
    private function getPrefField($pref, $prefRole)
    {
        $fieldBase = '';
        $suffix = '';

        switch ($pref) {
            case self::PREF_ADD_ISSUE: {
                $fieldBase = 'seAddIssue';
                break;
            }
            case self::PREF_EDIT_ISSUE: {
                $fieldBase = 'seEditIssue';
                break;
            }
            case self::PREF_ISSUE_STATE: {
                $fieldBase = 'seIssueState';
                break;
            }
            case self::PREF_ISSUE_COMMENT: {
                $fieldBase = 'seIssueComment';
                break;
            }
            default: return 1;
        }

        switch ($prefRole) {
            case self::PREF_ROLE_PM:
                $suffix = 'ForPM';
                break;
            default:
                $suffix = '';
        }

        return $fieldBase . $suffix;
    }
}
