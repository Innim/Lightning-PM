<?php
/**
 * Проект.
 */
class Project extends MembersInstance
{
    /**
     *
     * @var Project
     */
    public static $currentProject;
    
    private static $_availList = null;

    private static $_isArchive = false;

    /**
     * Загруженные проекты по идентификаторам
     * @var array<int, Project>
     */
    private static $_projectsByIds = [];
    
    public static function loadList($where = null)
    {
        return StreamObject::loadListDefault(
            self::getDB(),
            $where,
            LPMTables::PROJECTS,
            __CLASS__
        );
    }

    /**
     * Создает новый проект.
     */
    public static function addProject($uid, $name, $desc)
    {
        $db = self::getDB();
        $hash = [
            'INSERT' => [
                'uid'  => $uid,
                'name' => $name,
                'desc' => $desc,
                'date' => DateTimeUtils::mysqlDate(),
            ],
            'INTO'   => LPMTables::PROJECTS
        ];

        return $db->queryb($hash);
    }

    /**
     * Загружает список scrum проектов.
     * @param  boolean $includeArchive `true` если надо загружать также и заархивированные проекты,
     * @return array<Project>
     */
    public static function loadScrumList($includeArchive = false)
    {
        $where = ['`scrum` = 1'];
        if (!$includeArchive) {
            $where[] = '`isArchive` = 0';
        }

        return self::loadList($where);
    }

    /**
     * Обновляет настройки проекта
     *
     */
    public static function updateProjectSettings($projectId, $scrum, $slackNotifyChannel)
    {
        $db = self::getDB();

        $hash = [
            'UPDATE' => LPMTables::PROJECTS,
            'SET' => [
                'scrum' => $scrum,
                'slackNotifyChannel' => $slackNotifyChannel
            ],
            'WHERE' => [
                'id' => $projectId
            ]
        ];

        return $db->queryb($hash);
    }

    public static function updateIssueMemberDefault($projectId, $memberByDefaultId)
    {
        $db = self::getDB();

        if ($projectId != null && $memberByDefaultId != null) {
            $sql = "update `%s` set `defaultIssueMemberId`='". $memberByDefaultId ."'  where  `id` = '". $projectId ."'";
            $result = $db->queryt($sql, LPMTables::PROJECTS);

            if ($result) {
                return true;
            }
        }

        return false;
    }

    public static function deleteMemberDefault($projectId)
    {
        $sql = "UPDATE `%s` SET `defaultIssueMemberId`=null WHERE `id`='$projectId' ";
        return self::getDB()->queryt($sql, LPMTables::PROJECTS);
    }

    public static function updateMaster($projectId, $masterId)
    {
        $db = self::getDB();
        return $db->queryb([
            'UPDATE' => LPMTables::PROJECTS,
            'SET' => ['masterId' => $masterId],
            'WHERE' => ['id' => $projectId]
        ]);
    }

    /**
     * Получает список доступных проектов пользователю.
     * Метод кэширует полученные данные, поэтому загрузка
     * будет производится только при первом обращении.
     * @param boolean $isArchive
     * @return array|null
     */
    public static function getAvailList($isArchive)
    {
        if (LightningEngine::getInstance()->isAuth()) {
            if (self::$_availList === null) {
                $user = LightningEngine::getInstance()->getUser();
                $typeProjects = $isArchive ? 'archive' : 'develop';
                try {
                    return self::$_availList[$typeProjects] = self::getInstanceList($user, $isArchive);
                } catch (Exception $e) {
                    exit('Error: ' . $e->getMessage() . '<br>' . self::getDB()->error);
                }
            }
            return $isArchive ? self::$_availList['archive'] : self::$_availList['develop'];
        }
        return;
    }

