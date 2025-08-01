<?php
/**
 * Движок Lightning Project Manager
 * @author GreyMag
 */
class LightningEngine
{
    /**
     * Поле для хранения URL предыдущей страницы.
     */
    const SESSION_PREV_PATH = 'lightning_prev_path';
    /**
     * Сообщения об ошибках, которые надо показать после смены страницы.
     */
    const SESSION_NEXT_ERRORS = 'lightning_next_errors';

    const API_PATH = 'api';
    const BADGES_PATH = 'badges';

    /**
     * @return LightningEngine
     */
    public static function getInstance()
    {
        return self::$_instance;
    }
    
    public static function go2URL($url = '', $queryArgs = null)
    {
        if ($url == '') {
            $url = SITE_URL;
        }
        if (!empty($queryArgs)) {
            $url .= '?' . http_build_query($queryArgs);
        }
        header('Location: '. $url . '#');
        exit;
    }
    
    public static function getURL($path, $queryArgs = null)
    {
        $url = SITE_URL . $path;
        if (!empty($queryArgs)) {
            $url .= '?' . http_build_query($queryArgs);
        }
        return $url;
    }

    public static function getHost() {
        $host = parse_url(SITE_URL, PHP_URL_HOST);

        if (DEBUG) {
            $port = parse_url(SITE_URL, PHP_URL_PORT);
            if (!empty($port)) {
                $host .= ':' . $port;       
            }
        }

        return $host;
    }
    
    /**
    *
    * @var LightningEngine
    */
    private static $_instance;
    
    /**
     * Авторизация
     * @var LPMAuth
     */
    private $_auth;
    /**
     * @var User
     */
    private $_user;
    /**
     *
     * @var PageConstructor
     */
    private $_constructor;
    /**
     * @var PagesManager
     */
    private $_pagesManager;
    /**
     * @var CommentsManager
     */
    private $_commentsManager;
    /**
     * @var LPMParams
     */
    private $_params;
    /**
     * @var BasePage
     */
    private $_curPage;
    /**
     * @var ExternalApiManager
     */
    private $_apiManager;
    /**
     * @var CacheController
     */
    private $_cache;
    
    /**
     * Ошибки, которые надо вывести пользователю
     * @var array
     */
    private $_errors = array();
    private $_nextErrors = array();

    /**
     * Время запуска.
     * @var float
     */
    private $_startTime;
    
    public function __construct()
    {
        if (self::$_instance != '') {
            throw new Exception(__CLASS__ . ' are singleton');
        }

        self::$_instance = $this;
        $this->_params       = new LPMParams();
        $this->_auth         = new LPMAuth($this->_params->getQueryArg(LPMParams::QUERY_ARG_SID));
        $this->_pagesManager = new PagesManager($this);
        $this->_constructor   = new PageConstructor($this->_pagesManager);
        $this->_apiManager   = new ExternalApiManager($this);

        $this->_startTime = microtime(true);
    }

    public function run()
    {
        $params = $this->_params;
        $arg0 = $params->getArg(0);
        if ($arg0 == self::API_PATH) {
            $params->shiftArg();
            $this->apiCall();
        } elseif ($arg0 == self::BADGES_PATH) {
            $this->staticGenerator();
        } else  {
            $this->createPage();
        }
    }

    /**
     * Время выполнения на текущий момент в секундах.
     * @return float
     */
    public function getExecutionTimeSec()
    {
        $current = microtime(true);
        return $current - $this->_startTime;
    }

    private function apiCall()
    {
        try {
            $params = $this->_params;
            $uid = $params->shiftArg();

            if (!$uid) {
                throw new Exception('API uid is not defined');
            }

            $api = $this->_apiManager->getByUid($uid);
            if (!$api) {
                throw new Exception('API with uid ' . $uid . ' is not registered');
            }

            $result = $api->run(file_get_contents('php://input'));
            echo $result;
        } catch (Exception $e) {
            $this->debugOnException($e);
            die('Fatal API call error');
        }
    }

