<?php
class ProfilePage extends BasePage
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
        
        $engine = LightningEngine::getInstance();
        
        switch ($engine->getParams()->suid) {
            case self::SUID_EXIT: {
                $engine->getAuth()->destroy();
                LightningEngine::go2URL();
            } break;
            default: {
                // просмотр профиля
                /*if (isset( $_POST['form'])) {
                  //var_dump( $_SERVER );
                  if ($_POST['form'] == 'emailPref') {
                    $sql = "UPDATE `%s` SET " .
                            "`seAddIssue` = '" . (int)isset( $_POST['seAddIssue']) . "', " .
                            "`seEditIssue` = '" . (int)isset( $_POST['seEditIssue']) . "', " .
                            "`seIssueState` = '" . (int)isset( $_POST['seIssueState']) . "', " .
                            "`seIssueComment` = '" . (int)isset( $_POST['seIssueComment']) . "' " .
                           "WHERE `userId` = '" . $this->_engine->getAuth()->getUserId() . "'";
                    if (!$this->_db->queryt( $sql, LPMTables::USERS_PREF ))
                        $this->_engine->addError( 'Ошибка записи в базу' );
                    //else LightningEngine::go2URL( $this->getUrl() . '#settings' );
                  }
                }*/
            }
        }
        
        return $this;
    }
}
