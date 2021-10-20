<?php

class PassRecoveryPage extends LPMPage
{
    private $_show ;
    private $_userId;
    private $_recoveryKey;
    
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
           
        if (!empty($_POST)) {
            foreach ($_POST as $key => $value) {
                $_POST[$key] = trim($value);
            }
            if (isset($_POST['remail'])) {
                // TODO: вынеси отсюда все сохранение и выделить работу с БД
                $db = LPMGlobals::getInstance()->getDBConnect();
                $email = $db->escape_string($_POST['remail']);
                $sql = "SELECT `userId`, `pass`, `locked`, `firstName` " .
                       "FROM `%s` WHERE `email` = '" . $email . "'";
                if (!$query = $db->queryt($sql, LPMTables::USERS)) {
                    $this->_engine->addError('Ошибка чтения из базы');
                } elseif ($userInfo = $query->fetch_assoc()) {
                    if ($this->sendRecoveryEmail($userInfo['userId'], $userInfo['firstName'], $email)) {
                        $this->_show = 'successEmail';
                    }
                } else {
                    $this->_engine->addError('Пользователь с таким email не зарегистрирован');
                }
            } elseif (isset($_POST['newPass']) && isset($_POST['rePass']) && isset($_POST['userId']) && isset($_POST['key'])) {
                if ($_POST['newPass'] != $_POST['rePass']) {
                    $this->_engine->addError('Пароли не совпадают');
                    $this->_show = 'changePassForm';
                } else {
                    $this->updatePass($_POST['newPass'], $_POST['userId'], $_POST['key']);
                }
            }
        } elseif ($this->getPUID() == 'reclink') {
            $key = $this->getAddParam(0);
            $userId = $this->_engine->getParams()->getQueryArg('userId');

            $userId = base64_decode(urldecode($userId));
            if (!empty($key)&& !empty($userId)) {
                if ($this->checkUrlKey($key, $userId)) {
                    $this->_userId = $userId;
                    $this->_recoveryKey = $key;
                    $this->_show = 'changePassForm';
                }
            }
        } else {
            $this->_show = 'emailForm';
        }
        
        return $this;
    }
    
    private function checkUrlKey($key, $userId)
    {
        $savedKey = $this->getActualKey($userId);
        if ($savedKey !== false) {
            if ($savedKey === null || $savedKey !== $key) {
                $this->_engine->addError('Запись не найдена');
            } else {
                return true;
            }
        }

        return false;
    }

    private function getActualKey($userId)
    {
        // TODO: вынеси отсюда все сохранение и выделить работу с БД
        $db = LPMGlobals::getInstance()->getDBConnect();
        $curDate = DateTimeUtils::mysqlDate();
        $sql = "SELECT `recoveryKey` FROM `%s` WHERE `userId` = '" . $userId .
            "' AND `expDate` >= '". $curDate . "' LIMIT 1";
        if (!$query = $db->queryt($sql, LPMTables::RECOVERY_EMAILS)) {
            $this->_engine->addError('Ошибка чтения из базы');
            return false;
        } elseif ($row = $query->fetch_assoc()) {
            return $row['recoveryKey'];
        } else {
            return null;
        }
    }
    
    private function sendRecoveryEmail($userId, $firstName, $email)
    {
        // Проверим, нет ли актуального письма
        $currentKey = $this->getActualKey($userId);
        if ($currentKey === false) {
            return false;
        }

        if ($currentKey != null) {
            $this->_engine->addError('Письмо уже было отправлено на данный email');
            return false;
        }

        // TODO: вынеси отсюда все сохранение и выделить работу с БД
        $db = LPMGlobals::getInstance()->getDBConnect();

        $expFormat = mktime(date("H"), date("i"), date("s"), date("m"), date("d")+1, date("Y"));
        $expDate = date("Y-m-d H:i:s", $expFormat);
        $key = md5(BaseString::randomStr());
        $sql = "REPLACE INTO `%s` (`userId`, `recoveryKey`, `expDate` )" .
               "VALUES ('" . $userId . "', '" . $key . "', '" . $expDate . "' )";

        if (!$db->queryt($sql, LPMTables::RECOVERY_EMAILS)) {
            $this->_engine->addError('Ошибка записи в базу');
            return false;
        } else {
            $href = "pass-recovery/reclink/" . $key . "/?userId=" . urlencode(base64_encode($userId));
            $recoveryLink ='<a href="'. SITE_URL . $href .'"> ' . SITE_URL .  $href . '</a>';
            $message  = "Здравствуйте $firstName,\r\n";
            $message .= "Для восстановления пароля перейдите по ссылке:\r\n";
            $message .= "-----------------------\r\n";
            $message .= "$recoveryLink\r\n";
            $message .= "-----------------------\r\n";
            $message .= "Ссылка будет действительна в течении суток.\r\n\r\n";
            $subject = "Восстановление пароля";
            $res = EmailNotifier::getInstance()->send($email, $firstName, $subject, $message);
            if ($res) {
                return true;
            } else {
                $this->_engine->addError('Не удалось отправить письмо, попробуйте позже или свяжитесь с администратором.');
                // TODO: удалить из базы? или дать возможность запросить отправку еще раз
                return false;
            }
        }
    }
    
    private function updatePass($newPass, $userId, $key)
    {
        if ($this->checkUrlKey($key, $userId)) {
            // TODO: вынеси отсюда все сохранение и выделить работу с БД
            $db = LPMGlobals::getInstance()->getDBConnect();

            $salt = User::blowfishSalt();
            $sql = "UPDATE `%s` SET ".
                   "`pass` = '" . User::passwordHash($newPass, $salt) . "' " .
                   "WHERE `userId` = '" . $userId . "'";
            if (!$db->queryt($sql, LPMTables::USERS)) {
                $this->_engine->addError('Ошибка записи в БД');
            } else {
                $this->_show = 'recoverySuccess';
                $sql = "DELETE FROM `%s` WHERE `recoveryKey` = '" . $key . "'";
                if (!$db->queryt($sql, LPMTables::RECOVERY_EMAILS)) {
                    $this->_engine->addError('ошибка удаления');
                }
            }
        }
    }
    
    public function getUserId()
    {
        return $this->_userId;
    }
    
    public function getKey()
    {
        return $this->_recoveryKey;
    }
    
    public function getShow()
    {
        return $this->_show;
    }
}
