<?php
class Link {
	public static function getUrlByUid( $uid, $_subuids = '' ) {
		/*$url = SITE_URL . '?uid=' . $uid;
		if ($subuid     != '') $url .= '&suid=' . $subuid;
		if ($subpageuid != '') $url .= '&puid=' . $subpageuid;*/
		$args = func_get_args();
		$url = SITE_URL . implode( '/', $args );
		
		return $url;
	}
	
	public $href;
	public $label;
	
	private $_reqRole;
	private $_isCurrent = false;
	
	function __construct( $label = '', $href = '', $reqRole = -1 )
	{
		$this->href     = $href;
		$this->label    = $label;
		$this->_reqRole = $reqRole;
	}
	
	public function setCurrent( $value = true ) {
		$this->_isCurrent = $value;
	}
	
	public function isCurrent() {
		return $this->_isCurrent;
	} 
	
	public function checkRole() {
		if ($this->_reqRole == -1) return true;
		if (!$user = LightningEngine::getInstance()->getUser()) return false;
		
		return $user->checkRole( $this->_reqRole );
	}
}
?>