    private function staticGenerator() {
        try {
            $params = $this->_params;
            $uid = $params->shiftArg();

            if (!$uid) {
                throw new Exception('Static generator uid is not defined');
            }

            switch ($uid) {
                case self::BADGES_PATH:
                    $id = $params->shiftArg();
                    if (!$id) {
                        throw new Exception('Badges generator id is not defined');
                    }

                    $generator = new BadgesGenerator($this, $id);
                    break;
                default:
                    throw new Exception('Static generator with uid ' . $uid . ' is not registered');
            }
            
            $result = $generator->generate();
            $headers = $generator->getHeaders();
            
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }

            echo $result;
        } catch (Exception $e) {
            $this->debugOnException($e);
            die('Fatal static generator call error');
        }
    }

    private function createPage()
    {
        $session = Session::getInstance();
        $nextErrors = $session->get(self::SESSION_NEXT_ERRORS);
        if (!empty($nextErrors)) {
            $nextErrors = unserialize($nextErrors);
            foreach ($nextErrors as $error) {
                $this->addError($error);
            }
            $session->unsetVar(self::SESSION_NEXT_ERRORS);
        }

        try {
            $this->_curPage = $this->initCurrentPage();
        } catch (Exception $e) {
            $this->debugOnException($e);
            die('Fatal error');
        }

        try {
            $this->_constructor->createPage();
        } catch (Exception $e) {
            $this->debugOnException($e);

            $this->addNextError($e->getMessage());
            $path = $session->get(self::SESSION_PREV_PATH);
            $session->unsetVar(self::SESSION_PREV_PATH);

            $url = empty($path) ? SITE_URL : self::getURL($path);
            PagePrinter::jsRedirect($url);
            exit();
        }

        // Все прошло успешно - запоминаем URL как предыдущий
        // TODO: тут косяк если параллельно открыто несколько страниц
        $session->set(self::SESSION_PREV_PATH, $this->getCurrentUrlPath());
    }
    
    public function addError($errString)
    {
        if (LPMGlobals::isDebugMode()) {
            $dbError = $this->getDebugDbError();
            if ($dbError) {
                $errString = implode("\n", [
                    $errString,
                    '',
                    $dbError,
                ]);
            }
        }
        $this->_errors[] = $errString;
        return false;
    }

    public function getDebugDbError()
    {
        // В debug добавляем ошибку БД в текст, чтобы проще отлаживать
        $db = LPMGlobals::getInstance()->getDBConnect();
        if ($db->errno > 0) {
            $errLines = [
                '[DEBUG INFORMATION]',
                'DB error #' . $db->errno . ': ' . $db->error . '',
            ];

            $lastQuery = $db->lastQuery;
            if (empty($lastQuery)) {
                $errLines[] = 'No last query information';
            } else {
                $errLines[] = 'SQL: ';
                $errLines[] = $lastQuery;
            }

            return implode("\n", $errLines);
        } else {
            return false;
        }
    }
    
    public function addNextError($errString)
    {
        $this->_nextErrors[] = $errString;
        Session::getInstance()->set(self::SESSION_NEXT_ERRORS, serialize($this->_nextErrors));
        return false;
    }
    
    public function isAuth()
    {
        return $this->_auth->isLogin();
    }
    
    /**
     * @var PageConstructor
     */
    public function getConstructor()
    {
        return $this->_constructor;
    }
    
    /**
     * @var PagesManager
     */
    public function getPagesManager()
    {
        return $this->_pagesManager;
    }

    /**
     * Возвращает инстанцию интеграции с GitLab.
     * @return GitlabIntegration
     */
    public function gitlab()
    {
        return GitlabIntegration::getInstance($this->getUser());
    }

    /**
     * Возвращает инстанцию менеджера для работы с комментариями.
     * @return CommentsManager
     */
    public function comments()
    {
        if (empty($this->_commentsManager)) {
            $this->_commentsManager = new CommentsManager();
        }

        return $this->_commentsManager;
    }
    
    /**
     * Контроллер кэша.
     * @return CacheController
     */
    public function cache()
    {
        return empty($this->_cache) ? ($this->_cache = new CacheController()) : $this->_cache;
    }
    
    /**
     * @var User
     */
    public function getUser()
    {
        if (!$this->isAuth()) {
            return false;
        }
        if (!$this->_user) {
            $this->_user = User::load($this->_auth->getUserId());
        }
        return $this->_user;
    }

    public function getUserId()
    {
        return $this->isAuth() ? $this->_auth->getUserId() : null;
    }
    
    /**
     * @var LPMAuth
     */
    public function getAuth()
    {
        return $this->_auth;
    }
    
    /**
     * @var LPMParams
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * Возвращает URL путь для текущей страницы
     * @return string
     */
    public function getCurrentUrlPath()
    {
        $args = $this->_params->getArgs();
        $currentUrl = implode("/", array_filter($args));
    
        return $currentUrl;
    }

    /**
     * @return BasePage
     */
    public function getCurrentPage()
    {
        return $this->_curPage;
    }
    
    public function getErrors($clear = true)
    {
        $arr = $this->_errors;
        if ($clear) {
            $this->_errors = [];
        }
        return $arr;
    }
    
    /**
     * @return BasePage
     */
    private function initCurrentPage()
    {
        if ($this->_params->uid == ''
            || !$page = $this->_pagesManager->getPageByUid($this->_params->uid)) {
            $page = $this->_pagesManager->getDefaultPage();
        }
        
        // Если не удалось инициализировать, то перебрасываем на главную
        if (!$res = $page->init()) {
            // Если мы сейчас не авторизованы, а страница требует авторизации -
            // запомним URL для пересылки после авторизации
            if (!$this->isAuth() && $page->needAuth) {
                $redirectPath = $this->getCurrentUrlPath();
                if (!empty($redirectPath)) {
                    $session = Session::getInstance();
                    $session->set(AuthPage::SESSION_REDIRECT, $redirectPath);
                    $og = $page->getOpenGraph();
                    $ogStr = "";
                    if (!empty($og)) {
                        $ogStr = json_encode($og);
                    }
                    $session->set(AuthPage::SESSION_REDIRECT_OG, $ogStr);
                }
            }
            // пересылка на главную
            // т.к. нам надо пересылать данные OG, то нужно обязательно
            // сохранить сессию, но грабберы сайтов (для которых и нужен OG)
            // не поддерживают cookie, поэтому передаем явно
            self::go2URL(null, [LPMParams::QUERY_ARG_SID => session_id()]);
        }

        return $res;
    }

    private function debugOnException(Exception $e, $title = 'Fatal Error')
    {
        if (!LPMGlobals::isDebugMode()) {
            return;
        }

        $errLines =
        [
            '[' . get_class($e) . '] #' . $e->getCode() . ': ' . $e->getMessage(),
            $e->getTraceAsString()
        ];

        $this->addError(implode("\n", $errLines));
        echo '<h1>[DEBUG] ' . $title . '</h1>';
        echo '<pre>';
        var_dump($this->_errors);
        echo '</pre>';
        exit;
    }
}
