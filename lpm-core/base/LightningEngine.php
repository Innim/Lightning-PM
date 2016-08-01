<?php
/**
 * Движок Lightning Project Manager
 * @author GreyMag
 * @version 0.2.2a 
 *
 */
class LightningEngine
{
	/**
	 * @return LightningEngine
	 */
	public static function getInstance() {
		return self::$_instance;
	}
	
	public static function go2URL( $url = '' ) {
		if ($url == '') $url = SITE_URL;
		header( 'Location: '. $url  );
		exit;
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
	
	function __construct()
	{
		if (self::$_instance != '') throw new Exception( __CLASS__ . ' are singleton' );
		self::$_instance = $this;
		$this->_auth         = new LPMAuth();	
		$this->_params       = new LPMParams();	
		$this->_pagesManager = new PagesManager( $this );		
		$this->_contructor   = new PageConstructor( $this->_pagesManager );
	}

	public function createPage() {
		$this->setRedirectUrl();

		$this->_curPage = $this->initCurrentPage();
		
		$this->_contructor->createPage();
	}
	
	public function addError( $errString ) {
		array_push( $this->_errors, $errString );
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

	public function getCurrentUrl() {
		$args = $this->_params->getArgs();
		$currentUrl = implode("/",array_filter($args));
	
		return $currentUrl;
	}

	public function setRedirectUrl() {
		$redirectUrl = $this->getCurrentUrl();

		if(!$this->isAuth() && $_SESSION["redirect"] != $redirectUrl && $redirectUrl != '')
			$_SESSION["redirect"] = $redirectUrl;
	}

	/**
	 * @return BasePage
	 */
	public function getCurrentPage() {
		return $this->_curPage;
	}
	
	public function getErrors() {
		return $this->_errors;
	}
	
	/**
	 * @return BasePage
	 */
	private function initCurrentPage() {
		if ($this->_params->uid == '' 
			|| !$page = $this->_pagesManager->getPageByUid( $this->_params->uid )) 
				$page = $this->_pagesManager->getDefaultPage();	
		
		if (!$page = $page->init()) self::go2URL();
		return $page;
	}
}
?>