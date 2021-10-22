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
     */
    public function getUsers4Send($userIds, $pref, $exceptMe = true)
    {
        $userIds = array_unique($userIds);
        
        $prefField = $this->getPrefField($pref);
        
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
     * @param array $users
     * @param int $pref одна из констант <code>EmailNotifier::PREF_*</code>
     */
    public function sendMail2Allowed($subject, $text, $userIds, $pref)
    {
        return $this->sendMail2Users($subject, $text, $this->getUsers4Send($userIds, $pref));
    }
    
    public function sendMail2Users($subject, $text, $users)
    {
        if (!empty($users)) {
            $text .= "\n\n--\n" . LPMOptions::getInstance()->emailSubscript;
            
            foreach ($users as /*@var $user User */ $user) {
                $this->send(
                    $user->email,
                    $user->getName(),
                    $subject,
                    $text
                );
            }
        }
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
            true
        );
        if (LPMGlobals::isDebugMode()) {
            GMLog::getInstance()->logIt(
                GMLog::getInstance()->logsPath . '/emails/' .
                DateTimeUtils::mysqlDate(null, false) . '-' .
                DateTimeUtils::date('H-i-s') . '.html',
                $mess->getSubject() . "\r\n" .
                $mess->getMessage(),
                'email'
            );
            //	return true;
        }

        return $this->_mail->send($mess);
    }
    
    private function getPrefField($pref)
    {
        //$field = '';
        switch ($pref) {
            case self::PREF_ADD_ISSUE: return 'seAddIssue';
            case self::PREF_EDIT_ISSUE: return 'seEditIssue';
            case self::PREF_ISSUE_STATE: return 'seIssueState';
            case self::PREF_ISSUE_COMMENT: return 'seIssueComment';
            default: return 1;
        }
        //return '`' . $field . '`';
    }
}
