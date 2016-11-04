<?php
class PagePrinter {
	public static function title() {
		echo self::getPC()->getTitle();
	}
	
	public static function header() {
		echo self::getPC()->getHeader();
	}
	
	public static function siteTitle() {
		echo LPMOptions::getInstance()->title;
	}
	
	public static function siteSubTitle() {
		echo LPMOptions::getInstance()->subtitle;
	}
	
	public static function logoImg() {
		if (LPMOptions::getInstance()->logo != '') {
			echo '<img src="' . LPMOptions::getInstance()->logo . '" ' . 
					  'title="' . LPMOptions::getInstance()->title .'" ' .
					  'alt="' . LPMOptions::getInstance()->title .'"/>';
		}		
	}
	
	public static function version() {
		echo VERSION;
	}
	
	public static function copyrights() {
		echo '<a href="' . LPMBase::AUTHOR_SITE . '" target="_blank">' . LPMBase::AUTHOR . '</a> &copy; 20' . COPY_YEAR;
		$nowYear = DateTimeUtils::date( DateTimeFormat::YEAR_NUMBER_2_DIGITS );
		if ($nowYear > COPY_YEAR) echo '-' . $nowYear;
	}
	
	public static function productName() {
		echo LPMBase::PRODUCT_NAME;
	}
	
	public static function cssLinks() {
		/*$args = func_get_args();
		$str = '';
		foreach ($args as $file) {
			$str .= elf::cssLink( $file ) . "\n";
		}
		return $str;*/
		self::cssLink( 'main' );
		self::cssLink( 'jquery-ui-1.8.16' );
	}
	
	public static function errors() {
		echo implode( ', ', LightningEngine::getInstance()->getErrors() );
	}
	
	public static function issues($list) {
		PageConstructor::includePattern( 'issues', compact('list'));
	}
	
	public static function issueForm() {
		PageConstructor::includePattern( 'issue-form' );
	}
	
	public static function issueView() {
		PageConstructor::includePattern( 'issue' );
	}
	
	public static function usersList() {
		PageConstructor::includePattern( 'users-list' );
	}
	
	public static function usersChooser() {
		PageConstructor::includePattern( 'users-chooser' );
	}
	
	/*public static function mainCSSLink() {
		self::cssLink( 'main' );
	}*/
	
	public static function jsScripts() {
		$scripts = PageConstructor::getUsingScripts();
		foreach ($scripts as $scriptFileName) {
			self::jsScriptLink( $scriptFileName );
		}
	}
	
	public static function pageContent() {
		LightningEngine::getInstance()->getCurrentPage()->printContent();
	}
	
	public static function postVar($var, $default = '') {
		echo isset( $_POST[$var] ) ? $_POST[$var] : $default;
	}
	
	private static function jsScriptLink( $file ) {
		echo '<script type="text/javascript" src="' .
			 self::getPC()->getJSLink( $file ) .  
			 '"></script>';
	}
	
	private static function cssLink( $file ) {
		echo '<link rel="stylesheet" href="' . 
			 self::getPC()->getCSSLink( $file ) . 
			 '" type="text/css">';
	}
	
	/**
	 * @return PageConstructor
	 */
	private static function getPC() {
		return LightningEngine::getInstance()->getCostructor();
	}
}
?>