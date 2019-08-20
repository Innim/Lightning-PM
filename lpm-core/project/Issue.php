<?php
class Issue extends MembersInstance
{
	private static $_listByProjects = array();
	private static $_listByUser = array();
	
	/**
	 * Выборка происходит из таблиц:
	 * - задач - i
	 * - пользователей - u
	 * - проектов - p
	 * - счетчиков задачи - cnt
	 * - стикер на доске - st.
	 * @param  string $where       Условие выборки.
	 * @param  string $extraSelect Дополнительная строка полей для выборки.
	 * @param  array  $extraTables Ассоциативный массив дополнительных таблиц для выборки
	 *                             [алиас => таблица].
	 * @return array<Issue> Массив загруженных задач.
	 */
	protected static function loadList($where, $extraSelect = '', $extraTables = null) {
		//return StreamObject::loadListDefault( $where, LPMTables::PROJECTS, __CLASS__ );
		$sql = "SELECT `i`.*, 'with_sticker', `st`.`state` `s_state`, " .
				//"IF(`%1\$s`.`status` <> 2, `%1\$s`.`priority`, 0) AS `realPriority`, " .
				"IF(`i`.`status` = 2, `i`.`completedDate`, NULL) AS `realCompleted`, " .
				"`u`.*, `cnt`.*, `p`.`uid` as `projectUID`";
		if (!empty($extraSelect))
			$sql .= ', ' . $extraSelect;

		$sql .= ' FROM `%2$s` AS `u`, `%4$s` AS `p`';
		$args = array( 
			LPMTables::ISSUES, 
			LPMTables::USERS,
			LPMTables::ISSUE_COUNTERS,
			LPMTables::PROJECTS,
			LPMTables::SCRUM_STICKER
		);

		if (!empty($extraTables)) {
			$i = count($args);
			foreach ($extraTables as $alias => $table) {
				$sql .= ', `%' . (++$i) . '$s` AS `' . $alias . '`';
				$args[] = $table;
			}
		}

		$sql .= <<<SQL
		, `%1\$s` AS `i` 
		LEFT JOIN `%3\$s` AS `cnt` ON `i`.`id` = `cnt`.`issueId` 
		LEFT JOIN `%5\$s` AS `st` ON `i`.`id` = `st`.`issueId` 
			WHERE `i`.`projectId` = `p`.`id` 
			  AND `i`.`deleted` = '0'
SQL;

		if ($where != '')
			$sql  .= " AND " . $where;
		$sql .= " AND `i`.`authorId` = `u`.`userId` ".
				"ORDER BY FIELD(`i`.`status`, " . Issue::STATUS_WAIT . "," .
				Issue::STATUS_IN_WORK . "," . Issue::STATUS_COMPLETED . "), " .
				"`realCompleted` DESC, `i`.`priority` DESC, `i`.`completeDate` ASC, `id` ASC";

		array_unshift($args, $sql);

		try {
			return StreamObject::loadObjList(self::getDB(), $args, __CLASS__);
		} catch (Exception $e) { 
			exit ('Error: ' . $e->getMessage() . '<br>' . self::getDB()->error);
		}
	}

	/**
	 * Получает список задач по проекту. Загруженный список кэшируется по проектам,
	 * если список еще не был получен - он будет загружен из базы.
	 * @param  integer $projectId Идентификатор проекта,
	 * @param  integer $type      Тип задач.
	 * @return array<Issue> Массив задач.
	 */
	public static function getListByProject($projectId, $type = -1) {
		if (!isset(self::$_listByProjects[$projectId])) {
			if (LightningEngine::getInstance()->isAuth()) {
				$where = "`i`.`projectId` = '" . $projectId . "'";
				if ($type != -1)
					$where .= "AND `i`.`type` = '" . $type . "'";
					
				self::$_listByProjects[$projectId] = self::loadList($where);
			} else {
				self::$_listByProjects[$projectId] = array();
			}
		}
		return self::$_listByProjects[$projectId];
	}

