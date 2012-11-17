<?php
class PageConstructor
{		
	public static function getSiteURL() {
		return SITE_URL;
	}
	
	public static function getUsingScripts() {
		return self::$_usingScripts;
	}
	
	public static function getMainMenu() {
		return LightningEngine::getInstance()->getPagesManager()->getLinks4Menu();
	}
	
	public static function getSubMenu() {
		return LightningEngine::getInstance()->getPagesManager()->getLinks4SubMenu();
	}
	
	public static function getUserMenu() {
		return LightningEngine::getInstance()->getPagesManager()->getLinks4UserMenu();
	}
	
	public static function getBasePageURL() {
		return LightningEngine::getInstance()->getCurrentPage()->getBaseUrl();
	}
	
	public static function getProjectsList() {
		return Project::getAvailList();
	}
	
	public static function getIssuesList() {
		return Issue::getCurrentList();
	}
	
	public static function getComments() {
		return Comment::getCurrentList();
	}
	
	public static function getUsersList() {
		return User::loadList( '' );
	}
	
	public static function getUsersChooseList() {
		return User::loadList( '' );
	}
	
	public static function getDateLinks() {
		// TODO сделать что-нибудь с этим!!
		return LightningEngine::getInstance()->getCurrentPage()->getDateLinks();
	}
	
	public static function getWeekLinks() {
		// TODO сделать что-нибудь с этим!!
		return LightningEngine::getInstance()->getCurrentPage()->getWeekLinks();
	}
	
	public static function getWeekDates() {
		// TODO сделать что-нибудь с этим!!
		return LightningEngine::getInstance()->getCurrentPage()->getWeekDays();
	}
	
	public static function getWeekStat() {
		// TODO сделать что-нибудь с этим!!
		return LightningEngine::getInstance()->getCurrentPage()->getStat();
	}
	
	public static function getAddWorkerList() {
		// TODO сделать что-нибудь с этим!!
		//if (!WorkStudyPage::isCurrent()) return array();
		return LightningEngine::getInstance()->getCurrentPage()->getAddWorkerList();
	}
	
	public static function getProject() {
		return Project::$currentProject;
	}
	
	public static function getProjectMembers() {
		return ( Project::$currentProject != null ) 
				? Project::$currentProject->getMembers() : array();
	}
	
	public static function getIssue() {
		return Issue::$currentIssue ? Issue::$currentIssue : new Issue();
	}
	
	public static function getWorkersList() {
		//if (!WorkStudyPage::isCurrent()) return array();		
		return LightningEngine::getInstance()->getCurrentPage()->getWorkers();
	}
	
	public static function getDefaultDate() {
		/* return DateTimeUtils::date(
			 DateTimeFormat::DAY_OF_MONTH_2 . '/' .
			 DateTimeFormat::MONTH_NUMBER_2_DIGITS . '/' . 
			 DateTimeFormat::YEAR_NUMBER_4_DIGITS
		);	 */

		return LPMBaseObject::getDate4Input( DateTimeUtils::$currentDate );
	}
	
	public static function canCreateProject() {
		if (!$user = LightningEngine::getInstance()->getUser()) return false;
		return $user->canCreateProject(); 
	} 
	
	public static function isAuth() {
		return LightningEngine::getInstance()->isAuth();
	}
		
	public static function getUser() {
		return LightningEngine::getInstance()->getUser();
	}
	
	/*public static function includeCSS( $name ) {
		include self::$_instance->getThemeDir() . 'css/' . $name . '.css';
	}*/
	
	public static function includePattern( $name ) {
		include LightningEngine::getInstance()->getCostructor()->getThemePath() . $name . '.html';
	}
	
	private static $_usingScripts = array( 
		'libs/jquery-1.6.4.min',
		'libs/jquery-ui-1.8.16.min',
		'libs/jquery.form',
		'libs/jquery.validate.min',
		'libs/F2PInvoker', 
		'lightning' 
	);
	
	
	public  $_title    = '';
	public  $_header   = '';
	private $_themeDir = '';
	
	/**
	 * @var PagesManager
	 */
	private $_pagesManager;
	
	function __construct( PagesManager $pagesManager ) {		
		$this->_themeDir = THEMES_DIR . LPMOptions::getInstance()->currentTheme . '/';
		
		$this->_pagesManager = $pagesManager;
	}
	
	public function createPage() {		
		$page = LightningEngine::getInstance()->getCurrentPage();
		$this->_title  = $page->getTitle();
		$this->_header = $page->getHeader();
		self::$_usingScripts = array_merge( self::$_usingScripts, $page->getJS() );
		self::includePattern( 'page' );
	}
	
	public function getTitle() {
		return $this->_title . ' :: ' . LPMOptions::getInstance()->title;
	}
	
	public function getHeader() {
		return $this->_header;
	}
	
	public function getCSSLink( $file ) {
		return $this->getThemeUrl() . 'css/' . $file . '.css';
	}
	
	public function getJSLink( $file ) {
		return SITE_URL . SCRIPTS_DIR . $file . '.js';
	}
	
	public function getThemePath()
	{
		return ROOT . $this->_themeDir;
	}
	
	public function getThemeUrl()
	{
		return SITE_URL . $this->_themeDir;
	}
}
?>