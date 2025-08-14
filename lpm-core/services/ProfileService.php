<?php
require_once(dirname(__FILE__) . '/../init.inc.php');

class ProfileService extends LPMBaseService
{
    public function emailPref($data)
    {
        $allowed = [
            'seAddIssue', 'seEditIssue', 'seIssueState', 'seIssueComment',
            'seAddIssueForPM', 'seEditIssueForPM', 'seIssueStateForPM', 'seIssueCommentForPM'
        ];

        $fieldsForUpdate = [];
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed)) {
                $fieldsForUpdate[$field] = (int)(boolean)$value;
            } else {
                return $this->error('Недопустимое поле: ' . $field);
            }
        }

        $db = $this->_db;
        $userId = $this->getUserId();

        $res = $db->queryb([
            'UPDATE' => LPMTables::USERS_PREF,
            'SET' => $fieldsForUpdate,
            'WHERE' => ['userId' => $userId]
        ]);
        
        if (!$res) {
            return $this->error('Ошибка записи в БД');
        }
        
        return $this->answer();
    }
    
    public function newPass($currentPass, $newPass)
    {
        $sql = "SELECT `pass` FROM `%s` " .
               "WHERE `userId` = '" . $this->_auth->getUserId() . "'";
        if (!$query = $this->_db->queryt($sql, LPMTables::USERS)) {
            return $this->error('Ошибка чтения из базы');
        } elseif ($userInfo = $query->fetch_assoc()) {
            if (!User::passwordVerify($currentPass, $userInfo['pass'])) {
                return $this->error('Неверный пароль');
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