	/**
	 * Загружает список задач по проекту.
	 * @param  integer 		  $projectId   Идентификатор проекта,
	 * @param  array<integer> $issueStatus Список статусов задач, которые должны быть загружены.
	 * @param  string 		  $fromCompletedDate Минимальная дата завершени задачи
	 *                                       	 (в фотмате ГГГГ-ММ-ДД ЧЧ:ММ:СС)
	 * @param  string 		  $toCompletedDate Максимальная дата завершени задачи 
	 *                                     	   (в фотмате ГГГГ-ММ-ДД ЧЧ:ММ:СС)
	 * @return array<Issue> Массив загруженных задач.
	 */
	public static function loadListByProject($projectId, $issueStatus, $fromCompletedDate = null,
			$toCompletedDate = null) {
		//if (null === $issueStatus) //$issueStatus = Issue::STATUS_IN_WORK;
		$where = "`i`.`projectId` = '" . $projectId . "'";
			
		$args = '';
		if (!empty($issueStatus)) 
			$args = " AND `i`.`status` IN(" . implode(',', $issueStatus) . ')';
		if (!empty($fromCompletedDate))
			$args .= " AND `i`.`completedDate` >= '" . $fromCompletedDate . "'";
		if (!empty($toCompletedDate))
			$args .= " AND `i`.`completedDate` <= '" . $toCompletedDate . "'";

		$where .= $args;
		return self::loadList($where);
	}

	/**
	 * Загружает список задач по идентификаторам
	 * @param  array<int> $issueIds Идентификаторы задач
	 * @return array<Issue>
	 */
	public static function loadListByIds($issueIds) {
		if (empty($issueIds)) {
			return array();
		} else {
			$where = "`i`.`id` IN (" . implode(',', $issueIds) . ")";
			return self::loadList($where);
		}
	}
	
	public static function getListByMember($memberId) {
		if (!isset(self::$_listByUser[$memberId])) {
			if (LightningEngine::getInstance()->isAuth()) {
		       	/*$sql = "SELECT `%1\$s`.*,`%3\$s`.`uid` AS `projectUID`,
		       	`%3\$s`.`name` AS `projectName`,`%4\$s`.* FROM `%1\$s`, `%2\$s`, `%3\$s`,`%4\$s`". 
				  "WHERE `%1\$s`.`id` = `%2\$s`.`instanceId` " .
				  "AND `%4\$s`.`issueId` = `%1\$s`.`id` ".
				  "AND `%3\$s`.`id` = `%1\$s`.`projectId` ".
					"AND `%2\$s`.`userId` = '" . $memberId . "'".
					"AND `%1\$s`.`status` = '0'".
					"AND `%1\$s`.`deleted` = '0'".
					"ORDER BY `%1\$s`.`idInProject` ";*/

				self::$_listByUser[$memberId] = self::loadList(
					// только задачи, в которых я участник
					'`i`.`id` = `m`.`instanceId` AND `m`.`instanceType` = ' . LPMInstanceTypes::ISSUE . 
					' AND `m`.`userId` = ' . $memberId . 
					// открытые
					' AND `i`.`status` = ' . Issue::STATUS_IN_WORK .
					// и проект не в архиве
					' AND `p`.`isArchive` = 0',
					'`p`.`name` AS `projectName`',
					array('m' => LPMTables::MEMBERS)
				);
			} else {
				self::$_listByUser[$memberId] = array();
			}
		}

		return self::$_listByUser[$memberId];
	}

	public static function getCurrentList() {
		/*foreach (self::$_listByProjects as $list) {
			return $list;
		}
		
		return array();*/
		//return Project::
		return Project::$currentProject != null ?
				self::getListByProject(Project::$currentProject->id) : 
				array();
	}
	
	/**
	 * 
	 * @param float $issueId
	 * @return Issue
	 */
	public static function load($issueId) {
		return StreamObject::singleLoad($issueId, __CLASS__, "", "i`.`id");
	}

    /**
     * Загружает issue по идентификатору в проекте
     * @param $projectId
     * @param $idInProject
     * @return Issue
     */
	public static function loadByIdInProject($projectId, $idInProject) {
        return StreamObject::singleLoad($idInProject, __CLASS__, 
        	"`i`.`projectId` = " . $projectId, "i`.`idInProject");
    }
	
