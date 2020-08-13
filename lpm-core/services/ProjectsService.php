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

        //Функционал для задачи №243
        // if ($isArchive) {
        //     $this->setIsFixed($projectId, false);
        // }
        
        return $this->answer();
    }

    /**
     * Запись флага в БД фиксации проекта при показе списка проектов.
     * @param  int $projectId
     * @param  boolean $value
     */
    public function setIsFixed($projectId, $value)
    {
        $projectId = $projectId;
        $instanceType = LPMInstanceTypes::PROJECT;
        $userId = $this->getUserId();

        if ($value === true) {
            $sql = "INSERT INTO `%s` (`userId`, `instanceType`, `instanceId`)" .
                "VALUES (". $userId . ", '" . $instanceType . "', '" . $projectId . "' ) ";

            if (!$this->_db->queryt($sql, LPMTables::IS_FIXED)) {
                return $this->error('Проект не зафиксирован. Ошибка записи в БД.');
            }
        } else {
            $sql = "DELETE FROM `%s` " .
            "WHERE `userId` = " . "'" . $userId . "' " .
            "AND `instanceType` = " . "'" . $instanceType . "' " .
            "AND `instanceId` = " . "'" . $projectId . "'";

            if (!$this->_db->queryt($sql, LPMTables::IS_FIXED)) {
                return $this->error('Фиксация проекта не снята. Ошибка записи в БД');
            }
        }

        return $this->answer();
    }
}
