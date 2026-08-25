<?php
/**
 * Страница профиля зарегистрированного пользователя.
 */
class ProfilePage extends LPMPage
{
    const UID = 'profile';
    
    const SUID_EXIT = 'exit';

    /**
     *
     * @var Project
     */
    private $_project;

    public function __construct()
    {
        parent::__construct(self::UID, 'Профиль', true, false);

        array_push($this->_js, 'profile');
        $this->_pattern = 'profile';
    }
    
    public function init()
    {
        if (!parent::init()) {
            return false;
        }
        
        $engine = $this->_engine;
        
        switch ($engine->getParams()->suid) {
            case self::SUID_EXIT: {
                // Выход меняет состояние, поэтому только своей формой и POST-ом:
                // по ссылке пользователя мог бы разлогинить любой сторонний сайт.
                if ($this->isExitConfirmed()) {
                    $engine->getAuth()->destroy();
                    LightningEngine::go2URL();
                }

                $engine->addError('Не удалось выйти. Обновите страницу и попробуйте снова');
            }
            // no break - без подтверждения показываем обычную страницу профиля
            // falls through
            default: {
                $user = $engine->getUser();

                $this->addTmplVar('user', $user);
                $this->addTmplVar('isPM', Member::isPMForAnyProject($user->getID()));
                $this->addTmplVar('apiKeys', ApiKey::loadListByUserId($user->getID()));
            }
        }
        
        return $this;
    }

    /**
     * Определяет, что выход запрошен со своей же страницы.
     * @return bool
     */
    private function isExitConfirmed()
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && CsrfToken::check();
    }
}
