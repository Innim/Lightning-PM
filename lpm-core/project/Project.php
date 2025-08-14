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
    
    private static $_availList = [];

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
    public static function updateProjectSettings(
        $projectId,
        $scrum,
        $slackNotifyChannel,
        $gitlabGroupId,
        $gitlabProjectIds
    )
    {
        $db = self::getDB();

        $hash = [
            'UPDATE' => LPMTables::PROJECTS,
            'SET' => [
                'scrum' => $scrum,
                'slackNotifyChannel' => $slackNotifyChannel,
                'gitlabGroupId' => $gitlabGroupId,
                'gitlabProjectIds' => $gitlabProjectIds,
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
     * @return array
     */
    public static function getAvailList($isArchive)
    {
        $cacheKey = $isArchive ? 'archive' :'develop';
        if (!isset(self::$_availList[$cacheKey])) {
            if (LightningEngine::getInstance()->isAuth()) {
                $user = LightningEngine::getInstance()->getUser();
                self::$_availList[$cacheKey] = self::getInstanceList($user, $isArchive, true);
            } else {
                self::$_availList[$cacheKey] = [];
            }
        }
        
        return self::$_availList[$cacheKey];
    }

    /**
     * Получает из БД список всех проектов, доступные пользователю.
     * @param User $user
     * @param bool $isArchive
     * @param bool $loadImportantCount
     * @return array<Project>
     */
    private static function getInstanceList(User $user, bool $isArchive, bool $loadImportantCount = false)
    {
        // TODO: добавить счетчик сюда 
        $isModerator = $user->isModerator();
        $tables = [LPMTables::PROJECTS, LPMTables::FIXED_INSTANCE, LPMTables::MEMBERS, LPMTables::ISSUES];

        $instanceProject = LPMInstanceTypes::PROJECT;
        $archive = $isArchive ? 1 : 0;
        
        $sql = <<<SQL
    SELECT `p`.*, 
           IF (`fixed`.`instanceId` IS NULL, 0, 1) AS `fixedInstance`, 
           `fixed`.`dateFixed` AS `dateFixed`
SQL;
        if ($loadImportantCount) {
            $issueType = LPMInstanceTypes::ISSUE;
            $statusInWork = Issue::STATUS_IN_WORK;
            $minPriority = Issue::IMPORTANT_PRIORITY;

            $sql .= <<<SQL
,
           (SELECT COUNT(`i`.`id`) AS `count` 
              FROM `%4\$s` `i`
        INNER JOIN `%3\$s` `m`
                ON `m`.`instanceId` = `i`.`id`
             WHERE `m`.`userId` = $user->userId
               AND `m`.`instanceType` = $issueType
               AND `i`.`projectId` = `p`.`id`
               AND `i`.`priority` >= $minPriority
               AND `i`.`status` = $statusInWork
               AND `i`.`deleted` = 0) AS `importantIssuesCount`

SQL;
            $tables[] = LPMTables::MEMBERS;
        }

        $sql .= <<<SQL
      FROM `%1\$s` AS `p` 
SQL;
        
        if (!$isModerator) {
            $sql .= <<<SQL
INNER JOIN `%3\$s` AS `m` 
        ON `m`.`instanceId` = `p`.`id` 
       AND `m`.`instanceType` = $instanceProject
       AND `m`.`userId` = $user->userId 
SQL;
        }
    
        $sql .= <<<SQL
 LEFT JOIN `%2\$s` AS `fixed` 
        ON `fixed`.`instanceId` = `p`.`id` 
       AND `fixed`.`userId` = $user->userId
       AND `fixed`.`instanceType` =  $instanceProject
     WHERE `p`.`isArchive`= $archive
  ORDER BY `dateFixed` DESC, `p`.`lastUpdate` DESC
SQL;

        return StreamObject::loadObjList(self::getDB(), array_merge((array)$sql, $tables), __CLASS__);
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
    
    /**
     * Возвращает URL страницы Scrum доски проекта.
     * @param  string $projectUID Строковый идентификатор проекта.
     * @param  string $hash       Хэш параметр.
     * @return URL страницы проекта.
     */
    public static function getURLByProjectUIDScrum($projectUID, $hash = '')
    {
        return Link::getUrl(ProjectPage::UID, [$projectUID, ProjectPage::PUID_SCRUM_BOARD], $hash);
    }
    
    public static function checkDeleteComment($authorId, $commentId)
    {
        $user = LightningEngine::getInstance()->getUser();

        return $user->isAdmin() || $user->getID() == $authorId && Comment::checkDeleteCommentById($commentId);
    }
    
    /**
     * Обновляет в БД цели спринта текущего scrum проекта.
     * @param int $projectId идентификатор проекта.
     * @param string $targetText текст цели спринта.
     */
    public static function updateSprintTarget($projectId, $targetText)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        
        $text = $db->real_escape_string($targetText);
        $text = str_replace('%', '%%', $text);
        $instanceType = LPMInstanceTypes::PROJECT;
        
        $sql = "REPLACE `%s` (`instanceType`, `instanceId`, `content`) " .
            "VALUES ('" . $instanceType . "', '" . $projectId . "', '" . $text . "')";
        $result = $db->queryt($sql, LPMTables::INSTANCE_TARGETS);
        
        return $result;
    }
    
    /**
     * Получает из БД цели текущего спринта scrum проекта.
     * @param int $projectId идентификатор проекта.
     * @return string
     */
    public static function loadSprintTarget($projectId)
    {
        $content = self::loadValFromDb(LPMTables::INSTANCE_TARGETS, 'content', [
            'instanceType' => LPMInstanceTypes::PROJECT,
            'instanceId' => $projectId
        ]);

        return $content === null ? '' : $content;
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
     * Идентификатор группы проектов на GitLab,
     * соответствующей текущему проекту в таске.
     * @var int
     */
    public $gitlabGroupId;

    /**
     * Id привязанных проектов в GitLab.
     * @var string
     * @example '1,2,3'
     */
    public $gitlabProjectIds;

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

    /**
     * @var User
     */
    private $_tester;

    /**
     * @var User
     */
    private $_pm;

    /**
     * @var array<Member>
     */
    private $_specMasters = null;

    /**
     * @var array<Member>
     */
    private $_specTesters = null;
    
    /**
     * Цели спринта проекта.
     * @var string|null
     */
    private $_sprintTarget = null;
    
    /**
     * Форматированный текст целей спринта.
     * @var string|null
     */
    private $_sprintTargetHtml = null;

    /**
     * @var array<array>
     */
    private $_labels = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->_typeConverter->addIntVars('id', 'defaultIssueMemberId', 'gitlabGroupId');
        $this->_typeConverter->addBoolVars('scrum', 'fixedInstance');

        $this->addClientFields('id', 'uid', 'name', 'desc', 'scrum');
    }

    public function getClientObject($addFields = null)
    {
        $obj = parent::getClientObject($addFields);
        $obj->url = $this->getUrl();

        return $obj;
    }
    
    public function getID()
    {
        return $this->id;
    }

    public function isIntegratedWithGitlab()
    {
        return !empty($this->gitlabGroupId);
    }
    
    public function getShortDesc()
    {
        parent::getShort($this->desc, 200);
    }
    
    public function getUrl()
    {
        return self::getURLByProjectUID($this->uid);
    }

    public function getScrumBoardUrl()
    {
        return self::getURLByProjectUIDScrum($this->uid);
    }
    
    public function getDesc()
    {
        $text = nl2br($this->desc);
        $text = HTMLHelper::linkIt($text);
        return $text;
    }

    /**
     * Возвращает количество важных задач, открытых для текущего пользователя по этому проекту
     * @return int
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
    public function getCurrentSprintNum()
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
     * Возвращает строку для параметра hours в задаче из проекта (без значения)
     * @param  int $value
     * @param  boolean $short Использовать сокращение
     * @return Строка, со склонением, зависящим от значения hours. Например: часов, SP
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

    /**
     * Возвращает пользователя, назначенного тестировщиком проекта.
     * Если пользователь не выставлен, он будет загружен.
     * @return User|null
     */
    public function getTester()
    {
        if ($this->_tester === null) {
            $this->_tester = Member::loadTesterForProject($this->id);
        }

        return $this->_tester;
    }

    /**
     * Возвращает пользователя, назначенного PM проекта.
     * Если пользователь не выставлен, он будет загружен.
     * @return User|null
     */
    public function getPM() {
        if ($this->_pm === null) {
            $this->_pm = Member::loadPMForProject($this->id);
        }

        return $this->_pm;
    }

    /**
     * Возвращает список мастеров, определенных по тегам.
     * @return array<Member>
     */
    public function getSpecMasters()
    {
        return $this->_specMasters === null && !$this->loadSpecMasters() ? [] : $this->_specMasters;
    }


    /**
     * Возвращает список тестеров, определенных по тегам.
     * @return array<Member>
     */
    public function getSpecTesters()
    {
        return $this->_specTesters === null && !$this->loadSpecTesters() ? [] : $this->_specTesters;
    }

    /**
     * Возвращает список тегов для проекта.
     * @return array<array>
     */
    public function getLabels()
    {
        return $this->_labels === null && !$this->loadLabels() ? [] : $this->_labels;
    }

    /**
     * Возвращает текст текущих целей спринта.
     *
     * Если цели спринта не были загружены - будет выполнен запрос к БД.
     *
     * См. также getSprintTargetHtml().
     * @return string
     */
    public function getSprintTarget()
    {
        if ($this->_sprintTarget === null) {
            $this->_sprintTarget = Project::loadSprintTarget($this->id);
        }

        return $this->_sprintTarget;
    }
    
    /**
     * Возвращает форматированный текст для вставки в HTML код.
     * @return string
     */
    public function getSprintTargetHtml()
    {
        if (empty($this->_sprintTargetHtml)) {
            $this->_sprintTargetHtml = HTMLHelper::getMarkdownText($this->getSprintTarget());
        }
        
        return $this->_sprintTargetHtml;
    }

    /**
     * Возвращает список привязанных id проектов в GitLab.
     */
    public function getGitlabProjectIds(): array
    {
        return empty($this->gitlabProjectIds) ? [] : explode(',', $this->gitlabProjectIds);
    }

	protected function setVar($var, $value)
	{
        if ($var === 'importantIssuesCount') {
            $this->_importantIssuesCount = (int)$value;
            return true;
        }
        return parent::setVar($var, $value);
    }
    
    protected function loadMembers()
    {
        if (!$this->_members = Member::loadListByProject($this->id)) {
            return false;
        }
        return true;
    }

    /**
     * Загружается список мастеров, определенных по тегам.
     * @return array<Member>
     */
    private function loadSpecMasters()
    {
        $list = Member::loadSpecMastersForProject($this->id);
        if ($list === false) {
            throw new Exception('Ошибка при загрузке списка мастеров по тегам');
        }

        $this->_specMasters = $list;
        return $this->_specMasters;
    }

    /**
     * Загружается список тестеров, определенных по тегам.
     * @return array<Member>
     */
    private function loadSpecTesters()
    {
        $list = Member::loadSpecTestersForProject($this->id);
        if ($list === false) {
            throw new Exception('Ошибка при загрузке списка тестеров по тегам');
        }

        $this->_specTesters = $list;
        return $this->_specTesters;
    }

    private function loadLabels()
    {
        $list = Issue::getLabels($this->id);
        if ($list === false) {
            throw new Exception('Ошибка при загрузке списка тегов');
        }

        $this->_labels = $list;
        return $this->_labels;
    }
}
