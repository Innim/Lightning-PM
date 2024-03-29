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

    protected function extract2Answer($data)
    {
        foreach ($data as $key => $value) {
            $this->add2Answer($key, $value);
        }
    }
    
    protected function errorValidation($argName)
    {
        return $this->error('Argument ' . $argName . ' is not valid');
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
                $message .= ' (' . $dbError . ')';
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
    
    /**
     * @return User
     */
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

    protected function getUserId()
    {
        return $this->_auth->getUserId();
    }

    protected function checkRole($reqRole)
    {
        if (!$user = $this->getUser()) {
            return false;
        }
        return $user->checkRole($reqRole);
    }

    protected function getHtml(callable $printHtml)
    {
        return PageConstructor::getHtml($printHtml);
    }

    /**
     * @return GitlabIntegration
     */
    protected function getGitlabIfAvailable()
    {
        $client = LightningEngine::getInstance()->gitlab();
        if ($client->isAvailableForUser()) {
            return $client;
        } else {
            return null;
        }
    }

    /**
     * Получает проект и требует чтобы у текущего пользователя
     * быи права на чтение.
     *
     * Если нет такого проекта или нет прав на чтение -
     * будет порождено исключение.
     * @return Project
     */
    protected function getProjectRequireReadPermission($projectId)
    {
        if (!$user = $this->getUser()) {
            throw new Exception('Ошибка при загрузке пользователя');
        }
        
        if (!$project = Project::loadById($projectId)) {
            throw new Exception('Нет такого проекта');
        }
        
        if (!$project->hasReadPermission($user)) {
            throw new Exception('Недостаточно прав доступа');
        }

        return $project;
    }

    /**
     * Требует интеграцию таска, проекта и пользователя с GitLab
     * и возвращает экземпляр интеграции.
     *
     * Если интеграцию не удалось получить - порождает Exception.
     *
     * @return GitlabIntegration
     */
    protected function requireGitlabIntegration(Project $project)
    {
        if (!$project->isIntegratedWithGitlab()) {
            throw new Exception('Проект не интегрирован с GitLab');
        }

        $client = $this->getGitlabIfAvailable();
        if (!$client) {
            throw new Exception('Не удалось настроить интеграцию c GitLab для пользователя.');
        }

        return $client;
    }

    /**
     * @return CacheController
     */
    protected function cache()
    {
        return $this->_engine->cache();
    }
}
