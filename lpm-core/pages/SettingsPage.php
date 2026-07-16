<?php
/**
 * Страница настроек приложения.
 *
 * Функционал для администратора.
 */
class SettingsPage extends LPMPage
{
    const UID = 'settings';

    public function __construct()
    {
        parent::__construct(self::UID, 'Настройки', true, false, 'settings', '', User::ROLE_ADMIN);
        $this->addJS('admin', 'settings');
    }

    public function init()
    {
        if (!parent::init()) {
            return false;
        }

        $this->addTmplVar('options', LPMOptions::getInstance());

        return $this;
    }
}
