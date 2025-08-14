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
                $engine->getAuth()->destroy();
                LightningEngine::go2URL();
            } break;
            default: {
                $user = $engine->getUser();

                $this->addTmplVar('user', $user);
                $this->addTmplVar('isPM', Member::isPMForAnyProject($user->getID()));
            }
        }
        
        return $this;
    }
}
