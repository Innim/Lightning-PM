<?php
require_once(dirname(__FILE__) . '/../init.inc.php');

class ProfileService extends LPMBaseService
{
    public function emailPref($addIssue, $editIssue, $issueState, $issueComment)
    {
        $sql = "UPDATE `%s` SET " .
                "`seAddIssue` = '" . (int)(boolean)$addIssue . "', " .
                "`seEditIssue` = '" . (int)(boolean)$editIssue . "', " .
                "`seIssueState` = '" . (int)(boolean)$issueState . "', " .
                "`seIssueComment` = '" . (int)(boolean)$issueComment . "' " .
                "WHERE `userId` = '" . $this->_auth->getUserId() . "'";
        
        if (!$this->_db->queryt($sql, LPMTables::USERS_PREF)) {
            return $this->error('Ошибка записи в БД');
        }
        
        return $this->answer();
    }
    
    public function newPass($curentPass, $newPass)
    {
        $sql = "SELECT `pass` FROM `%s` " .
               "WHERE `userId` = '" . $this->_auth->getUserId() . "'";
        if (!$query = $this->_db->queryt($sql, LPMTables::USERS)) {
            return $this->error('Ошибка чтения из базы');
        //$engine->addError( 'Ошибка чтения из базы' );
        } elseif ($userInfo = $query->fetch_assoc()) {
            if (!User::passwordVerify($curentPass, $userInfo['pass'])) {
                return $this->error('Неверный пароль');
            //$engine->addError( 'Неверный пароль' );
            } else {
                $salt = User::blowfishSalt();
                $sql = "UPDATE `%s` SET ".
                       "`pass` = '" . User::passwordHash($newPass, $salt) . "' " .
                       "WHERE `userId` = '" . $this->_auth->getUserId() . "'";
                if (!$this->_db->queryt($sql, LPMTables::USERS)) {
                    return $this->error('Ошибка записи в БД');
                }
            }
        }
        return $this->answer();
    }
}
