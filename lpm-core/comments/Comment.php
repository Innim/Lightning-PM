<?php
class Comment extends LPMBaseObject {
	private static $_tags = ['b', 'i', 'u', 'code'];

	private static $_listByInstance = array();
	private static $_curIType = -1;
	private static $_curIId   = -1;
	
	protected static function loadList($where) {
		$sql = "select * from `%1\$s`, `%2\$s` where `%1\$s`.`deleted` = '0'";
		if ($where != '') $sql  .= " and " . $where;
		$sql .= " and `%1\$s`.`authorId` = `%2\$s`.`userId` ".
					"order by `%1\$s`.`date` desc";	
		//GMLog::writeLog($sql);	
		return StreamObject::loadObjList(
				self::getDB(), 
				[$sql, LPMTables::COMMENTS, LPMTables::USERS], 
				__CLASS__ 
		);
	}
	
	public static function setCurrentInstance($curIType, $curIId) {
		self::$_curIType = $curIType;
		self::$_curIId   = $curIId;
	}

	public static function getIssuesListByProject($projectId, $from = 0, $limit = 0) {
		$instanceType = LPMInstanceTypes::ISSUE;
		$limitStr = $limit > 0 ? 'LIMIT ' . $from . ',' . $limit : '';

		$sql = <<<SQL
			SELECT `c`.*, `u`.* 
			  FROM `%1\$s` `c`, `%2\$s` `u`, `%3\$s` `p`, `%4\$s` `i`
			 WHERE `c`.`deleted` = 0 AND `c`.`instanceType` = {$instanceType} 
			   AND `c`.`authorId` = `u`.`userId` AND `i`.`id` = `c`.`instanceId`
			   AND `i`.`projectId` = `p`.`id` AND `p`.`id` = {$projectId}
		  ORDER BY `c`.`date` DESC
			{$limitStr}
SQL;
		return StreamObject::loadObjList(self::getDB(), array($sql, LPMTables::COMMENTS, 
				LPMTables::USERS, LPMTables::PROJECTS, LPMTables::ISSUES), __CLASS__);
	}
	
	public static function getListByInstance($instanceType, $instanceId = null) {
		$list = null;

		if (isset(self::$_listByInstance[$instanceType]) && $instanceId !== null && 
				isset(self::$_listByInstance[$instanceType][$instanceId]))
			$list = self::$_listByInstance[$instanceType][$instanceId];

		if ($list === null) {
			if (LightningEngine::getInstance()->isAuth()) {
				$where = '`%1$s`.`instanceType` = ' . $instanceType;
				if ($instanceId !== null)
					$where .= ' AND `%1$s`.`instanceId` = ' . $instanceId;
				$list = self::loadList($where);

				if ($instanceId !== null) {				
					if (!isset(self::$_listByInstance[$instanceType])) 
						self::$_listByInstance[$instanceType] = array();
					self::$_listByInstance[$instanceType][$instanceId] = $list;
				}
			} else {
				$list = [];
			}
		}

		return $list;
	}

	public static function add($instanceType, $instanceId, $userId, $text) {
        $db = LPMGlobals::getInstance()->getDBConnect();

        $text = $db->real_escape_string($text);
        $text = str_replace('%', '%%', $text);

        $sql = "insert into `%s` (`instanceId`, `instanceType`, `authorId`, `date`, `text` ) " .
            "values ( '" . $instanceId . "', '" . $instanceType . "', " .
            "'" . $userId . "', '" . DateTimeUtils::mysqlDate() . "', " .
            "'" . $text . "' )";

        if (!$db->queryt($sql, LPMTables::COMMENTS))
            return false;

        return self::load($db->insert_id);
    }
	
	/**
	 * 
	 * @param float $id
	 * @return Comment
	 */
	public static function load($id) {
		return StreamObject::singleLoad($id, __CLASS__, '', '%1$s`.`id');
	}
	
	public static function getCurrentList() {
		return self::$_curIType > 0 && self::$_curIId > 0 ?
			self::getListByInstance(self::$_curIType, self::$_curIId) : array();
	}

    public static function setTimeToDeleteComment($comment, $time) {
        setcookie('comment' . $comment->id, $comment->id, time()+$time, '/');
    }

