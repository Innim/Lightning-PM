<?php
require_once(dirname(__FILE__) . '/../init.inc.php');

class ProfileService extends LPMBaseService
{
    public function createApiKey($name = '')
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->error('Пользователь не найден');
            }

            $this->extract2Answer(ApiKey::createForUser($user, $name));
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    public function revokeApiKey($keyId)
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return $this->error('Пользователь не найден');
            }

            ApiKey::revokeForUser((int)$keyId, $userId);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

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
        $newPass = (string)$newPass;
        if (!Validation::checkPass($newPass, PASSWORD_MAX_LENGTH, PASSWORD_MIN_LENGTH, true)) {
            return $this->error(sprintf(
                'Пароль должен быть от %d до %d символов - используйте латинские буквы, цифры или знаки',
                PASSWORD_MIN_LENGTH,
                PASSWORD_MAX_LENGTH
            ));
        }

        $userId = $this->getUserId();

        try {
            $currentHash = User::loadPasswordHash($userId);
            if ($currentHash === null) {
                return $this->error('Пользователь не найден');
            }

            if (!User::passwordVerify($currentPass, $currentHash)) {
                return $this->error('Неверный пароль');
            }

            $salt = User::blowfishSalt();
            User::updatePassword($userId, User::passwordHash($newPass, $salt));

            // Смена пароля должна отбирать доступ у того, кто увёл куки:
            // сохранённые ранее авторизации перестают действовать.
            $this->_auth->removeOtherSessions();
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }
}