	public static function updateCommentsCounter( $issueId ) {
		$sql = "INSERT INTO `%1\$s` (`issueId`, `commentsCount`) " .
									"VALUES ('" . $issueId . "', '1') " .
					   "ON DUPLICATE KEY UPDATE `commentsCount` = " . 
							"(SELECT COUNT(*) FROM `%2\$s` " .
							  "WHERE `%2\$s`.`instanceType` = '" . LPMInstanceTypes::ISSUE . "' " .
								"AND `%2\$s`.`instanceId` = '" . $issueId . "')";
		$db = LPMGlobals::getInstance()->getDBConnect();
		$db->queryt( $sql, LPMTables::ISSUE_COUNTERS, LPMTables::COMMENTS );
	} 
	
	public static function updateImgsCounter( $issueId, $count ) {
		$sql = "INSERT INTO `%1\$s` (`issueId`, `imgsCount`) " .
									"VALUES ('" . $issueId . "', '" . $count . "') " .
					   "ON DUPLICATE KEY UPDATE `imgsCount` = " . 
							"(SELECT COUNT(*) FROM `%2\$s` " .
							  "WHERE `%2\$s`.`itemType` = '" . LPMInstanceTypes::ISSUE . "' " .
								"AND `%2\$s`.`itemId` = '" . $issueId . "' ".
								"AND `%2\$s`.`deleted` = 0)";
		$db = LPMGlobals::getInstance()->getDBConnect();
		$db->queryt( $sql, LPMTables::ISSUE_COUNTERS, LPMTables::IMAGES );
	} 

	public static function getCountImportantIssues($userId, $projectId = null)
	{
		$projectId = (int)$projectId;
		// $sql = "SELECT COUNT(*) AS count FROM `%1\$s` WHERE `%1\$s`.`priority` >= 79
		// 		AND `%1\$s`.`deleted` = 0 AND `%1\$s`.`status` = '". self::STATUS_IN_WORK."'";
		$sql = "SELECT COUNT(*) as count FROM `%1\$s` INNER JOIN `%2\$s` ON `%1\$s`.`instanceId` = `%2\$s`.`id`";
		if ($projectId === 0)
			$sql .= 'INNER JOIN `%3$s` ON `%2$s`.`projectId` = `%3$s`.`id`';
		$sql .=	"WHERE `%1\$s`.`userId`= '" . $userId . "' AND `%1\$s`.`instanceType`= 1 AND `%2\$s`.`priority` >= 79 
				AND `%2\$s`.`deleted` = 0 AND `%2\$s`.`status` = '".
				self::STATUS_IN_WORK."'";
		if ( 0 !== $projectId )
		{
			$sql .= " AND `%2\$s`.`projectId` = '".$projectId."'";
		}
		else 
		{
			// Игнорируем архивные проекты
			$sql .= " AND `%3\$s`.`isArchive` = 0";	
		}
		$db = LPMGlobals::getInstance()->getDBConnect();
		$res = $db->queryt( $sql, LPMTables::MEMBERS, LPMTables::ISSUES, LPMTables::PROJECTS );
		return $res ? (int)$res->fetch_assoc()['count'] : 0;
	}

