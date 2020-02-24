<?php
/**
 * Движок Lightning Project Manager
 * @author GreyMag
 * @version 0.2.2a 
 *
 */
class LightningEngine {
	/**
	 * Поле для хранения URL предыдущей страницы.
	 */
	const SESSION_PREV_PATH = 'lightning_prev_path';
	/**
	 * Сообщения об ошибках, которые надо показать после смены страниы.
	 */
	const SESSION_NEXT_ERRORS = 'lightning_next_errors';

	/**
	 * @return LightningEngine
	 */
	public static function getInstance() {
		return self::$_instance;
	}
	
	public static function go2URL($url = '', $queryArgs = null) {
		if ($url == '')
			$url = SITE_URL;
		if (!empty($queryArgs))
			$url .= '?' . http_build_query($queryArgs);
		header('Location: '. $url . '#');
		exit;
	}
	
	public static function getURL($path, $queryArgs = null) {
		$url = SITE_URL . $path;
		if (!empty($queryArgs))
			$url .= '?' . http_build_query($queryArgs);
		return $url;
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
	private $_contructor;
	/**
	 * @var PagesManager
	 */
	private $_pagesManager;
	/**
	 * @var LPMParams
	 */
	private $_params;
	/**
	 * @var BasePage
	 */
	private $_curPage;
	
	/**
	 * Ошибки, которые надо вывести пользователю
	 * @var array
	 */
	private $_errors = array();
	private $_nextErrors = array();
	
	function __construct()
	{
		if (self::$_instance != '') throw new Exception( __CLASS__ . ' are singleton' );
		self::$_instance = $this;
		$this->_params       = new LPMParams();	
		$this->_auth         = new LPMAuth($this->_params->getQueryArg(LPMParams::QUERY_ARG_SID));	
		$this->_pagesManager = new PagesManager( $this );		
		$this->_contructor   = new PageConstructor( $this->_pagesManager );
	}

	public function createPage() 
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
			$this->_contructor->createPage();
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
	
	public function addError($errString) {
		if (LPMGlobals::isDebugMode()) {
			// В дебаге добавляем ошибку БД в текст, чтобы проще отлаживать	
			$db = LPMGlobals::getInstance()->getDBConnect();		
			if ($db->errno > 0) {
        		$errString .= ' (DB error #' . $db->errno . ': ' . $db->error . ')';
        	}
		}
		$this->_errors[] = $errString;
		return false;
	}
	
	public function addNextError($errString) {
		$this->_nextErrors[] = $errString;
		Session::getInstance()->set(self::SESSION_NEXT_ERRORS, serialize($this->_nextErrors));
		return false;
	}
	
	public function isAuth() {
		return $this->_auth->isLogin();
	}
	
	/**
	 * @var PageConstructor
	 */
	public function getCostructor() {
		return $this->_contructor;
	}
	
	/**
	 * @var PagesManager
	 */
	public function getPagesManager() {
		return $this->_pagesManager;
	}

	/**
	 * Возвращает инстанцию интеграции с GitLab.
	 * @return GitlabIntegration
	 */
	public function gitlab() {
		return GitlabIntegration::getInstance($this->getUser());
	}
	
	/**
	 * @var User
	 */
	public function getUser() {
		if (!$this->isAuth()) return false;
		if (!$this->_user) {
			$this->_user = User::load( $this->_auth->getUserId() );
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
	public function getAuth() {
		return $this->_auth;
	}
	
	/**
	 * @var LPMParams
	 */
	public function getParams() {
		return $this->_params;
	}

	/**
	 * Возвращает URL путь для текущей страницы
	 * @return string
	 */
	public function getCurrentUrlPath() {
		$args = $this->_params->getArgs();
		$currentUrl = implode("/", array_filter($args));
	
		return $currentUrl;
	}

	/**
	 * @return BasePage
	 */
	public function getCurrentPage() {
		return $this->_curPage;
	}
	
	public function getErrors($clear = true) {
		$arr = $this->_errors;
		if ($clear)
			$this->_errors = [];
		return $arr;
	}
	
	/**
	 * @return BasePage
	 */
	private function initCurrentPage() {
		if ($this->_params->uid == '' 
			|| !$page = $this->_pagesManager->getPageByUid( $this->_params->uid )) 
				$page = $this->_pagesManager->getDefaultPage();	
		
		// Если не удалось инициализировать, то перебрасываем на главную
		if (!$res = $page->init())
		{
			// Если мы сейчас не авторизованы, а страница требует авторизации - 
			// запомним URL для пересылки после авторизации
			if (!$this->isAuth() && $page->needAuth)
			{
				$redirectPath = $this->getCurrentUrlPath();
				if (!empty($redirectPath)) {
					$session = Session::getInstance();
					$session->set(AuthPage::SESSION_REDIRECT, $redirectPath);
					$og = $page->getOpenGraph();
					$ogStr = "";
					if (!empty($og))
						$ogStr = json_encode($og);
					$session->set(AuthPage::SESSION_REDIRECT_OG, $ogStr);
				}
			}
			// пересылка на главную 
			// т.к. нам надо пересылать данные OG, то нужно обязательно
			// сохранить сессиию, но грабберы сайтов (для которых и нужен OG)
			// не поддерживают cookie, поэтому передаем явно
			self::go2URL(null, [LPMParams::QUERY_ARG_SID => session_id()]);
		} 

		return $res;
	}

	private function debugOnException(Exception $e, $title = 'Fatal Error') {
		if (!LPMGlobals::isDebugMode())
			return;

		$this->addError(
			'[' . get_class($e) . '] #' . $e->getCode() . ': ' . $e->getMessage() . "\n" . 
			$e->getTraceAsString() . "\n");
		echo '<h1>[DEBUG] ' . $title . '</h1>';
		echo '<pre>';
		var_dump($this->_errors);
		echo '</pre>';
		exit;
	}
}
?>