    /**
     * Получает из БД все проекты, доступные пользователю.
     * @param $user
     * @param boolean $isArchive
     * @return array
     */
    private static function getInstanceList($user, $isArchive)
    {
        if (!$user->isModerator()) {
            $sql = "SELECT projects.*, members.userId, fixed.instanceId AS `fixedInstance`, fixed.dateFixed AS `dateFixed` FROM `%1\$s` AS projects " .
                        "INNER JOIN `%2\$s` AS members ON members.instanceId = projects.id " .
                        "AND `members`.`instanceType` = '" . LPMInstanceTypes::PROJECT . "' " .
                        "AND `members`.`userId` = '" . $user->userId . "' " .
                        "LEFT JOIN `%3\$s` AS fixed ON fixed.instanceId = projects.id " .
                        "AND `fixed`.`userId` = '" . $user->userId . "' " .
                        "AND `fixed`.`instanceType` = '" . LPMInstanceTypes::PROJECT . "' " .
                        "WHERE `projects`.`isArchive` = '" . (int)$isArchive . "' ".
                    "ORDER BY dateFixed DESC, projects.lastUpdate DESC";
            return StreamObject::loadObjList(self::getDB(), array( $sql, LPMTables::PROJECTS, LPMTables::MEMBERS, LPMTables::IS_FIXED ), __CLASS__);
        }
        $sql = "SELECT projects.*, fixed.instanceId AS `fixedInstance`, fixed.dateFixed AS `dateFixed` FROM `%1\$s` AS projects " .
                    "LEFT JOIN `%2\$s` AS fixed ON fixed.instanceId = projects.id " .
                    "AND `fixed`.`instanceType` = '" . LPMInstanceTypes::PROJECT . "' " .
                    "AND `fixed`.`userId` = '" . $user->userId . "' " .
                    "WHERE `projects`.`isArchive` = '" . (int)$isArchive . "' " .
                "ORDER BY dateFixed DESC, projects.lastUpdate DESC";
        return StreamObject::loadObjList(self::getDB(), array( $sql, LPMTables::PROJECTS, LPMTables::IS_FIXED ), __CLASS__);
    }

    public static function updateIssuesCount($projectId)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $sql = "UPDATE `%1\$s` ".
                  "SET `issuesCount` = (SELECT COUNT(*) FROM `%2\$s` ".
                                        "WHERE `%1\$s`.`id` = `%2\$s`.`projectId` ".
                                          "AND  `%2\$s`.`status` IN (0,1) ".
                                          "AND  `%2\$s`.`deleted` = 0) ".
                "WHERE  `%1\$s`.`id` = '" . $projectId . "'";

