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

	public function CheckTester( $IdProjectURL ){
		$arrayUrl = explode("/", $IdProjectURL);
		$numberProject = $arrayUrl[4];

	        $PDO = new PDO("mysql:host=localhost;dbname=lpm_schema", "root", "5513");

	        		    $STH = $PDO->query('SELECT idPost FROM lpm_tester');

	        // $sqli = "SELECT `idPost` FROM ";
	        // $www = $this->_db->queryt($sqli, LPNTables::TESTER);

		    $STH = $PDO->query('SELECT idPost FROM lpm_tester');
		    $projectarray = $STH->fetchAll(PDO::FETCH_COLUMN, 0);

		    if(in_array($numberProject, $projectarray)){
		        $WTH = $PDO->query("SELECT nameUser FROM lpm_tester WHERE idPost='$numberProject' ");
		        $tb = $WTH->fetch(PDO::FETCH_COLUMN, 0);
		        if($tb == ""){
		            $IdProjectURL =  'NotFoundProgect';
		        } else {
		        	$IdProjectURL = $tb;
		        }
		    } else {
		        $IdProjectURL = 'NotFoundProgect';
		    }

        $this->add2Answer( 'IdProjectURL', $IdProjectURL );
        return $this->answer();
	}

	public function TesterAddToServer( $valueSelected, $textSelected, $urlProject ){

		$ARRAY_POST_URL = explode("/", $urlProject);
		$POSTS = $ARRAY_POST_URL[4];

//		$tester_val = $PDO->prepare("INSERT INTO lpm_tester (idUser, nameUser, idPost) VALUES ('$valueSelected', '$textSelected', '$POSTS')");
        $tester_val = $this->_db->preparet("INSERT INTO `%s` (`idUser`, `nameUser`, `idPost`) VALUES ( '{$valueSelected}','$textSelected', '$POSTS')", LPMTables::TESTER);
		$tester_val->execute();

		$this->add2Answer('Test Users', $textSelected);
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