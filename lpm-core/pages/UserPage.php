<?php
/**
 * Страница просмотра и редактирования информации о пользователе.
 */
class UserPage extends LPMPage
{
    const UID = 'user';
    const PUID_EDIT = 'edit';
    
    public function __construct()
    {
        parent::__construct(self::UID, '', true, true);

        $this->_js[] = 'user';
        $this->_pattern = 'user';

        $this->_defaultPUID = self::UID;
        $this->_baseParamsCount = 2;

        $this->addSubPage(
            self::PUID_EDIT,
            'Редактирование',
            'user-edit',
            null,
            '',
            User::ROLE_MODERATOR
        );
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
        
        if (empty($this->_curSubpage)) {
            $this->addTmplVar('editUrl', $this->getEditUrl($userId));
        } else {
            switch ($this->_curSubpage->uid) {
                case self::PUID_EDIT:
                    $this->addTmplVar('viewUrl', $this->getBaseUrl());
                    break;
            }
        }

        return $this;
    }

    private function getEditUrl($userId)
    {
        return $this->getBaseUrl(self::PUID_EDIT);
    }

    private function getUserIdParam()
    {
        return (int)$this->getParam(1);
    }
}
