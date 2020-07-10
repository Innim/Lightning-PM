<?php
/**
 * Страница просмотра и редактирования информации о пользователе.
 */
class UserPage extends LPMPage
{
    const UID = 'user';
    
    public function __construct()
    {
        parent::__construct(self::UID, '', true, true, '', '', User::ROLE_MODERATOR);

        $this->_js[] = 'user';
        $this->_pattern = 'user';
    }

    public function init()
    {
        if (!parent::init()) {
            return false;
        }

        $userId = $this->getUserIdParam();
        if (!$userId) {
            return false;
        }
        
        $user = User::load($userId);
        if (!$user) {
            return false;
        }

        $this->_title  = "Пользователь " . $user->getName();
        $this->_header = $user->getName();

        $this->addTmplVar('user', $user);

        return $this;
    }

    private function getUserIdParam()
    {
        return (int)$this->getParam(1);
    }
}
