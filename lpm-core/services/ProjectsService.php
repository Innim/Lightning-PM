<?php

require_once(dirname(__FILE__) . '/../init.inc.php');

class ProjectsService extends LPMBaseService
{
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
     * @param  int $instanceId
     * @param  boolean $value
     */
    public function setIsFixed($instanceId, $value)
    {
        $projectId = (int) $instanceId;
        $project = Project::loadById($projectId);
        $user = $this->getUser();
        if (!$project->hasReadPermission($user)) {
            return $this->error("Нет прав на фиксацию проекта");
        }

        $instanceType = LPMInstanceTypes::PROJECT;
        $userId = $this->getUserId();
        $isFixed = (bool) $value;

        if ($isFixed) {
            $sql = "REPLACE `%s` (`userId`, `instanceType`, `instanceId`, `dateFixed`) " .
                "VALUES ('" . $userId . "', '" . $instanceType . "', '" . $projectId . "', '" . DateTimeUtils::mysqlDate() . "')";
            if (!$this->_db->queryt($sql, LPMTables::FIXED_INSTANCE)) {
                return $this->error('Проект не зафиксирован. Ошибка записи в БД.');
            }
        } else {
            $sql = "DELETE FROM `%s` " .
                "WHERE `userId` = " . "'" . $userId . "' " .
                "AND `instanceType` = " . "'" . $instanceType . "' " .
                "AND `instanceId` = " . "'" . $projectId . "'";
            if (!$this->_db->queryt($sql, LPMTables::FIXED_INSTANCE)) {
                return $this->error('Фиксация проекта не снята. Ошибка записи в БД');
            }
        }

        return $this->answer();
    }
}
