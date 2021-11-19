<?php
/**
 * Комментарий.
 */
class Comment extends LPMBaseObject
{
    protected static function loadList($where)
    {
        $sql = "select * from `%1\$s`, `%2\$s` where `%1\$s`.`deleted` = '0'";
        if ($where != '') {
            $sql  .= " and " . $where;
        }
        $sql .= " and `%1\$s`.`authorId` = `%2\$s`.`userId` ".
                    "order by `%1\$s`.`date` desc";
        //GMLog::writeLog($sql);
        return StreamObject::loadObjList(
            self::getDB(),
            [$sql, LPMTables::COMMENTS, LPMTables::USERS],
            __CLASS__
        );
    }

    public static function getIssuesListByProject($projectId, $from = 0, $limit = 0)
    {
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

    public static function getListByInstance($instanceType, $instanceId = null)
    {
        $where = '`%1$s`.`instanceType` = ' . $instanceType;
        if ($instanceId !== null) {
            $where .= ' AND `%1$s`.`instanceId` = ' . $instanceId;
        }

        return self::loadList($where);
    }
    
    /**
     * @return Comment
     */
    public static function add($instanceType, $instanceId, $userId, $text)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();

        $text = $db->real_escape_string($text);
        $text = str_replace('%', '%%', $text);

        $sql = "insert into `%s` (`instanceId`, `instanceType`, `authorId`, `date`, `text` ) " .
            "values ( '" . $instanceId . "', '" . $instanceType . "', " .
            "'" . $userId . "', '" . DateTimeUtils::mysqlDate() . "', " .
            "'" . $text . "' )";

        if (!$db->queryt($sql, LPMTables::COMMENTS)) {
            return false;
        }

        return self::load($db->insert_id);
    }
    
    /**
     *
     * @param float $id
     * @return Comment
     */
    public static function load($id)
    {
        return StreamObject::singleLoad($id, __CLASS__, '', '%1$s`.`id');
    }

    public static function setTimeToDeleteComment($comment, $time)
    {
        $name = 'comment' . $comment->id;
        $value = $comment->id . '';

        $_COOKIE[$name] = $value;
        setcookie($name, $value, time() + $time, '/');
    }

    public static function checkDeleteCommentById($id)
    {
        return !empty($_COOKIE['comment' . $id]);
    }

    public static function remove(User $user, Comment $comment)
    {
        $db = self::getDB();
        $sql = "UPDATE `%s` SET `deleted` = 1 WHERE `id` = '$comment->id'";
        if (!$db->queryt($sql, LPMTables::COMMENTS)) {
            throw new Exception('Remove comment failed', \GMFramework\ErrorCode::SAVE_DATA);
        }

        self::setTimeToDeleteComment($comment, 0);
        
        // Записываем лог
        UserLogEntry::create(
            $user->userId,
            DateTimeUtils::$currentDate,
            UserLogEntryType::DELETE_COMMENT,
            $comment->id
        );
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
     * (не обязательно может быть загружен)
     * @var Issue
     */
    public $issue;

    private $_mergeRequests;
    
    public function __construct()
    {
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
    public function getIssueCommentUrl(Issue $issue)
    {
        return $issue->getConstURL() . '#comment-' . $this->id;
    }

    /**
     * Возвращает обработанный текст, который можно выводить на html странице.
     * @return string
     */
    public function getText()
    {
        if (empty($this->_htmlText)) {
            $this->_htmlText = HTMLHelper::htmlTextForComment($this->text);
        }

        return $this->_htmlText;
    }

    /**
     * @see ParseTextHelper::parseVideoLinks()
     */
    public function getVideoLinks()
    {
        return ParseTextHelper::parseVideoLinks($this->getText());
    }

    /**
     * @see ParseTextHelper::findMergeRequests()
     */
    public function getMergeRequests()
    {
        if ($this->_mergeRequests === null) {
            $this->_mergeRequests = ParseTextHelper::findMergeRequests($this->text);
        }
        return $this->_mergeRequests;
    }

    /**
     * Возвращает текст без тегов.
     *
     * Вырезает все теги подсветки.
     * @return string
     */
    public function getCleanText()
    {
        $value = $this->text;

        $replaceTags = [];
        foreach (HTMLHelper::$bbTags as $tag) {
            $replaceTags[] = '[' . $tag . ']';
            $replaceTags[] = '[/' . $tag . ']';
        }

        $value = str_replace($replaceTags, '', $value);
        return $value;
    }
    
    public function getAuthorLinkedName()
    {
        return $this->author->getLinkedName();
    }
    
    public function getAuthorAvatarUrl()
    {
        return $this->author->getAvatarUrl();
    }
    
    public function getDate()
    {
        return $this->dateLabel;//parent::getDateTime( $this->date );
    }
    
    public function loadStream($hash)
    {
        return parent::loadStream($hash) && $this->author->loadStream($hash);
    }
    
    protected function setVar($var, $value)
    {
        switch ($var) {
            case 'date': {
                if (!parent::setVar($var, $value)) {
                    return false;
                }
                $this->dateLabel = self::getDateTimeStr($this->date);
                return true;
            } break;
        }
        
        return parent::setVar($var, $value);
    }

    protected function clientObjectCreated($obj)
    {
        $obj->text = $this->getText();
        return $obj;
    }
}
