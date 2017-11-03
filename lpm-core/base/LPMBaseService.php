<?php
class LPMBaseService extends SecureService
{
	/**
	 * @var LPMAuth
	 */
	protected $_auth;
	protected $_user;

	private $_dbError;
	
	function __construct()
	{
		parent::__construct();
	}
	
	public function beforeFilter( $calledFunc )
	{
		if (in_array( $calledFunc, $this->_allowMethods )) return true;
	
		$this->_auth = new LPMAuth();
	
		return $this->_auth->isLogin();
	}
	
	protected function errorDBSave() {
		return $this->error('Error DB save');
	}
	
	protected function errorDBLoad() {
		return $this->error('Error DB load');
	}

	protected function error($message, $code = 0) {
		if (DEBUG) {
			if ($this->_db && $this->_db->error) {
				if (empty($message)) 
					$message = 'DB error';
				$message .= ' (#' . $this->_db->errno . ': ' . $this->_db->error . ')';
			} else if ($this->_dbError) {
				if (empty($message)) 
					$message = 'DB error';
				$message .= ' (' . $this->_dbError . ')';
			}
		}
		return parent::error($message, $code);
	}

	protected function exception($e) {
		if (DEBUG && $e instanceof DBException) {
			$db = $e->getDB();
			if ($db && $db->error)
				$this->_dbError = '#' . $db->errno . ': ' . $db->error;
		}
		return parent::exception($e);
	}
	
	protected function floatArr( $arr, $justPositive = true ) {
		if (!is_array( $arr )) return false;
		$newArr = array();
		foreach ($arr as $item) {
			$item = (float)$item;
			if ($justPositive && $item <= 0) continue;
			if (!in_array( $item, $newArr)) array_push( $newArr, $item );
		}
		return $newArr;
	}
	
	protected function getUser() {
		if (!$this->_auth->isLogin()) return false;
		if (!$this->_user) {
			$this->_user = User::load( $this->_auth->getUserId() );
		}
		return $this->_user;
	}
	
	protected function checkRole( $reqRole ) {
		if (!$user = $this->getUser()) return false;
		return $user->checkRole( $reqRole );
	}
	
	protected function addComment( $instanceType, $instanceId, $text ) {
		if (!$user = $this->getUser()) return false;
		
		$text = trim( $text );
		if ($text == '') {
			$this->error( 'Недопустимый текст' );
			return false;
		}

		$text = $this->_db->real_escape_string($text);
		$text = str_replace( '%', '%%', $text );
		//$text = $this->_db->escape_string_t( $text ); // там баг
		
		$sql = "insert into `%s` (`instanceId`, `instanceType`, `authorId`, `date`, `text` ) " .
		         "values ( '" . $instanceId . "', '" . $instanceType . "', " . 
		         		   "'" . $user->userId . "', '" . DateTimeUtils::mysqlDate() . "', " .
		         		   "'" . $text . "' )";
		if (!$this->_db->queryt( $sql, LPMTables::COMMENTS )) return false;
		
		return Comment::load( $this->_db->insert_id );
	}
}
?>