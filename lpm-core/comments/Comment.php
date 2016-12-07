<?php
class Comment extends LPMBaseObject
{
	private static $_listByInstance = array();
	private static $_curIType = -1;
	private static $_curIId   = -1;
	
	protected static function loadList( $where ) {
		$sql = "select * from `%1\$s`, `%2\$s` where `%1\$s`.`deleted` = '0'";
		if ($where != '') $sql  .= " and " . $where;
		$sql .= " and `%1\$s`.`authorId` = `%2\$s`.`userId` ".
					"order by `%1\$s`.`date` desc";	
		GMLog::writeLog( $sql );	
		return StreamObject::loadObjList(
				self::getDB(), 
				array( $sql, LPMTables::COMMENTS, LPMTables::USERS ), 
				__CLASS__ 
		);
	}
	
	public static function setCurrentInstance( $curIType, $curIId ) {
		self::$_curIType = $curIType;
		self::$_curIId   = $curIId;
	}
	
	public static function getListByInstance( $instanceType, $instanceId ) {
		if (!isset( self::$_listByInstance[$instanceType], 
					self::$_listByInstance[$instanceType][$instanceId] )) {
			if (!isset( self::$_listByInstance[$instanceType] )) 
				self::$_listByInstance[$instanceType] = array();
			if (LightningEngine::getInstance()->isAuth()) {
				self::$_listByInstance[$instanceType][$instanceId] = self::loadList( 
					"`%1\$s`.`instanceId` = '" . $instanceId . "' and " .
					"`%1\$s`.`instanceType` = '" . $instanceType . "'" 
				);
			} else self::$_listByInstance[$instanceType][$instanceId] = array();
		}
	
		return self::$_listByInstance[$instanceType][$instanceId];
	}
	
	/**
	 * 
	 * @param float $id
	 * @return Comment
	 */
	public static function load( $id ) {
		return StreamObject::singleLoad( $id, __CLASS__, '', '%1$s`.`id' );
	}
	
	public static function getCurrentList() {
		return self::$_curIType > 0 && self::$_curIId > 0
		? self::getListByInstance( self::$_curIType, self::$_curIId )
		: array();
	}		
	
	public $id           = 0;
	public $instanceId   = 0;
	public $instanceType = 0;
	public $authorId     = 0;
	public $date         = 0;
	public $text         = '';
	public $dateLabel    = '';
	
	/**
	 * 
	 * @var User
	 */
	public $author;
	
	function __construct()
	{
		parent::__construct();
	
		//$this->id = 0;
	
		$this->_typeConverter->addFloatVars( 'id', 'instanceId', 'instanceType', 'authorId' );
		$this->addDateTimeFields( 'date' );
	
		$this->author = new User();
	}

	public function getText() {
		return $this->text;
	}
	
	public function getAuthorLinkedName() {
		return $this->author->getLinkedName();
	}
	
	public function getAuthorAvatarUrl() {
		return $this->author->getAvatarUrl();
	}
	
	public function getDate() {
		return $this->dateLabel;//parent::getDateTime( $this->date );
	}
	
	public function loadStream( $hash ) {
		return parent::loadStream( $hash ) && $this->author->loadStream( $hash );
	}
	
	protected function setVar( $var, $value ) {
		switch ($var) {
			case 'text' : {
				$value = htmlspecialchars( $value );

				$value = preg_replace( 
					array( 
						//"/(^|[\n ])([\w]*?)((www|ftp)\.[^ \,\"\t\n\r<]*)/is",
						//"/(^|[\n ])([\w]*?)((ht|f)tp(s)?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is",
						"/(https?:\/\/[^<\s]+[[:alnum:]])([^[:alnum:]]*(?:<br ?\/?>)*[^a-zа-я0-9]|\s|$)/iu",
						"/((?:\n\r)|(?:\r\n)|\n|\r){1}/",
						"/\[(b|i|u|code)\](.*?)\[\/\\1\]/",
					), 
				    array(  
				    	//"$1$2<a href=\"http://$3\" >$3</a>",
				    	//"$1$2<a href=\"$3\" >$3</a>",
				    	'<a href="$1">$1</a>$2',
				    	"<br />",
				    	"<$1>$2</$1>" 
				    ),
					$value 
				);
			} break;
			case 'date' : {
				if (!parent::setVar( $var, $value )) return false;
				$this->dateLabel = self::getDateTimeStr( $this->date );
				return true;
			} break;
		}
		
		return parent::setVar( $var, $value );
	}
}

?>