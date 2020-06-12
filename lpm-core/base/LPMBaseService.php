<?php
class LPMBaseService extends SecureService
{
    /**
     * @var LightningEngine
     */
    protected $_engine;
    /**
     * @var LPMAuth
     */
    protected $_auth;
    protected $_user;

    private $_dbError;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function beforeFilter($calledFunc)
    {
        if (in_array($calledFunc, $this->_allowMethods)) {
            return true;
        }
    
        $this->_engine = new LightningEngine();
        $this->_auth = $this->_engine->getAuth();
    
        return $this->_auth->isLogin();
    }
    
    protected function errorDBSave()
    {
        return $this->error('Error DB save');
    }
    
    protected function errorDBLoad()
    {
        return $this->error('Error DB load');
    }

    protected function error($message = '', $errno = 0, $useLang = true)
    {
        if (DEBUG) {
            $dbError = $this->_dbError;
            if (empty($dbError)) {
                $db = LPMGlobals::getInstance()->getDBConnect();
                if ($db && $db->error) {
                    $dbError = '#' . $db->errno . ': ' . $db->error;
                }
            }

            if (!empty($dbError)) {
                if (empty($message)) {
                    $message = 'DB error';
                }
                $message .= ' (' . $_dbError . ')';
            }
        }

        return parent::error($message, $errno, $useLang);
    }

    protected function exception(Exception $e)
    {
        if (DEBUG) {
            if ($e instanceof DBException) {
                $db = $e->getDB();
            } elseif ($e instanceof \GMFramework\DBException) {
                $db = $e->getDB();
            } else {
                $db = LPMGlobals::getInstance()->getDBConnect();
            }

            if ($db && $db->error) {
                $this->_dbError = '#' . $db->errno . ': ' . $db->error;
            }
        }

        return parent::exception($e);
    }
    
    protected function floatArr($arr, $justPositive = true)
    {
        if (!is_array($arr)) {
            return false;
        }
        $newArr = array();
        foreach ($arr as $item) {
            $item = (float)$item;
            if ($justPositive && $item <= 0) {
                continue;
            }
            if (!in_array($item, $newArr)) {
                array_push($newArr, $item);
            }
        }
        return $newArr;
    }
    
    protected function getUser()
    {
        if (!$this->_auth->isLogin()) {
            return false;
        }
        if (!$this->_user) {
            $this->_user = User::load($this->_auth->getUserId());
        }
        return $this->_user;
    }
    
    protected function checkRole($reqRole)
    {
        if (!$user = $this->getUser()) {
            return false;
        }
        return $user->checkRole($reqRole);
    }
    
    protected function addComment($instanceType, $instanceId, $text)
    {
        if (!$user = $this->getUser()) {
            return false;
        }
        
        // TODO: перенести в Comment
        $text = trim($text);
        if ($text == '') {
            $this->error('Недопустимый текст');
            return false;
        }

        setcookie('Delete', 'Удалить', time()+600, "/");

        $comment = Comment::add($instanceType, $instanceId, $user->userId, $text);
        if ($comment) {
            $comment->author = $user;
            // Записываем лог
            UserLogEntry::create(
                $user->userId,
                DateTimeUtils::$currentDate,
                UserLogEntryType::ADD_COMMENT,
                $comment->id
            );
        }

        return $comment;
    }
}
