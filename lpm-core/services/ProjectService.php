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

        if(empty($userId)) {
            return $this->error("Неверные входные параметры");
        }

        if (Member::hasMember(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId, $userId )) {
            return $this->error("Тестеровшик уже добавлен");
        }

        Member::saveProjectForTester($projectId, $userId);

        $this->add2Answer("projectId", $projectId);
        $this->add2Answer("userId", $userId);

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

    public function deleteTester($projectId) {
        $projectId = (int)$projectId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) return $this->error('Недостаточно прав');


        if(!Member::deleteMembers(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId))
            return $this->error("Ошибка удаления тестера.");

        $this->add2Answer('$testerId', $projectId);

        return $this->answer();
    }

}

?>