    public static function checkDeleteCommentById ($id) {
        return $_COOKIE['comment'.$id];
    }

    public static function remove(User $user, Comment $comment) {
        $db = self::getDB();
        $sql = "UPDATE `%s` SET `deleted` = 1 WHERE `id` = '$comment->id'";
        if (!$db->queryt($sql, LPMTables::COMMENTS))
	    	throw new Exception('Remove comment failed', \GMFramework\ErrorCode::SAVE_DATA);

        self::setTimeToDeleteComment($comment, 0);
		
	    // Записываем лог
	    UserLogEntry::create($user->userId, DateTimeUtils::$currentDate,
	    	UserLogEntryType::DELETE_COMMENT, $comment->id);
    }
	
	public $id           = 0;
	public $instanceId   = 0;
	public $instanceType = 0;
	public $authorId     = 0;
	public $date         = 0;
	public $text         = '';
	public $dateLabel    = '';


	private $_htmlText;

	
	/**
	 * 
	 * @var User
	 */
	public $author;
	/**
	 * Задача, если комментарий оставлен к ней 
	 * (не озябательно может быть загружен)
	 * @var Issue
	 */
	public $issue;
	
	function __construct() {
		parent::__construct();
	
		//$this->id = 0;
	
		$this->_typeConverter->addFloatVars('id', 'instanceId', 'instanceType', 'authorId');
		$this->addDateTimeFields('date');
	
		$this->author = new User();
	}

	/**
	 * Возвращает URL комментария на странице задачи.
	 * @param  Issue  $issue Задача, к которой оставлен комментарий.
	 * @return string        URL комментария на странице задачи.
	 */
	public function getIssueCommentUrl(Issue $issue) {
		return $issue->getConstURL() . '#comment-' . $this->id;
	}

	/**
	 * Возвращает обработанный текст, который можно выводить на html странице.
	 * @return string
	 */
	public function getText() {
		if (empty($this->_htmlText)) {
			$value = htmlspecialchars($this->text);
			// Для совместимости, чтобы старые комменты не поплыли
			$value = $this->proceedBBCode($this->text);

			$value = HTMLHelper::codeIt($value, false);
			$value = HTMLHelper::formatIt($value, false);

			$this->_htmlText = $value;
		}
		
		return $this->_htmlText;
	}

	/**
	 * @see ParseTextHelper::parseVideoLinks()
	 */
    public function getVideoLinks() {
        return ParseTextHelper::parseVideoLinks($this->getText());
    }

	/**
	 * Возвращает текст без тегов.
	 *
	 * Вырезает все теги подсветки.
	 * @return string
	 */
	public function getCleanText() {
		$value = $this->text;

		$replaceTags = [];
		foreach (self::$_tags as $tag) {
			$replaceTags[] = '[' . $tag . ']';
			$replaceTags[] = '[/' . $tag . ']';
		}

		$value = str_replace($replaceTags, '', $value);
		return $value;
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
	
	public function loadStream($hash) {
		return parent::loadStream($hash) && $this->author->loadStream($hash);
	}
	
	protected function setVar($var, $value) {
		switch ($var) {
			case 'date' : {
				if (!parent::setVar($var, $value))
					return false;
				$this->dateLabel = self::getDateTimeStr($this->date);
				return true;
			} break;
		}
		
		return parent::setVar($var, $value);
	}

	protected function clientObjectCreated($obj) {
		$obj->text = $this->getText();
		return $obj;
	}

	private function proceedBBCode($value) {
		$tags = implode('|', self::$_tags);
		$value = preg_replace( 
			array( 
				//"/(^|[\n ])([\w]*?)((www|ftp)\.[^ \,\"\t\n\r<]*)/is",
				//"/(^|[\n ])([\w]*?)((ht|f)tp(s)?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is",
				"/(https?:\/\/[^<\s]+[[:alnum:]])([^[:alnum:]]*(?:<br ?\/?>)*[^a-zа-я0-9]|\s|$)/iu",
				"/((?:\n\r)|(?:\r\n)|\n|\r){1}/",
				"/\[(" . $tags . ")\](.*?)\[\/\\1\]/",
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

		return $value;
	}
}