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
     * Изменяет роль пользователя. Доступно только администратору.
     * @param  int $userId Идентификатор пользователя.
     * @param  int $role Новая роль (одна из констант User::ROLE_*).
     */
    public function setRole($userId, $role)
    {
        $userId = (int)$userId;
        $role = (int)$role;

        if (!$this->checkRole(User::ROLE_ADMIN)) {
            return $this->error('Недостаточно прав');
        }

        if ($userId <= 0) {
            return $this->error('Неверный идентификатор пользователя');
        }

        if (!array_key_exists($role, User::getRolesMap())) {
            return $this->error('Неверная роль');
        }

        if ($userId == $this->getUserId()) {
            return $this->error('Нельзя изменить собственную роль');
        }

        if (!User::updateRole($userId, $role)) {
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
