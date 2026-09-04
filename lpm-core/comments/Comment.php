<?php
/**
 * Комментарий.
 */
class Comment extends LPMBaseObject
{
    const MAX_FILES_COUNT = 20;

    private const ISSUE_COMMENT_PREFIX = 'ic_';

    protected static function loadList($where)
    {
        $ic = self::ISSUE_COMMENT_PREFIX;

        $whereSql = empty($where) ? '' : ' AND (' . $where . ')';
        $sql = <<<SQL
    SELECT `c`.*, `u`.*, 
           `ic`.`commentId` `{$ic}commentId`, `ic`.`type` `{$ic}type`, `ic`.`data` `{$ic}data`
      FROM `%1\$s` `c`
INNER JOIN `%2\$s` `u` 
        ON `c`.`authorId` = `u`.`userId`
 LEFT JOIN `%3\$s` `ic`
        ON `c`.`id` = `ic`.`commentId`
     WHERE `c`.`deleted` = '0'
           $whereSql
  ORDER BY `c`.`date` DESC
SQL;

        $comments = StreamObject::loadObjList(
            self::getDB(),
            [$sql, LPMTables::COMMENTS, LPMTables::USERS, LPMTables::ISSUE_COMMENT],
            __CLASS__
        );

        self::loadFilesForComments($comments);
        return $comments;
    }

    /**
     * Возвращает комментарии к задачам проекта, начиная с самых свежих.
     * Комментарии удалённых задач в выборку не попадают: до самой задачи
     * добраться уже нельзя, а её вложения удалены вместе с ней.
     * @param int $projectId
     * @param int $from Смещение от начала выборки.
     * @param int $limit Ограничение на кол-во комментариев; 0 — без ограничения.
     * @return Comment[]
     */
    public static function getIssuesListByProject($projectId, $from = 0, $limit = 0)
    {
        $instanceType = LPMInstanceTypes::ISSUE;
        $limitStr = $limit > 0 ? 'LIMIT ' . $from . ',' . $limit : '';

        $sql = <<<SQL
			SELECT `c`.*, `u`.* 
			  FROM `%1\$s` `c`, `%2\$s` `u`, `%3\$s` `p`, `%4\$s` `i`
			 WHERE `c`.`deleted` = 0 AND `c`.`instanceType` = {$instanceType} 
			   AND `c`.`authorId` = `u`.`userId` AND `i`.`id` = `c`.`instanceId`
			   AND `i`.`deleted` = 0
			   AND `i`.`projectId` = `p`.`id` AND `p`.`id` = {$projectId}
		  ORDER BY `c`.`date` DESC
			{$limitStr}
SQL;
        $comments = StreamObject::loadObjList(self::getDB(), array($sql, LPMTables::COMMENTS,
                LPMTables::USERS, LPMTables::PROJECTS, LPMTables::ISSUES), __CLASS__);
        self::loadFilesForComments($comments);
        return $comments;
    }

    public static function getListByInstance($instanceType, $instanceId = null)
    {
        $where = '`c`.`instanceType` = ' . $instanceType;
        if ($instanceId !== null) {
            $where .= ' AND `c`.`instanceId` = ' . $instanceId;
        }

        return self::loadList($where);
    }

    /**
     * Возвращает идентификаторы всех комментариев сущности,
     * включая уже удалённые.
     * @param int $instanceType Одна из констант {@see LPMInstanceTypes}.
     * @param int $instanceId
     * @return int[]
     * @throws \GMFramework\ProviderLoadException
     */
    public static function loadIdsByInstance($instanceType, $instanceId)
    {
        $res = self::buildAndExecute([
            'SELECT' => 'id',
            'FROM'   => LPMTables::COMMENTS,
            'WHERE'  => [
                'instanceType' => (int)$instanceType,
                'instanceId'   => (int)$instanceId,
            ],
        ]);

        if (!$res) {
            throw new \GMFramework\ProviderLoadException();
        }

        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }

