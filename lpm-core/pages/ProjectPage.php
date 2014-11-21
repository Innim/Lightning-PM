<?php
class ProjectPage extends BasePage
{
	const UID = 'project';
	const PUID_MEMBERS = 'members';
	const PUID_ISSUES  = 'issues';
	const PUID_ISSUE   = 'issue';
	
	/**
	 * 
	 * @var Project
	 */
	private $_project;
	
	function __construct()
	{
		parent::__construct( self::UID, '', true, true );
		
		array_push( $this->_js, 'project', 'issues' );
		$this->_pattern = 'project';
		
		$this->_baseParamsCount = 2;
		$this->_defaultPUID     = self::PUID_ISSUES;
		
		$this->addSubPage( self::PUID_ISSUES , 'Список задач' );
		$this->addSubPage( self::PUID_MEMBERS, 'Участники', 'project-members', 
						   array( 'users-chooser' ), '', User::ROLE_MODERATOR );
	}
	
	public function init() {
		if (!parent::init()) return false;
		
		$engine = LightningEngine::getInstance();
		// загружаем проект, на странице которого находимся
		if ($engine->getParams()->suid == '' 
			|| !$this->_project = Project::load( $engine->getParams()->suid )) return false;
		// проверим, можно ли текущему пользователю смотреть этот проект
		if (!$user = LightningEngine::getInstance()->getUser()) return false;
		if (!$user->isModerator()) {
			$sql = "SELECT `instanceId` FROM `%s` " .
			                 "WHERE `instanceId`   = '" . $this->_project->id . "' " .
							   "AND `instanceType` = '" . Project::ITYPE_PROJECT . "' " .
							   "AND `userId`       = '" . $user->userId . "'";		
			if (!$query = $this->_db->queryt( $sql, LPMTables::MEMBERS )) return false;
			if ($query->num_rows == 0) return false;
		}
		
		Project::$currentProject = $this->_project;
		
		$this->_header = 'Проект &quot;' . $this->_project->name . '&quot;';// . $this->_title;
		$this->_title  = $this->_project->name;		
		
		// проверяем, не добавили ли задачу или может отредактировали
		if (isset( $_POST['actionType'] )) {			 
			 if ($_POST['actionType'] == 'addIssue') $this->saveIssue();
			 elseif ($_POST['actionType'] == 'editIssue' && isset( $_POST['issueId'] )) 
			 	$this->saveIssue( true );
		}
		
		// может быть это страница просмотра задачи?
		if (!$this->_curSubpage) {
			if ($this->getPUID() == self::PUID_ISSUE) 
			{
				$issueId = $this->getCurentIssueId((float)$this->getAddParam());
				if ($issueId <= 0 || !$issue = Issue::load( (float)$issueId) )
						LightningEngine::go2URL( $this->getUrl() );				
				
				$issue->getMembers();	
				Issue::$currentIssue = $issue;
				
				Comment::setCurrentInstance( Issue::ITYPE_ISSUE, $issue->id );

				$this->_title  =$issue->name .' - '. $this->_project->name ;
				$this->_pattern = 'issue';
				ArrayUtils::remove( $this->_js,	'project' );
				array_push( $this->_js,	'issue' );
			} 
		} 
		


		
		// загружаем задачи
		if (!$this->_curSubpage || $this->_curSubpage->uid == self::PUID_ISSUES) {			
			$issues = Issue::getListByProject( $this->_project->id );		
		}
		
		return $this;
	}
	
    /**
     * Глобальный номер задания
     * @param mixed $idInProject 
     * @return $issueId
     */
    private function getCurentIssueId($idInProject)
    {
        $sql = "SELECT `id` FROM `%s` WHERE `idInProject` = '" . $idInProject . "' " .
										   "AND `projectId` = '" . $this->_project->id . "'";
        if (!$query = $this->_db->queryt( $sql, LPMTables::ISSUES )) {
            return $engine->addError( 'Ошибка доступа к базе' );
        }else{
            $result = $query->fetch_assoc();
            return $result['id'];
        }        
    }
    
    /**
     * Номер последнего задания в проекте
     * @return idInProject
     */
    private function getLastIssueId() 
    {
        $sql = "SELECT MAX(`idInProject`) AS maxID FROM `%s` " .
               "WHERE `projectId` = '" . $this->_project->id . "'";
        if(!$query = $this->_db->queryt($sql, LPMTables::ISSUES)){
            return $engine->addError( 'Ошибка доступа к базе' );
        }
        
        if ($query->num_rows == 0) {
            return 1;
        }else{
            $result = $query->fetch_assoc();
            return $result['maxID'] + 1;
        }    
    }
    
