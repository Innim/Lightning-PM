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

    public function getSumOpenedIssuesHours($projectId)
    {
        // TODO проверить права доступа для этого проекта

        $count = Project::sumHoursActiveIssues($projectId);

        if ($count === false) return $this->error('Ошибка получения данных суммы часов');

        $this->add2Answer('count', $count);

        return $this->answer();
    }

    public function addIssueMemberDefault ($projectId, $memberByDefaultId) {
        $projectId = (int)$projectId;
        $memberByDefaultId = (int)$memberByDefaultId;

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) return $this->error('Нет такого проекта');

        $memberProject = Project::loadById($projectId)->getMember($memberByDefaultId);

        if(!$memberProject) {
            return $this->error('Исполнитель не найден в участниках проекта');
        }

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR )) return $this->error('Недостаточно прав');

        $defaultIssueMemberId = Project::loadById($projectId)->defaultIssueMemberId;

        if ($defaultIssueMemberId) {
            return $this->error('Исполнитель уже назначен для проекта');
        }

        $result = Project::updateIssueMemberDefault($projectId, $memberByDefaultId);

        if ( !$result ) {
            return $this->error('Ошибка изменения данных');
        }

        $this->add2Answer("projectId", $projectId);
        $this->add2Answer("performerByDefaultId", $memberByDefaultId);

        return $this->answer();

    }

    public function deleteMemberDefault ($projectId) {
        $projectId = (int)$projectId;
        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) return $this->error('Недостаточно прав');

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) return $this->error('Нет такого проекта');

        $defaultIssueMemberId = Project::loadById($projectId)->defaultIssueMemberId;

        if (!$defaultIssueMemberId) {
            return $this->error('Исполнитель не назначен для проекта');
        }

        $result = Project::deleteMemberDefault($defaultIssueMemberId, $projectId);

        if (!$result) {
            return $this->error('Ошибка удаления.');
        }

         $this->add2Answer('$result', $result);

        return $this->answer();
    }

}

?>