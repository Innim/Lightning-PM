<?php
class ProjectPage extends BasePage
{
	const UID = 'project';
	const PUID_MEMBERS = 'members';
	const PUID_ISSUES  = 'issues';
	const PUID_COMPLETED_ISSUES  = 'completed';
	const PUID_COMMENTS  = 'comments';
	const PUID_ISSUE   = 'issue';
	const PUID_SCRUM_BOARD = 'scrum_board';
	const PUID_SCRUM_BOARD_SNAPSHOT = 'scrum_board_snapshot';

	/**
	 * 
	 * @var Project
	 */
	private $_project;
	private $_currentPage;

	function __construct()
	{
		parent::__construct( self::UID, '', true, true );
		
		array_push( $this->_js,'libs/jquery.zclip', 'project', 'issues');
		$this->_pattern = 'project';
		
		$this->_baseParamsCount = 2;
		$this->_defaultPUID     = self::PUID_ISSUES;

		$this->addSubPage(self::PUID_ISSUES , 'Список задач');
		$this->addSubPage(self::PUID_COMPLETED_ISSUES , 'Завершенные');
		$this->addSubPage(self::PUID_COMMENTS , 'Комментарии', 'project-comments');
		$this->addSubPage(self::PUID_MEMBERS, 'Участники', 'project-members', 
				array('users-chooser'), '', User::ROLE_MODERATOR);
	}
	
	public function init() {
		$engine = LightningEngine::getInstance();

		// загружаем проект, на странице которого находимся
		if ($engine->getParams()->suid == '' 
			|| !$this->_project = Project::load($engine->getParams()->suid)) return false;

		// Если это scrum проект - добавляем новый подраздел
		if ($this->_project->scrum)
		{
			$this->addSubPage(self::PUID_SCRUM_BOARD, 'Scrum доска', 'scrum-board');
            $this->addSubPage(self::PUID_SCRUM_BOARD_SNAPSHOT, 'Scrum архив', 'scrum-board-snapshot');
		}

		if (!parent::init())
            return false;
		
		// проверим, можно ли текущему пользователю смотреть этот проект
		if (!$user = LightningEngine::getInstance()->getUser())
            return false;

		if (!$user->isModerator()) {
			$sql = "SELECT `instanceId` FROM `%s` " .
			                 "WHERE `instanceId`   = '" . $this->_project->id . "' " .
							   "AND `instanceType` = '" . LPMInstanceTypes::PROJECT . "' " .
							   "AND `userId`       = '" . $user->userId . "'";

			if (!$query = $this->_db->queryt( $sql, LPMTables::MEMBERS ))
                return false;

			if ($query->num_rows == 0)
                return false;
		}
		
		$iCount = (int)$this->_project->getImportantIssuesCount();
		if ($iCount > 0)
		{
			$issuesSubPage = $this->getSubPage(self::PUID_ISSUES);
			$issuesSubPage->link->label .= " (" . $iCount . ")";
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
				$issue->getTesters();
				
				Comment::setCurrentInstance( LPMInstanceTypes::ISSUE, $issue->id );

				$this->_title  =$issue->name .' - '. $this->_project->name ;
				$this->_pattern = 'issue';
				ArrayUtils::remove( $this->_js,	'project' );
				array_push( $this->_js,	'issue' );

				$this->addTmplVar('issue', $issue);
			} 
		}

