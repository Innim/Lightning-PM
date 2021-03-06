<?php
require_once(__DIR__ . '/../init.inc.php');

/**
 * Сервис работы с пользователями.
 */
class UsersService extends LPMBaseService
{
    /**
     * Блокирует пользователя.
     * @param  int $userId Идентификатор пользователя.
     * @param  bool $isLock Заблокирован или нет.
     */
    public function lockUser($userId, $isLock)
    {
        $locked = (bool)$isLock;
        $userId = (int)$userId;

        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        if ($userId <= 0) {
            return $this->error('Неверный идентификатор пользователя');
        }
        
        if (!User::updateLocked($userId, $locked)) {
            return $this->error('Ошибка записи в БД');
        }
        
        return $this->answer();
    }

    /**
     * Устанавливает Slack name (Member ID) для пользователя.
     * @param  int $userId Идентификатор пользователя.
     * @param  string $slackName Имя в Slack (Member ID).
     */
    public function setSlackName($userId, $slackName)
    {
        $slackName = trim((string)$slackName);
        $userId = (int)$userId;

        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        if ($userId <= 0) {
            return $this->error('Неверный идентификатор пользователя');
        }
        
        if (!User::updateSlackName($userId, $slackName)) {
            return $this->error('Ошибка записи в БД');
        }
        
        return $this->answer();
    }
}