	private function saveIssue( $editMode = false ) {
		$engine = $this->_engine;
		// если это ректирование, то проверим идентификатор задача
		// соответствие её проекту и права пользователя
		if ($editMode) {
			$issueId = (float)$_POST['issueId'];
			
			// проверяем что такая задача есть и она принадлежит текущему проекту
			$sql = "SELECT `id`, `idInProject` FROM `%s` WHERE `id` = '" . $issueId . "' " .
										   "AND `projectId` = '" . $this->_project->id . "'";
			if (!$query = $this->_db->queryt( $sql, LPMTables::ISSUES )) {
				return $engine->addError( 'Ошибка записи в базу' );
			}
			
			if ($query->num_rows == 0) 
				return $engine->addError( 'Нет такой задачи для текущего проекта' );
            $result = $query->fetch_assoc(); 
			$idInProject = $result['idInProject'];
			// TODO проверка прав
			
		} else {
            $issueId = 'NULL';
            $idInProject = (int)$this->getLastIssueId();
        }
		
		if (empty( $_POST['name'] ) || !isset( $_POST['members'] )
		|| !isset( $_POST['type'] ) || empty( $_POST['completeDate'] )
		|| !isset( $_POST['priority'] ) )  {
			$engine->addError( 'Заполнены не все обязательные поля' );
		} elseif (preg_match( "/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/",
					$_POST['completeDate'], $completeDateArr ) == 0 ) {
			$engine->addError( 'Недопустимый формат даты. Требуется формат ДД/ММ/ГГГГ' );
		} elseif ($_POST['type'] != Issue::TYPE_BUG
					&& $_POST['type'] != Issue::TYPE_DEVELOP) {
			$engine->addError( 'Недопустимый тип' );
		} elseif (!is_array( $_POST['members'] ) || count( $_POST['members'] ) == 0 ) {
			$engine->addError( 'Необходимо указать хотя бы одного исполнителя проекта' );
		} elseif ($_POST['priority'] < 0 || $_POST['priority'] > 99) {
			$engine->addError( 'Недопустимое значение приоритета' );
		} else {
			$_POST['desc'] = str_replace( '%', '%%', $_POST['desc'] );
			$_POST['hours']= str_replace( '%', '%%', $_POST['hours'] );
			$_POST['name'] = str_replace( '%', '%%', $_POST['name'] );
			foreach ($_POST as $key => $value) {
				if ($key != 'members')
				$_POST[$key] = $this->_db->escape_string( $value );
			}
			$_POST['type'] = (int)$_POST['type'];
			
			$completeDate = $completeDateArr[3] . '-' . 
							$completeDateArr[2] . '-' .
							$completeDateArr[1] . ' ' .
							'00:00:00';
			$priority = min( 99, max( 0, (int)$_POST['priority'] ) );
            
			// сохраняем задачу
			$sql = "INSERT INTO `%s` (`id`, `projectId`, `idInProject`, `name`, `hours`, `desc`, `type`, " .
			                          "`authorId`, `createDate`, `completeDate`, `priority` ) " .
			           		 "VALUES (". $issueId . ", '" . $this->_project->id . "', '" . $idInProject . "', " .
			           		 		  "'" . $_POST['name'] . "', '" . $_POST['hours'] . "', '" . $_POST['desc'] . "', " .
			           		 		  "'" . (int)$_POST['type'] . "', " .
			           		 		  "'" . $engine->getAuth()->getUserId() . "', " .
									  "'" . DateTimeUtils::mysqlDate() . "', " .
									  "'" . $completeDate . "', " . 
									  "'" . $priority . "' ) " .
			"ON DUPLICATE KEY UPDATE `name` = VALUES( `name` ), " .
									"`hours` = VALUES( `hours` ), " .
									"`desc` = VALUES( `desc` ), " .
									"`type` = VALUES( `type` ), " .
									"`completeDate` = VALUES( `completeDate` ), " .
									"`priority` = VALUES( `priority` )";			
			$members = array();
			if (!$this->_db->queryt( $sql, LPMTables::ISSUES )) {
				$engine->addError( 'Ошибка записи в базу' );
			} else {
				if (!$editMode) $issueId = $this->_db->insert_id;
				else {
					// выберем из базы текущих участников задачи
					$sql = "SELECT `userId` FROM `%s` " .
							"WHERE `instanceType` = '" . Issue::ITYPE_ISSUE . "' " .
						      "AND `instanceId` = '" . $issueId . "'";
					if (!$query = $this->_db->queryt( $sql, LPMTables::MEMBERS )) {
						return $engine->addError( 'Ошибка загрузки участников' );
					}
					
					$users4Delete = array();
					
					while ($row = $query->fetch_assoc()) {
						$tmpId = (float)$row['userId'];
						$memberInArr = false;
						foreach ($_POST['members'] as $i => $memberId) {													
							if ($memberId == $tmpId) {
								ArrayUtils::removeByIndex( $_POST['members'], $i );
								array_push( $members, (float)$memberId );
								$memberInArr = true;
								break;
							}
						}
						if (!$memberInArr) array_push( $users4Delete, $tmpId );
					}
					
					if (count( $users4Delete ) > 0 && 
							!$this->_db->queryt( 
								"DELETE FROM `%s` " .
								 "WHERE `instanceType` = '" . Issue::ITYPE_ISSUE . "' " .
						      	   "AND `instanceId` = '" . $issueId . "' " .
						      	   "AND `userId` IN (" . implode( ',', $users4Delete ) . ")", 
								LPMTables::MEMBERS 
							)
					   ) 
					{
						return $engine->addError( 'Ошибка при сохранении участников' );
					}
				}
				
				// сохраняем исполнителей задачи
				$sql = "INSERT INTO `%s` ( `userId`, `instanceType`, `instanceId` ) " .
							     "VALUES ( ?, '" . Issue::ITYPE_ISSUE . "', '" . $issueId . "' )";
					
				if (!$prepare = $this->_db->preparet( $sql, LPMTables::MEMBERS )) {
					if (!$editMode)
						$this->_db->queryt( "DELETE FROM `%s` WHERE `id` = '" . $issueId . "'", 
											LPMTables::ISSUES );
					$engine->addError( 'Ошибка при сохранении участников' );
				} else {
					$saved = array();
					foreach ($_POST['members'] as $memberId) {
						$memberId = (float)$memberId;
						array_push( $members, $memberId );
						if (!in_array( $memberId, $saved )) {
							$prepare->bind_param( 'd', $memberId );
							$prepare->execute();
							array_push( $saved, $memberId );
						}
					}
					$prepare->close();


					//удаление старых изображений
					if (!empty($_POST["removedImages"]))
					{
						$delImg = $_POST["removedImages"];
						$delImg = explode(',', $delImg);
						$imgIds = array();
						foreach ($delImg as $imgIt) {
							$imgIt = (int)$imgIt;
							if ($imgIt > 0) $imgIds[] = $imgIt;

						}
						if (!empty($imgIds)){

							$sql = "UPDATE `%s` ". 
										"SET `deleted`='1' ".
										"WHERE `imgId` IN (".implode(',',$imgIds).") ".
										 "AND `deleted` = '0' ".
										 "AND `itemId`='".$issueId."' ".
										 "AND `itemType`='".Issue::ITYPE_ISSUE."'";
							$this->_db->queryt($sql, LPMTables::IMAGES);
						}
						
					}


					// загружаем изображения
					$uploader = $this->saveImages4Issue( $issueId );

					if ($uploader === false)
					{
						$engine->addError( 'Не удалось загрузить изображение' );
						return;
					}

					// обновляем счетчики
					if ($uploader->getLoadedCount() > 0 || $editMode) 
						Issue::updateImgsCounter( $issueId, $uploader->getLoadedCount() );
					
					$issueURL = $this->getBaseUrl( ProjectPage::PUID_ISSUE, $issueId );
					
					// отсылаем оповещения
					if ($issue = Issue::load( $issueId )) {
						if ($editMode) {
							array_push( $members, $issue->authorId );
							EmailNotifier::getInstance()->sendMail2Allowed(
								'Изменена задача "' . $issue->name . '"', 
								$engine->getUser()->getName() . ' изменил задачу "' .
								$issue->name .  '", в которой Вы принимаете участие' . "\n" .
								'Просмотреть задачу можно по ссылке ' .	$issueURL, 
								$members,
								EmailNotifier::PREF_ADD_ISSUE
							);
						} else {
							EmailNotifier::getInstance()->sendMail2Allowed(
								'Добавлена задача "' . $issue->name . '"', 
								$engine->getUser()->getName() . ' добавил задачу "' . 
								$issue->name .  '", в которой Вы назначены исполнителем' . "\n" . 
								'Просмотреть задачу можно по ссылке ' .	$issueURL, 
								$members, 
								EmailNotifier::PREF_ADD_ISSUE
							);
						}	

						Project::updateIssuesCount(  $issue->projectId );				
					}
					
					LightningEngine::go2URL( 
						$editMode 
							? $issueURL
							: $this->_project->getUrl() 
					);
				}
			}
		}
	}

	private function saveImages4Issue( $issueId ) 
	{
		$uploader = new LPMImgUpload( 
			Issue::MAX_IMAGES_COUNT, 
			true,
            array( LPMImg::PREVIEW_WIDTH, LPMImg::PREVIEW_HEIGHT ), 
            'issues', 
            'scr_',
           	Issue::ITYPE_ISSUE, 
           	$issueId
        );  

        if ($uploader->isErrorsExist()) {
            $errors = $uploader->getErrors();
            $this->_engine->addError( $errors[0] );
            return false;
        }
            
        //if ($uploader->getLoadedCount() == 0) return $uploader;
            
        return $uploader;    
	}
}
?>