        return $ids;
    }

    /**
     * Валидирует и нормализует текст комментария.
     * @param string $text Исходный текст.
     * @param bool $allowEmpty Разрешён ли пустой текст.
     * @return string Текст без крайних пробелов.
     * @throws Exception Если текст пуст и это не разрешено.
     */
    public static function normalizeText($text, $allowEmpty = false)
    {
        $text = trim($text);
        if ($text === '' && !$allowEmpty) {
            throw new Exception('Недопустимый текст');
        }

        return $text;
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
        return StreamObject::singleLoad($id, __CLASS__, '', 'c`.`id');
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

    /**
     * Сохраняет новый текст комментария и отмечает правку.
     *
     * Прежний текст остаётся в истории ({@see CommentTextSnapshot}); сбой
     * записи истории не мешает сохранить правку.
     *
     * @param Comment $comment Комментарий; при успехе обновляется на месте.
     * @param string $text Новый текст, уже прошедший {@see normalizeText()}.
     * @param int $editorId Пользователь, вносящий правку.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить текст.
     */
    public static function updateText(Comment $comment, $text, $editorId)
    {
        // Базовый слепок пишется до правки: иначе исходного текста
        // уже не будет ни в комментарии, ни в истории.
        CommentTextSnapshot::recordBaseline($comment);

        $editDate = DateTimeUtils::$currentDate;

        self::buildAndSaveToDbV2([
            'UPDATE' => LPMTables::COMMENTS,
            'SET'    => [
                'text'     => (string)$text,
                'editorId' => (int)$editorId,
                'editDate' => DateTimeUtils::mysqlDate($editDate),
            ],
            'WHERE'  => ['id' => (int)$comment->id],
        ]);

        $comment->text = (string)$text;
        $comment->editorId = (int)$editorId;
        $comment->editDate = $editDate;
        $comment->_htmlText = null;
        $comment->_editor = null;
        $comment->_mergeRequests = null;

        CommentTextSnapshot::record($comment->id, $comment->text, $comment->editorId, $editDate);
    }

    public static function discard(Comment $comment)
    {
        self::buildAndSaveToDbV2([
            'DELETE' => LPMTables::COMMENTS,
            'WHERE'  => ['id' => (int)$comment->id],
        ]);
    }

    private static function loadFilesForComments(array $comments)
    {
        if (empty($comments)) {
            return;
        }

        $commentIds = array_map(function (Comment $comment) {
            return $comment->id;
        }, $comments);
        $filesByCommentId = LPMFile::loadGroupedByInstances(LPMInstanceTypes::COMMENT, $commentIds);

        foreach ($comments as $comment) {
            $comment->setFiles(isset($filesByCommentId[$comment->id]) ? $filesByCommentId[$comment->id] : []);
        }
    }
    
    public $id           = 0;
    public $instanceId   = 0;
    public $instanceType = 0;
    public $authorId     = 0;
    public $date         = 0;
    public $text         = '';
    public $dateLabel    = '';

    /**
     * Пользователь, правивший текст последним; 0 — правок не было.
     * @var int
     */
    public $editorId     = 0;

    /**
     * Дата последней правки текста; 0 — правок не было.
     * @var float
     */
    public $editDate     = 0;


    private $_htmlText;

    /**
     * @var User|false
     */
    private $_editor;


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
    /**
     * Данные коммента задачи, если это коммент задачи и для него есть данные.
     * @var IssueComment
     */
    public $issueComment;

    private $_mergeRequests;
    private $_files;
    
    public function __construct()
    {
        parent::__construct();
    
        //$this->id = 0;
    
        $this->_typeConverter->addFloatVars('id', 'instanceId', 'instanceType', 'authorId');
        $this->_typeConverter->addIntVars('editorId');
        $this->addDateTimeFields('date', 'editDate');
    
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
        return $this->dateLabel;
    }

    /**
     * Определяет, может ли пользователь редактировать текст комментария.
     *
     * Править можно только чек-лист тестирования
     * ({@see IssueCommentType::TEST_CHECKLIST}) и только его автору
     * либо модератору: остальные комментарии неизменяемы.
     * @param User $user Пользователь, для которого проверяются права.
     * @return bool
     */
    public function checkEditPermit(User $user)
    {
        if (empty($this->issueComment) || !$this->issueComment->isTestChecklist()) {
            return false;
        }

        return $user->isModerator() || $user->getID() == $this->authorId;
    }

    /**
     * Определяет, правили ли текст комментария после публикации.
     * @return bool
     */
    public function isEdited()
    {
        return !empty($this->editDate);
    }

    /**
     * Возвращает пользователя, правившего текст последним.
     * @return User|false false, если правок не было либо пользователя
     * не удалось загрузить.
     */
    public function getEditor()
    {
        if ($this->_editor === null) {
            if (empty($this->editorId)) {
                $this->_editor = false;
            } elseif ($this->editorId == $this->author->getID()) {
                $this->_editor = $this->author;
            } else {
                $this->_editor = User::load($this->editorId);
            }
        }

        return $this->_editor;
    }

    /**
     * Возвращает имя правившего текст пользователя ссылкой на его профиль.
     * @return string Пустая строка, если правившего установить не удалось.
     */
    public function getEditorLinkedName()
    {
        $editor = $this->getEditor();

        return empty($editor) ? '' : $editor->getLinkedName();
    }

    /**
     * Возвращает дату последней правки текста для отображения.
     * @return string Пустая строка, если правок не было.
     */
    public function getEditDate()
    {
        return self::getDateTimeStr($this->editDate);
    }

    /**
     * @return LPMFile[]
     */
    public function getFiles()
    {
        if ($this->_files === null) {
            $this->_files = LPMFile::loadListByInstance(LPMInstanceTypes::COMMENT, $this->id);
        }

        return $this->_files;
    }

    public function setFiles(array $files)
    {
        $this->_files = $files;
    }
    
    public function loadStream($hash)
    {
        $issueCommentHash = [];
        $icPrefix = self::ISSUE_COMMENT_PREFIX;
        $icPrefixLen = mb_strlen($icPrefix);
        foreach ($hash as $key => $value) {
            if (strpos($key, $icPrefix) === 0) {
                if ($value !== null) {
                    $issueCommentHash[mb_substr($key, $icPrefixLen)] = $value;
                }
                unset($hash[$key]);
            }
        }

        if (!empty($issueCommentHash)) {
            $this->issueComment = new IssueComment();
            if (!$this->issueComment->loadStream($issueCommentHash)) {
                return false;
            }
        }

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
