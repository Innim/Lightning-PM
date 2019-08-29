<?php

require_once( dirname( __FILE__ ) . '/../init.inc.php' );

class ProjectService extends LPMBaseService 
{
	public function addMembers( $projectId, $userIds ) {
		$projectId = (float)$projectId;
		
		if (!$userIds = $this->floatArr( $userIds )) return $this->error( 'Неверные входные параметры' );
		
		// проверяем права пользователя
		if (!$this->checkRole( User::ROLE_MODERATOR )) return $this->error( 'Недостаточно прав' );
		
		// проверим, что существует такой проект
		if (!Project::loadById( $projectId )) return $this->error( 'Нет такого проекта' );
		
		// пытаемся добавить участников проекта
		$sql = "insert into `%s` ( `userId`, `instanceType`, `instanceId` ) values ";
		
		foreach ($userIds as $i => $userId) {
			if ($i > 0) $sql .= ', ';
			$sql .= "( '" . $userId . "', '" . LPMInstanceTypes::PROJECT . "', '" . $projectId . "' )";
		}
		
		if (!$this->_db->queryt( $sql, LPMTables::MEMBERS )) {
			return ($this->_db->errno == 1062) ? $this->error( 'Участники уже добавлены' ) : $this->error();
		}
		
		if (!$members = Member::loadListByProject( $projectId )) return $this->error();
		
		$this->add2Answer( 'members', $members );
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

	public function setProjectSettings($projectId, $scrum, $slackNotifyChannel) {
        $projectId = (int)$projectId;
        $slackNotifyChannel = (string)$slackNotifyChannel;

        if ($scrum !== 0 and $scrum !== 1 ) {
            return $this->error('Invalid input parameters');
        }

        $scrum = (bool)$scrum;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) return $this->error('Not enough rights');

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) return $this->error('No such project');

        $result = Project::updateProjectSettings($projectId, $scrum, $slackNotifyChannel);

        if (!$result) return $this->error('Error update table');

        $this->add2Answer('projectId', $projectId);
        $this->add2Answer('scrum', $scrum);
        $this->add2Answer('slackNotifyChannel', $slackNotifyChannel);

        return $this->answer();
    }


}
?>