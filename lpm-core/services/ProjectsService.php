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
}