		// загружаем задачи
		if (!$this->_curSubpage || $this->_curSubpage->uid == self::PUID_ISSUES) 
		{			
			$this->addTmplVar('issues', Issue::loadListByProject( $this->_project->id,array(Issue::STATUS_IN_WORK,Issue::STATUS_WAIT) ));	
		}
		// загружаем  завершенные задачи
		else if ($this->_curSubpage->uid == self::PUID_COMPLETED_ISSUES) 
		{			
			$this->addTmplVar('issues', Issue::loadListByProject(
				$this->_project->id, array( Issue::STATUS_COMPLETED )));	
		}
		else if ($this->_curSubpage->uid == self::PUID_COMMENTS) 
		{
			$page = $this->getProjectedCommentsPage();
			$commentsPerPage = 100;

			$this->_currentPage = $page;

			$comments = Comment::getIssuesListByProject($this->_project->id, 
					($page - 1) * $commentsPerPage, $commentsPerPage);
			$issueIds = [];
			$commentsByIssueId = [];
			foreach ($comments as $comment) {
				if (!isset($commentsByIssueId[$comment->instanceId])) {
					$commentsByIssueId[$comment->instanceId] = [];
					$issueIds[] = $comment->instanceId;
				} 
				$commentsByIssueId[$comment->instanceId][] = $comment;
			}

			$issues = Issue::loadListByIds($issueIds);
			foreach ($issues as $issue) {
				if (isset($commentsByIssueId[$issue->id])) {
					foreach ($commentsByIssueId[$issue->id] as $comment) {
						$comment->issue = $issue;
					}
				}
			}

			$this->addTmplVar('project', $this->_project);
			$this->addTmplVar('comments', $comments);
			$this->addTmplVar('page', $page);
			if ($page > 1)
				$this->addTmplVar('prevPageUrl', $this->getUrl('page', $page - 1));
			// Упрощенная проверка, да, есть косяк если общее кол-во комментов делиться нацело 
			if (count($comments) === $commentsPerPage)
				$this->addTmplVar('nextPageUrl', $this->getUrl('page', $page + 1));
		}
		else if ($this->_curSubpage->uid == self::PUID_SCRUM_BOARD) 
		{
			$this->addTmplVar('project', $this->_project);
			$this->addTmplVar('stickers', ScrumSticker::loadList($this->_project->id));
		} else if ($this->_curSubpage->uid == self::PUID_SCRUM_BOARD_SNAPSHOT) {
            $this->addTmplVar('project', $this->_project);
            $snapshots = ScrumStickerSnapshot::loadList($this->_project->id);
            $this->addTmplVar('snapshots', $snapshots);

            $sid = (int) $this->getParam(3);

            if ($sid > 0)
            {
                foreach ($snapshots as $snapshot) {
                    if ($snapshot->id == $sid) {
                        $this->addTmplVar('snapshot', $snapshot);
                        break;
                    }
                }
            }
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
			// TODO наверное нужен "белый список" тегов
			$_POST['desc'] = str_replace( '%', '%%', $_POST['desc'] );
			$_POST['hours']= str_replace( '%', '%%', $_POST['hours'] );
			$_POST['name'] = str_replace( '%', '%%', $_POST['name'] );

			foreach ($_POST as $key => $value) {
				if ($key != 'members' && $key != 'clipboardImg' && $key != 'imgUrls' && $key != 'testers')
					$_POST[$key] = $this->_db->real_escape_string( $value );
			}

			$_POST['type'] = (int)$_POST['type'];
			
			$completeDate = $completeDateArr[3] . '-' . 
							$completeDateArr[2] . '-' .
							$completeDateArr[1] . ' ' .
							'00:00:00';
			$priority = min( 99, max( 0, (int)$_POST['priority'] ) );

			// из дробных разрешаем только 1/2
            $hours = $_POST['hours'];
            $hours = ($hours == "0.5" || $hours == "0,5" || $hours == "1/2") ? 0.5 : (int)$hours;

			// сохраняем задачу
			$sql = "INSERT INTO `%s` (`id`, `projectId`, `idInProject`, `name`, `hours`, `desc`, `type`, " .
			                          "`authorId`, `createDate`, `completeDate`, `priority` ) " .
			           		 "VALUES (". $issueId . ", '" . $this->_project->id . "', '" . $idInProject . "', " .
			           		 		  "'" . $_POST['name'] . "', '" . $hours . "', '" . $_POST['desc'] . "', " .
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
			$testers = array();

			if (!$this->_db->queryt( $sql, LPMTables::ISSUES )) {
				$engine->addError( 'Ошибка записи в базу' );
			} else {
				if (!$editMode) $issueId = $this->_db->insert_id;
				else {
					// выберем из базы текущих участников задачи
					$sql = "SELECT `userId` FROM `%s` " .
							"WHERE `instanceType` = '" . LPMInstanceTypes::ISSUE . "' " .
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
					
					if (count($users4Delete) > 0 && 
							!Member::deleteIssueMembers($issueId, $users4Delete)) 
						return $engine->addError('Ошибка при сохранении участников');

                    // выборка тестеров задачи
                    $sql = "SELECT `userId` FROM `%s` " .
                        "WHERE `instanceType` = '" . LPMInstanceTypes::ISSUE_FOR_TEST . "' " .
                        "AND `instanceId` = '" . $issueId . "'";
                    if (!$query = $this->_db->queryt( $sql, LPMTables::MEMBERS )) {
                        return $engine->addError( 'Ошибка загрузки тестеров' );
                    }

                    $users4Delete = array();

                    while ($row = $query->fetch_assoc()) {
                        $tmpId = (float)$row['userId'];
                        $memberInArr = false;
                        if (!empty($_POST['testers'])) {
                            foreach ($_POST['testers'] as $i => $testerId) {
                                if ($testerId == $tmpId) {
                                    ArrayUtils::removeByIndex($_POST['testers'], $i);
                                    array_push($members, (float)$testerId);
                                    $memberInArr = true;
                                    break;
                                }
                            }
                        }
                        if (!$memberInArr)
                            array_push($users4Delete, $tmpId);
                    }

                    if (count($users4Delete) > 0 && !Member::deleteIssueTesters($issueId, $users4Delete))
                        return $engine->addError('Ошибка при удалении старых тестеров');
				}

				// сохраняем исполнителей задачи
				$sql = "INSERT INTO `%s` ( `userId`, `instanceType`, `instanceId` ) " .
							     "VALUES ( ?, '" . LPMInstanceTypes::ISSUE . "', '" . $issueId . "' )";
					
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

					if (!empty($_POST['testers'])) {
                        // Сохранение тестеров задачи
                        $sql = "INSERT INTO `%s` ( `userId`, `instanceType`, `instanceId` ) " .
                            "VALUES ( ?, '" . LPMInstanceTypes::ISSUE_FOR_TEST . "', '" . $issueId . "' )";

                        if (!$prepare = $this->_db->preparet( $sql, LPMTables::MEMBERS )) {
                            $engine->addError( 'Ошибка при сохранении тестеров' );
                        } else {
                            $saved = array();
                            foreach ($_POST['testers'] as $testerId) {
                                $testerId = (float)$testerId;
                                array_push($testers, $testerId);
                                if (!in_array($testerId, $saved)) {
                                    $prepare->bind_param('d', $testerId);
                                    $prepare->execute();
                                    array_push($saved, $testerId);
                                }
                            }
                            $prepare->close();
                        }
                    }

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
										 "AND `itemType`='".LPMInstanceTypes::ISSUE."'";
							$this->_db->queryt($sql, LPMTables::IMAGES);
						}
					}
					// загружаем изображения
					//если задача редактируется
					if ($editMode) {
						//считаем из базы кол-во картинок, имеющихся для задачи
						$sql = "SELECT COUNT(*) AS `cnt` FROM `%s` " .
							"WHERE `itemId` = '" . $issueId. "'".
							"AND `itemType` = '" . LPMInstanceTypes::ISSUE . "' " .
							"AND `deleted` = '0'";
						
						if ($query = $this->_db->queryt($sql, LPMTables::IMAGES)) 
						{
							$row =  $query->fetch_assoc();
							$loadedImgs = (int)$row['cnt'];
						}
						else 
						{
							$engine->addError('Ошибка доступа к БД. Не удалось загрузить количество изображений');
							return;
						}
					}
					//если добавляется
					else
						$loadedImgs = 0;