        return $db->queryt($sql, LPMTables::PROJECTS, LPMTables::ISSUES);
    }

    public static function sumHoursActiveIssues($projectId)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $sql ="SELECT SUM(`hours`) AS `sum` FROM `%s` WHERE `projectId` = ".$projectId." ".
               "AND `deleted` = 0 ".
               "AND NOT `status` = ".Issue::STATUS_COMPLETED." ";
        $query = $db->queryt($sql, LPMTables::ISSUES);
        if (!$query || !($row = $query->fetch_assoc())) {
            return false;
        }
        return (float)$row['sum'];
    }

    /**
     *
     * @param string $projectUID
     * @return Project
     */
    public static function load($projectUID)
    {
        return StreamObject::singleLoad($projectUID, __CLASS__, '', 'uid');
    }

    /**
     * Загружает данные проекта.
     * Если проект уже был загружен - вернется сохраненный объект
     * @param  int     $projectId   Идентификатор проекта
     * @param  boolean $forceReload Принудительно выполняет загрузку из БД,
     * даже если есть уже загруженные данные
     * @return Project
     */
    public static function loadById($projectId, $forceReload = false)
    {
        if ($forceReload || !isset(self::$_projectsByIds[$projectId])) {
            $project = StreamObject::singleLoad($projectId, __CLASS__, '');
            ;
            self::$_projectsByIds[$projectId] = $project;
        } else {
            $project = self::$_projectsByIds[$projectId];
        }

        return $project;
    }

    /**
     * Возвращает URL страницы проекта.
     * @param  string $projectUID Строковый идентификатор проекта.
     * @param  string $hash       Хэш параметр.
     * @return URL страницы проекта.
     */
    public static function getURLByProjectUID($projectUID, $hash = '')
    {
        return Link::getUrl(ProjectPage::UID, [$projectUID], $hash);
    }

    public static function checkDeleteComment($author, $cookie)
    {
        $user = LightningEngine::getInstance()->getUser();

        return  $user->isAdmin() || $user->getID() == $author && Comment::checkDeleteCommentById($cookie);
    }

    public static function getProjectTester()
    {
        $projectId = self::$currentProject->getID();
        $tester = Member::loadTesterForProject($projectId);
        if (!$tester) {
            return null;
        }

        return $tester[0];
    }

    /**
     *
     * @var int
     */
    public $id = 0;
    public $uid;
    public $name;
    public $desc;
    public $defaultIssueMemberId;

    /**
     * Проект ведется с помощью методологии Scrum
     * @var Boolean
     */
    public $scrum = false;

    /**
     * Имя канала для оповещений в Slack (без решетки).
     * @var string
     */
    public $slackNotifyChannel;

    /**
     * Идентификатор пользователя, являющегося мастером в проекте.
     * @var int
     */
    public $masterId;

    /**
     * Проект зафиксирован в таблице проектов
     * @var Boolean|null
     */
    public $fixedInstance;

    private $_importantIssuesCount = -1;

    private $_sumOpenedIssuesHours = -1;
    private $_totalIssuesCount = -1;

    private $_currentSprintNum = -1;

    /**
     * @var User
     */
    private $_master;

    public function __construct()
    {
        parent::__construct();
        $this->_typeConverter->addIntVars('id', 'defaultIssueMemberId');
        $this->_typeConverter->addBoolVars('scrum', 'fixedInstance');
    }

    public function getID()
    {
        return $this->id;
    }

    public function getShortDesc()
    {
        parent::getShort($this->desc, 200);
    }

    public function getUrl()
    {
        //return Link::getUrlByUid( ProjectPage::UID, $this->uid );
        return self::getURLByProjectUID($this->uid);
    }

    public function getDesc()
    {
        $text = nl2br($this->desc);
        $text = HTMLHelper::linkIt($text);
        return $text;
    }

    /**
     * Возвращает количество важных задач, открытых для текугео пользователя по этому проекту
     * @return [type] [description]
     */
    public function getImportantIssuesCount()
    {
        if ($this->_importantIssuesCount === -1) {
            $userId = LightningEngine::getInstance()->getUserId();
            $this->_importantIssuesCount = empty($userId) ? 0 :
                Issue::getCountImportantIssues($userId, $this->id);
        }

        return $this->_importantIssuesCount;
    }

    /**
     * Возвращает количество всех задач текущего проекта
     * @return int
     */
    public function getTotalIssuesCount()
    {
        if ($this->_totalIssuesCount === -1) {
            $this->_totalIssuesCount = Issue::loadTotalCountIssuesByProject($this->id);
        }

        return $this->_totalIssuesCount;
    }

    /**
     * Возвращает сумму часов открытых задач
     * @return int
     */
    public function getSumOpenedIssuesHours()
    {
        if ($this->_sumOpenedIssuesHours === -1) {
            $this->_sumOpenedIssuesHours = self::sumHoursActiveIssues($this->id);
        }

        return $this->_sumOpenedIssuesHours;
    }

    /**
     * Возвращает номер текущего спринта (с 1), если проект является SCRUM проектом, иначе - 0.
     * @return int
     */
    public function getCurrentSpintNum()
    {
        if ($this->scrum) {
            if ($this->_currentSprintNum === -1) {
                $this->_currentSprintNum = ScrumStickerSnapshot::getLastSnapshotId($this->id) + 1;
            }

            return $this->_currentSprintNum;
        } else {
            return 0;
        }
    }

    /**
     * Возвращает лейбл для параметра hours в задаче из проекта (без значения)
     * @param  int $value
     * @param  boolean $short Использовать сокращение
     * @return Лейбл, со склонением, зависящим от значения hours. Например: часов, SP
     */
    public function getNormHoursLabel($value, $short = false)
    {
        if ($this->scrum) {
            return DeclensionHelper::storyPoints($value, $short);
        } else {
            return $short ? 'ч' : DeclensionHelper::hours($value);
        }
    }

    /**
     * Определяет, есть ли у пользователя права на чтение проекта.
     * @param  User    $user Пользователь.
     * @return boolean       true если есть права, в ином случае false.
     */
    public function hasReadPermission(User $user)
    {
        if ($user->isModerator()) {
            return true;
        }

        if ($this->_members != null) {
            foreach ($this->_members as $member) {
                if ($user->userId == $member->userId) {
                    return true;
                }
            }
            return false;
        } else {
            $sql = "SELECT `instanceId` FROM `%s` " .
                             "WHERE `instanceId`   = '" . $this->id . "' " .
                               "AND `instanceType` = '" . LPMInstanceTypes::PROJECT . "' " .
                               "AND `userId`       = '" . $user->userId . "'";

            $db = LPMGlobals::getInstance()->getDBConnect();
            if (!$query = $db->queryt($sql, LPMTables::MEMBERS)) {
                return false;
            }

            return $query->num_rows > 0;
        }
    }

    /**
     * Возвращает пользователя, назначенного мастером проекта.
     * Если пользователь не выставлен, он будет загружен.
     * @return User|null
     */
    public function getMaster()
    {
        if ($this->_master === null && $this->masterId > 0) {
            $this->_master = User::load($this->masterId);
        }

        return $this->_master;
    }

    protected function loadMembers()
    {
        if (!$this->_members = Member::loadListByProject($this->id)) {
            return false;
        }
        return true;
    }

    // public function setIsArchive( $projectId , $value ){
    // 	$db = LPMGlobals::getInstance()->getDBConnect();
    // 	$sql = "UPDATE `%1\$s`".
    // 			"SET `isArchive` = '" . $value . "'" .
    // 			"WHERE `id` = '" . $projectId . "'";
    // 	return $db->queryt( $sql, LPMTables::PROJECTS );
    // }

}
