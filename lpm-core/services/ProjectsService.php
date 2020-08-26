<?php

require_once(dirname(__FILE__) . '/../init.inc.php');

class ProjectsService extends LPMBaseService
{
    /**
     * Преобразователь типов
     * @var TypeConverter
     */
    protected $_typeConverter;

    /**
     * Индентификатор проекта
     * @var int
     */
    public $projectId;

    /**
     * Флаг зафиксированного проекта
     * @var Boolean
     */
    public $isFixed;

    public function __construct() {
        parent::__construct();
        $this->_typeConverter->addIntVars('projectId');
        $this->_typeConverter->addBoolVars('isFixed');
    }

    public function setIsArchive($projectId, $value)
    {
        $isArchive = $value ? 1 : 0;
        $projectId = (int)$projectId;

        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }
        
        if ($projectId > 0) {
            $sql = "UPDATE `%1\$s`".
                    "SET `isArchive` = '" . $value . "'" .
                    "WHERE `id` = '" . $projectId . "'";
        
            if (!$this->_db->queryt($sql, LPMTables::PROJECTS)) {
                return $this->error('Ошибка записи в БД');
            }
        }

        return $this->answer();
    }

    /**
     * Записывает/удаляет id зафиксированного проекта в БД при показе списка проектов.
     * @param  int $projectId
     * @param  boolean $value
     */
    public function setIsFixed($projectId, $value)
    {
        $user = $this->getUser();
        $this->projectId = $projectId;
        $project = Project::loadById($this->projectId);

        if (!$project->hasReadPermission($user)) {
            return $this->error("Нет прав на фиксацию проекта");
        }

        $instanceType = LPMInstanceTypes::PROJECT;
        $userId = $this->getUserId();
        $this->isFixed = $value;

        if ($this->isFixed) {
            $sql = "REPLACE `%s` (`userId`, `instanceType`, `instanceId`, `dateFixed`) " .
                "VALUES ('" . $userId . "', '" . $instanceType . "', '" . $this->projectId . "', '" . DateTimeUtils::mysqlDate() . "')";
            if (!$this->_db->queryt($sql, LPMTables::IS_FIXED)) {
                return $this->error('Проект не зафиксирован. Ошибка записи в БД.');
            }
        } else {
            $sql = "DELETE FROM `%s` " .
                "WHERE `userId` = " . "'" . $userId . "' " .
                "AND `instanceType` = " . "'" . $instanceType . "' " .
                "AND `instanceId` = " . "'" . $this->projectId . "'";
            if (!$this->_db->queryt($sql, LPMTables::IS_FIXED)) {
                return $this->error('Фиксация проекта не снята. Ошибка записи в БД');
            }
        }

        return $this->answer();
    }
}