					$uploader = $this->saveImages4Issue( $issueId, $loadedImgs);

					if ($uploader === false)
						return $engine->addError( 'Не удалось загрузить изображение' );

					$issue = Issue::load($issueId);
					if (!$issue) {
						$engine->addError('Не удалось загрузить данные задачи');
						return;
					}

					// Если это SCRUM проект
					if ($this->_project->scrum) {
						$putOnBoard = !empty($_POST['putToBoard']);
						if ($issue->isOnBoard() != $putOnBoard) {
							if ($putOnBoard) {
								if (!ScrumSticker::putStickerOnBoard($issue))
									return $engine->addError('Не удалось поместить стикер на доску');
							} else {
								if (!ScrumSticker::updateStickerState($issue->id, 
										ScrumStickerState::BACKLOG))
									return $engine->addError('Не удалось снять стикер с доски');
							}
						}
					}

					// обновляем счетчики
					if ($uploader->getLoadedCount() > 0 || $editMode) 
						Issue::updateImgsCounter( $issueId, $uploader->getLoadedCount() );
					
					$issueURL = $this->getBaseUrl( ProjectPage::PUID_ISSUE, $idInProject );
					
					// отсылаем оповещения
					if ($editMode) {
						array_push( $members, $issue->authorId ); // TODO фильтр, чтобы не добавлялся дважды
						EmailNotifier::getInstance()->sendMail2Allowed(
							'Изменена задача "' . $issue->name . '"', 
							$engine->getUser()->getName() . ' изменил задачу "' .
							$issue->name .  '", в которой Вы принимаете участие' . "\n" .
							'Просмотреть задачу можно по ссылке ' .	$issueURL, 
							$members,
							EmailNotifier::PREF_EDIT_ISSUE
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

					Project::updateIssuesCount($issue->projectId);
				
					LightningEngine::go2URL($issueURL);
					// LightningEngine::go2URL( 
						// $editMode 
							// ? $issueURL
							// : $this->_project->getUrl() 
					// );
				}
			}
		}
	}

	private function getProjectedCommentsPage() {
		// $page = $this->getParam($this->_baseParamsCount + 1);
		// return empty($page) ? 1 : (int)$page;
		return $this->getPageArg();
	}

	private function saveImages4Issue( $issueId, $hasCnt = 0 ) 
	{
		$uploader = new LPMImgUpload( 
			Issue::MAX_IMAGES_COUNT - $hasCnt, 
			true,
            array( LPMImg::PREVIEW_WIDTH, LPMImg::PREVIEW_HEIGHT ), 
            'issues', 
            'scr_',
			LPMInstanceTypes::ISSUE, 
           	$issueId,
           	false
        );  

        // Выполняем загрузку для изображений из поля загрузки
        // Вставленных из буфера
        // И добавленных по URL
        if (!$uploader->uploadViaFiles('images') ||
        	isset($_POST['clipboardImg']) && !$uploader->uploadFromBase64($_POST['clipboardImg']) ||
        	isset($_POST['imgUrls']) && !$uploader->uploadFromUrls($_POST['imgUrls']))
        {
        	$errors = $uploader->getErrors();
            $this->_engine->addError($errors[0]);
            return false;
        }
        return $uploader;    
	}
}
?>