    /**
     * Возвращает список стандартных меток для задачи отсортированных по количеству использований.
     * @return array[{id, label, countUses, projectId}...n] Список меток для задачи.
     */
	public static function getLabels() {
	    $labels = array();
        $projectId = (Project::$currentProject != null) ? Project::$currentProject->id : 0;
	    $sql = "SELECT `id`, `label`, `countUses`, `projectId` FROM `%s` WHERE (`deleted` = " . LabelState::ACTIVE . ") AND ".
            "(`projectId` = " . (int) $projectId . " OR `projectId` = 0)";

        $db = LPMGlobals::getInstance()->getDBConnect();
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS);
        if ($res) {
            while ($array = $res->fetch_assoc()) {
                $labels[] = $array;
            }
            uasort($labels, "Issue::labelsSort");
        }
        return $labels;
    }

    /**
     * Возвращает список меток во всех проектах по тексту метки.
     * @param Имя меток, которые нужно вернуть.
     * @return array Список меток по имени.
     */
    public static function getLabelsByLabelText($label) {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $label = $db->escape_string($label);
        $labels = array();
        $sql = "SELECT * FROM `%s` WHERE `label` = '" . $label . "'";
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS);
        if ($res) {
            while ($array = $res->fetch_assoc()) {
                $labels[] = $array;
            }
        }
        return $labels;
    }

    /**
     * Возвращает метку по id.
     * @param $id
     * @return array|null
     */
    public static function getLabel($id) {
        $id = (int) $id;
        $sql = "SELECT * FROM `%s` WHERE `id` = " . $id;
        $db = LPMGlobals::getInstance()->getDBConnect();
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS);
        return ($res) ? $res->fetch_assoc() : null;
    }

    public static function labelsSort($label1, $label2) {
        return $label2['countUses'] - $label1['countUses'];
    }

    /**
     * Добавить метками количество использований.
     * @param $labelNames Список имен меток, которым нужно добавить использование.
     * @param $projectId Идентификатор проекта приоритет метки которого нужно изменить, либо 0,
     * если нужно изменить приоритет только общей для проектов метки.
     */
    public static function addLabelsUsing($labelNames, $projectId = 0) {
        $projectId = (int) $projectId;
        $db = LPMGlobals::getInstance()->getDBConnect();
        foreach ($labelNames as $key => $value) {
            $labelNames[$key] = $db->escape_string($value);
        }

        $sql = "UPDATE `%s` SET `countUses` = `countUses` + 1 WHERE `label` IN('" . implode("','", $labelNames). "')" .
            " AND (`projectId` = 0 OR `projectId` = " . (int) $projectId . ")";
        $db->queryt($sql, LPMTables::ISSUE_LABELS);
    }

    /**
     * Возвращает список меток по имени.
     * @param $issueName Имя задачи.
     * @return array{string} Список меток в указанном имени.
     */
    public static function getLabelsByName($issueName) {
        $labels = array();
        $matches = array();
        if (preg_match_all("/(?:\[([\w: -]+?)\])+.*/UA", trim($issueName), $matches))
        {
            if (count($matches) > 1)
                $labels = array_unique($matches[1]);
        }
        return $labels;
    }

    /**
     * Сохраняет метку.
     * @param $label string Текст метки.
     * @param $projectId int Идентификатор проекта для которого создается метка (если не передан, то метка будет общей).
     * @param $id int Идентификатор метки (если не передан, то будет создана новая метка).
     * @param $countUses int Количество использований метки.
     * @param $deleted bool Удалена ли метка.
     * @return int|null Идентификатор вставленной/обновленной записи или null в случае ошибки.
     */
    public static function saveLabel($label, $projectId = 0, $id = 0, $countUses = 0, $deleted = 0) {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $id = ((int)$id > 0) ? (int)$id : "NULL";
        $projectId = (int) $projectId;
        $countUses = (int) $countUses;
        $label = $db->escape_string($label);

        $sql = "INSERT INTO `%s` (`id`, `projectId`, `label`, `countUses`, `deleted`) " .
            "VALUES ('" . $id . "', '" . $projectId . "', '" . $label . "', '" . $countUses . "', '" . $deleted . "') " .
            "ON DUPLICATE KEY UPDATE ".
            "`projectId` = VALUES(`projectId`), `label` = VALUES(`label`), `countUses` = VALUES(`countUses`), `deleted` = VALUES(`deleted`)";

        if ($db->queryt($sql, LPMTables::ISSUE_LABELS))
            return $db->insert_id;
        return null;
    }

    /**
     * Удаляет метку.
     * @param $id int Идентификатор метки.
     * @param $deleted bool Состояние удаления метки.
     * @return bool true в случае успешной операции, иначе false.
     */
    public static function changeLabelDeleted($id, $deleted) {
        $id = (int)$id;
        if ($id > 0) {
            $sql = "UPDATE `%s` SET `deleted` = " . $deleted . " WHERE `id` = " . $id;

            $db = LPMGlobals::getInstance()->getDBConnect();
            return ($db->queryt( $sql, LPMTables::ISSUE_LABELS )) ? true : false;
        }
    }

	public static function loadTotalCountIssuesByProject($projectId)
	{
		$sql = "SELECT COUNT(*) AS `count` FROM `%1\$s` WHERE `projectId` = " . $projectId . 
					" AND `deleted` = 0 ";
		$db = LPMGlobals::getInstance()->getDBConnect();
		if ($q = $db->queryt($sql, LPMTables::ISSUES))
		{
			$row = $q->fetch_assoc();
			return $row ? $row['count'] : 0;
		}
		else 
		{
			return null;
		}
	}

	public static function updateStatus(User $user, Issue $issue, $status, $sendNotify = true) {
		$issue->status = $status;
	    $hash = [
	    	'UPDATE' => LPMTables::ISSUES,
	    	'SET' => [
	    		'status' => $issue->status 
	    	],
	    	'WHERE' => [
	    		'id' => $issue->id
	    	]
	    ];

	    if ($issue->status === Issue::STATUS_COMPLETED) {
			$issue->completedDate = (float)DateTimeUtils::date();
	    	$hash['SET']['completedDate'] = DateTimeUtils::mysqlDate($issue->completedDate);
	    } else if ($issue->status === Issue::STATUS_IN_WORK) {
	    	// Сбрасываем дату завершения	    	
			$issue->completedDate = null;
	    	$hash['SET']['completedDate'] = '0000-00-00 00:00:00';
	    }


	    $db = self::getDB();
	    if (!$db->queryb($hash))
	    	throw new Exception('Status save failed', \GMFramework\ErrorCode::SAVE_DATA);

	    Project::updateIssuesCount($issue->projectId);

	    if ($sendNotify) {
	    	// Отправка оповещений
	    	
	    	// Slack
			$slack = SlackIntegration::getInstance();

			$subject = '';
			$text = '';
			switch ($issue->status) {
				case Issue::STATUS_COMPLETED : 
					$subject = 'Завершена задача "' . $issue->name . '"';
					$text = $user->getName() . ' отметил задачу "' .
						$issue->name . '" как завершённую';

					$slack->notifyIssueCompleted($issue);
					break;
				case Issue::STATUS_IN_WORK : 
					$subject = 'Открыта задача "' . $issue->name . '"';
					$text = $user->getName() . ' заново открыл задачу "' .
						$issue->name . '"';
					
					// TODO: оповестить в slaсk если вернули в работу
					break;
				case Issue::STATUS_WAIT : 
					$subject = 'Задача "' . $issue->name . '"ожидает проверки';
					$text = $user->getName() . ' поставил задачу "' .
						$issue->name . '"'.  '" на проверку';

					$slack->notifyIssueForTest($issue);
					break;
			}

			// Почта
			if (!empty($subject) && !empty($text)) {
				$members = $issue->getMemberIds();
				$members[] = $issue->authorId;

				$text .= "\n" . 'Просмотреть задачу можно по ссылке ' .	$issue->getConstURL();

				EmailNotifier::getInstance()->sendMail2Allowed(
					$subject, $text, $members, EmailNotifier::PREF_ISSUE_STATE);
			}
					
	    }
	}

	/**
	 * Обновляет значение приоритета задачи.
	 * @param  Issue  $issue Задача, у которой меняется приоритет.
	 * @param  int    $delta Изменение приоритета.
	 */
	public static function changePriority(Issue $issue, $delta) {
		$issue->priority = (int)max(0, min($issue->priority + $delta, 100));
	    $hash = [
	    	'UPDATE' => LPMTables::ISSUES,
	    	'SET' => [
	    		'priority' => $issue->priority 
	    	],
	    	'WHERE' => [
	    		'id' => $issue->id
	    	]
	    ];

	    $db = self::getDB();
	    if (!$db->queryb($hash))
	    	throw new Exception('Priority save failed', \GMFramework\ErrorCode::SAVE_DATA);
	}

	/**
	 * Возвращает постоянный URL задачи.
	 * @param  string $projectUID       Уникальный строковый идентификатор проекта.
	 * @param  int    $issueIdInProject Идентификатор задачи в проекте.
	 * @return URL задачи или URL сайта, идентификатор проекта пуст.
	 */
	public static function getConstURLBy($projectUID, $issueIdInProject) {
		if (empty($projectUID)) {
			return SITE_URL;
		} else {
			return Link::getUrlByUid(ProjectPage::UID, $projectUID, 
				ProjectPage::PUID_ISSUE, $issueIdInProject);
		}
	}

	const TYPE_DEVELOP     	= 0;
	const TYPE_BUG         	= 1;
	const TYPE_SUPPORT     	= 2;
	
	const STATUS_IN_WORK   	= 0;
	const STATUS_WAIT      	= 1;
	const STATUS_COMPLETED 	= 2;

	const MAX_IMAGES_COUNT	= 10;
	
	public $id            =  0;
	public $parentId      =  0;
	public $projectId     =  0;
	public $projectName  = ''; /*для загрузки задач по неск-им проектам*/
    public $idInProject   =  0;
	public $projectUID    = '';
	public $name          = '';
	public $desc          = '';
	/**
	 * Нормачасы. Для проектов, использующих Scrum - здесь story points
	 * @var float
	 */
	public $hours		  =  0;
	public $type          = -1;
	public $authorId      =  0;
	public $createDate    =  0;
	public $startDate     =  0;
	public $completeDate  =  0;
	public $completedDate =  0;
	public $priority      = 49;
	public $status        = -1;
	public $commentsCount = 0;

	private $_images = null;
    private $_testers = null;

	private $_htmlDesc = null;

	/**
	 * 
	 * @var User
	 */
	public $author;
	/**
	 * Проект, к которому относится задача
	 * @var Project
	 */
	private $_project;
	/**
	 * Стикер
	 * @var ScrumSticker
	 */
	private $_sticker = false;
	
	//public $baseURL = '';
	
	function __construct($id = 0) {
		parent::__construct();
		
		$this->id = $id;
		
		$this->_typeConverter->addFloatVars( 
			'id', 'parentId', 'authorId', 'type', 'status', 'commentsCount', 'hours'
		);
		$this->_typeConverter->addIntVars('priority');
		$this->_typeConverter->addBoolVars('isOnBoard');
		$this->addDateTimeFields('createDate', 'startDate', 'completeDate', 'completedDate');
		
		$this->addClientFields( 
			'id', 'parentId', 'idInProject', 'name', 'desc', 'type', 'authorId', 'createDate', 
			'completeDate','completedDate', 'startDate', 'priority', 'status' ,'commentsCount', 'hours'
		);

		$this->author = new User();
	}

	public function getClientObject($addfields = null)
	{
	    $obj = parent::getClientObject($addfields);

		if ($this->author)
			$obj->author = $this->author->getClientObject();

		$obj->url = $this->getConstURL();
		$obj->formattedDesc = $this->getDesc();

	    return $obj;
	}
	
	public function checkEditPermit( $userId ) {
		if ($userId == $this->authorId) return true;
		
		// TODO проверку прав
		return true;
	}

    public function getIdInProject(){
        return $this->idInProject;
    }

	public function getID() {
		return $this->id;
	}

	/**
	 * Возвращает моксимальное количество изображений.
	 * @return int Максимальное количество изображений.
	 */
	public function getMaxImagesCount() {
		return self::MAX_IMAGES_COUNT;
	}
	
	/**
	 * Устанавливает название задачи.
	 * @param string $value Название задачи.
	 */
	public function setTitle($value) {
		$this->title = $value;
	}

	/**
	 * Возвращает название задачи.
	 * @return string Название задачи.
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Загружает и возвращает объект проекта.
	 * Этот метод достаточно тяжелый, он будет грузить данные из БД
	 * Для получения имени проекта в общем списке - 
	 * лучше воспользоваться projectName.
	 * @return Project Модель проекта.
	 */
	public function getProject() {
	    if ($this->_project === null)
	    	$this->_project = Project::loadById($this->projectId);

	    return $this->_project;
	}

	/**
	 * Возвращает массив изображений, прикрепленных к записи
	 * @var array <code>Array of LPMImg</code>
	 */
	public function getImages() {
		if ($this->_images === null) {
			$this->_images = LPMImg::loadListByIssue( $this->id );
		}

		return $this->_images;
	}

	/**
	 * Стикер, прикрепленный к доске
	 * @return ScrumSticker|null
	 */
	public function getSticker() {
	    if ($this->_sticker === false)
	    	$this->_sticker = ScrumSticker::load($this->id);

	    return $this->_sticker;
	}

	public function isOnBoard() {
	    $sticker = $this->getSticker();
	    return $sticker !== null && $sticker->isOnBoard();
	}
	
	/**
	 * относительно текущей страницы
	 */
	public function getURL4View() {
		//return $this->baseURL;
		$curPage = LightningEngine::getInstance()->getCurrentPage();
		return $curPage->getBaseUrl( ProjectPage::PUID_ISSUE, $this->idInProject );
	}
	
	public function getPriorityStr() {
		if ($this->priority < 33) return 'низкий';
		else if ($this->priority < 66) return 'нормальный';
		else return 'высокий';
	}

	/**
	 * Возвращает URL страницы проекта, к которому относится задача,
	 * @param  string $hash Хэш параметр.
	 * @return string URL страницы проекта.
	 */
	public function getProjectUrl($hash = '') {
		return Project::getURLByProjectUID($this->projectUID, $hash);
	}
	
	/**
	 * Чтобы этот метод корректно работал, необходимо, 
	 * чтобы был загружен uid проекта
	 */
	public function getConstURL() {
		return self::getConstURLBy($this->projectUID, $this->idInProject);
	}
	
	public function getName() {
		return $this->name;
	}

	public function getLabelNames() {
	    return self::getLabelsByName($this->getName());
    }

	// public function getNormHours(){
	// 	return $this->hours;
	// }

	public function getStrHours() {
	    return ($this->hours == .5) ? "1/2" : (string) $this->hours;
    }

	/**
	 * Возвращает лейбл для параметра hours
	 * @param  boolean $short Использовать сокращение 
	 * @return Лейбл, со склонением, зависящим от значения hours. Например: часов, SP
	 */
	public function getNormHoursLabel($short = false)
	{
		return $this->getProject()->getNormHoursLabel($this->hours, $short);
	}
	
	public function getDesc() {

		if (empty($this->_htmlDesc))
		{
			$desc = $this->desc;

			if (strpos($desc, '<ul>') !== false)
			{
				// Предварительно порежем переносы в списках
				$desc = str_replace("\r\n", "\n", $desc);
				$desc = str_replace(array("</li>\n<li>","</li> \n<li>"), '</li><li>', $desc);
				$desc = str_replace(array("<ul>\n<li>", "</li>\n</ul>"), array('<ul><li>', '</li></ul>'), $desc);
			}

			$desc = HTMLHelper::codeIt($desc);
			$desc = nl2br($desc);
			$desc = HTMLHelper::linkIt($desc);

			$this->_htmlDesc = $desc;
		}
		
		return $this->_htmlDesc;
	}

	public function isCompleted() {
		return $this->status == self::STATUS_COMPLETED;
	} 

	public function isMember($userId) {
		if ($this->_members === null) {
			return Member::hasIssueMember($this->id, $userId);
		} else {
			$finded = false;
			foreach ($this->_members as $member) {
				if ($userId === $member->userId ){	
					$finded = true;
					break;
				}
			}
			return $finded;
		}
	}

    public function isTester($userId) {
        $testers = $this->getTesters();
        $count = count($testers);
        if ($count > 0) {
            foreach ($testers as $tester) {
                if ($tester->userId == $userId)
                    return true;
            }
        }

        return false;
    }
	
	/**
	 * Возвращает краткое описание задачи - для превью.
	 * @return string Краткое описание.
	 */
	public function getShortDesc() {
		$desc = $this->desc;
		// Для короткого описания вырежем весь код
		$desc = HTMLHelper::stripCode($desc);

		return parent::getRich(parent::getShort($desc));
	}
	
	public function getCreateDate() {
		return self::getDateStr( $this->createDate );
	}
	
	public function getCompleteDate() {
		return self::getDateStr( $this->completeDate );
	}
	
	public function getCompletedDate() {
		return self::getDateStr( $this->completedDate );
	}
	
	
	public function getCompleteDate4Input() {
		return self::getDate4Input( $this->completeDate );
	}
	
	public function getAuthorLinkedName() {
		return ($this->author) ? $this->author->getLinkedName() : '';
	}
	
	/*public function getMembersLinkedName() {
		if (!$this->_members) return '';
		$names = array();
		foreach ($this->_members as /*@var $member Member * /$member) {
			array_push( $names, $member->getLinkedName() );
		}
		
		return implode( ', ', $names );
	}*/
	
	public function getType() {
		switch ($this->type) {
			case self::TYPE_BUG     : return 'Ошибка';
			case self::TYPE_DEVELOP : return 'Разработка';
			case self::TYPE_SUPPORT : return 'Поддержка';
			default 			    : return '';
		}
	}
	
	public function getStatus() {
		switch ($this->status) {
			case self::STATUS_IN_WORK   : return 'В работе';
			case self::STATUS_WAIT      : return 'Ожидает проверки';
			case self::STATUS_COMPLETED : return 'Завершена';
			default 			        : return '';
		}
	}

	public function loadStream($hash) {
		$res = parent::loadStream($hash) && $this->author->loadStream($hash);
		if (isset($hash['with_sticker'])) {
			$sData = [];
		    foreach ($hash as $key => $value) {
		    	if (strpos($key, 's_') === 0 && $value !== null)
		    		$sData[$key] = $value;
		    }

		    if (empty($sData)) {
		    	$this->_sticker = null;
		    } else {
				if (!$this->_sticker) 
					$this->_sticker = new ScrumSticker();
	    		$this->_sticker->loadStream($sData);
	    		$this->_sticker->issueId = $this->issueId;
	    	}

	    }
		return $res;
	}

    public function getTesters() {
        if ($this->_testers == null && !$this->loadTesters())
            return array();
        return $this->_testers;
    }

    public function getTesterIds() {
        if ($this->_testers == null && !$this->loadTesters())
            return array();

        $arr = array();
        foreach ($this->_testers as $tester) {
            array_push( $arr, $tester->userId );
        }
        return $arr;
    }

    public function getTesterIdsStr() {
        return implode( ',', $this->getTesterIds() );
    }
	
	public function getMembersSp() {
		$members = $this->getMembers();
		$arr = array();
		foreach ($members as $member) 
			$arr[] = $member->sp; 
		return $arr;
	}
	
	public function getMembersSpStr() {
		return implode(',', $this->getMembersSp());
	}

	protected function loadMembers() {
		$this->_members = Member::loadListByIssue( $this->id );
		if ($this->_members === false) 
			throw new Exception( 'Ошибка при загрузке списка исполнителей задачи' );
		return true;
	}

	protected function loadTesters() {
	    $this->_testers = Member::loadListByIssueForTest($this->id);
	    if ($this->_testers === false)
	        throw new Exception('Ошибка при загрузке списка тестеров задачи');
	    return $this->_testers;
    }

	/**
	 * 
	 * @return array<{
	 *  type:string = youtube|video,
	 *  url:string
	 * ]>
	 */

	public function getVideoLinks()
	{
		$preg = [
			// YouTube
			"(?:youtube.)\w{2,4}\/(?:watch\?v=)",
			//Youtu.be
			":?youtu.be",
			// Droplr
			"d.pr\/v\/",
			// Innim owncloud
			"cloud.innim.ru\/index.php\/s\/"
		];

		preg_match_all("/(" . implode("|", $preg) . ")(\S*)\"/", $this->getDesc(), $video);
		#preg_match_all("/(?:youtube.)\w{2,4}\/(?:watch\?v=)(\S*)\"|(?:d.pr\/v\/)(\S*)\"/", $this->getDesc() , $video);
		$list = array();
		foreach ($video[0] as $key => $value) {
			$urlPrefix = $video[1][$key];
			$videoUid = $video[2][$key];
			$type = 'video';
			$url = null;

			if (strpos($urlPrefix, 'youtube') === 0) {
				// Это YouTube
				$type = 'youtube';
				$url = "http://www.youtube.com/embed/" . $videoUid;
			} else if (strpos($urlPrefix, 'youtu.be') === 0) {
				// Это YouTu.be
				$type = 'youtube';
				$url = "http://www.youtube.com/embed/" . $videoUid;
			} else if (strpos($urlPrefix, 'd.pr') === 0) {
				// Это Droplr
				// $url = "http://d.pr/v/" . $videoUid . "+";
				$url = "http://" . $urlPrefix . $videoUid . "+";
			} else {
				// Для owncloud по формату ссылки не понятно, поэтому грузим заголовок
				$url = "https://" . $urlPrefix . $videoUid . "/download";
				$header = get_headers($url, 1);
				if (empty($header) || !isset($header['Content-Type']) ||
						strpos($header['Content-Type'], 'video/') !== 0) {
					$url = null;
				}
			}

			if (!empty($url))
				$list[] = (object) compact('type', 'url');
		}

		return $list;
	}
}
?>