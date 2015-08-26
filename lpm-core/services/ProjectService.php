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
			$sql .= "( '" . $userId . "', '" . Project::ITYPE_PROJECT . "', '" . $projectId . "' )";
		}
		
		if (!$this->_db->queryt( $sql, LPMTables::MEMBERS )) {
			return ($this->_db->errno == 1062) ? $this->error( 'Участники уже добавлены' ) : $this->error();
		}
		
		if (!$members = Member::loadListByProject( $projectId )) return $this->error();
		
		$this->add2Answer( 'members', $members );
		return $this->answer();
	}

	public function setIsArchive( $projectId , $value ){
		$isArchive = $value ? 1 : 0;
        $projectId = (int)$projectId;

        if (!$this->checkRole( User::ROLE_MODERATOR )) return $this->error( 'Недостаточно прав' );
        
        if ($projectId > 0) {
			$sql = "UPDATE `%1\$s`".
					"SET `isArchive` = '" . $value . "'" .
					"WHERE `id` = '" . $projectId . "'";
		
		    if (!$this->_db->queryt( $sql, LPMTables::PROJECTS )) 
			    return $this->error( 'Ошибка записи в БД' );        
        }        
		
		return $this->answer();
	}
}
?>