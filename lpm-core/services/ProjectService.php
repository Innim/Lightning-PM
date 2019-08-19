<?php

require_once( dirname( __FILE__ ) . '/../init.inc.php' );

class ProjectService extends LPMBaseService
{
    public function addMembers($projectId, $userIds)
    {
        $projectId = (float)$projectId;

        if (!$userIds = $this->floatArr($userIds)) return $this->error('Неверные входные параметры');

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) return $this->error('Недостаточно прав');

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) return $this->error('Нет такого проекта');

        // пытаемся добавить участников проекта
        $sql = "insert into `%s` ( `userId`, `instanceType`, `instanceId` ) values ";

        foreach ($userIds as $i => $userId) {
            if ($i > 0) $sql .= ', ';
            $sql .= "( '" . $userId . "', '" . LPMInstanceTypes::PROJECT . "', '" . $projectId . "' )";
        }

        if (!$this->_db->queryt($sql, LPMTables::MEMBERS)) {
            return ($this->_db->errno == 1062) ? $this->error('Участники уже добавлены') : $this->error();
        }

        if (!$members = Member::loadListByProject($projectId)) return $this->error();

        $this->add2Answer('members', $members);
        return $this->answer();
    }

    public function addTester( $projectId, $userId ){
        $projectId = (float)$projectId;
        $userId = (float)$userId;


        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) return $this->error('Недостаточно прав');

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) return $this->error('Нет такого проекта');

        $query = "SELECT userId FROM lpm_tester WHERE projectId='$projectId' ";

        $STH = $this->_db->query($query);
        $row = $STH->fetch_row();

        if ($row !== null) {
            return $this->error("Tester exists");
        }

        $tester_val = $this->_db->preparet("INSERT INTO `%s` (`userId`, `projectId`) VALUES ( '{$userId}', '{$projectId}')", LPMTables::TESTER);
        $result = $tester_val->execute();

        if(!$result) {
            return $this->error("error ");
        }
        $this->add2Answer("TESTER", $userId);

        return $this->answer();
    }

    public function getSumOpenedIssuesHours($projectId)
    {
        // TODO проверить права доступа для этого проекта

        $count = Project::sumHoursActiveIssues($projectId);

        if ($count === false) return $this->error('Ошибка получения данных суммы часов');

        $this->add2Answer('count', $count);

        return $this->answer();
    }

}

?>