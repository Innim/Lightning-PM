<?php
require_once(dirname( __FILE__ ) . '/../init.inc.php');
use \GMFramework\DateTimeUtils as DTU;

class IssueService extends LPMBaseService
{
	/**
	 * Завершаем задачу
	 * @param  int $issueId 
	 */
	public function complete($issueId) {
		// завершать задачу может создатель задачи,
		// исполнитель задачи или модератор
		$issue = Issue::load((float)$issueId);
		if (!$issue) 
			return $this->error('Нет такой задачи');
		
		if (!$issue->checkEditPermit($this->_auth->getUserId())) 
			return $this->error('У Вас нет прав на редактирование этой задачи');

		try {
			Issue::updateStatus($this->getUser(), $issue, Issue::STATUS_COMPLETED);
		} catch (Exception $e) {
			return $this->exception($e);
		}

		// Менять состояние стикера может любой пользователь
		if ($issue->isOnBoard() && 
				!ScrumSticker::updateStickerState($issue->id, ScrumStickerState::DONE))
        	return $this->errorDBSave();
		
		$this->add2Answer('issue', $this->getIssue4Client($issue));
		
		return $this->answer();
	}
	
	/**
	 * Восстанавливаем задачу
	 * @param float $issueId
	 */
	public function restore($issueId) {
		// востанавливать задачу может создатель задачи,
		// исполнитель задачи или модератор
		$issue = Issue::load((float)$issueId);
		if (!$issue) 
			return $this->error('Нет такой задачи');
		
		if (!$issue->checkEditPermit($this->_auth->getUserId())) 
			return $this->error('У Вас нет прав на редактирование этой задачи');

		try {
			Issue::updateStatus($this->getUser(), $issue, Issue::STATUS_IN_WORK);
		} catch (Exception $e) {
			return $this->exception($e);
		}

		// Менять состояние стикера может любой пользователь
		if ($issue->isOnBoard() && 
				!ScrumSticker::updateStickerState($issue->id, ScrumStickerState::IN_PROGRESS))
        	return $this->errorDBSave();
		
		$this->add2Answer('issue', $this->getIssue4Client($issue));
	
		return $this->answer();
	}

	/**
	 * Ставим задачу на проверку
	 * @param float $issueId
	 */
	public function verify($issueId) {
		// ставить задачу на проверку может исполнитель задачи
		$issue = Issue::load((float)$issueId);
		if (!$issue) 
			return $this->error('Нет такой задачи');
		
		if (!$issue->checkEditPermit($this->_auth->getUserId())) 
			return $this->error('У Вас нет прав на редактирование этой задачи');

		try {
			Issue::updateStatus($this->getUser(), $issue, Issue::STATUS_WAIT);
		} catch (Exception $e) {
			return $this->exception($e);
		}

		// Менять состояние стикера может любой пользователь
		if ($issue->isOnBoard() && 
				!ScrumSticker::updateStickerState($issue->id, ScrumStickerState::TESTING))
        	return $this->errorDBSave();

		$this->add2Answer('issue', $this->getIssue4Client($issue));
	
		return $this->answer();
	}
	
	/**
	 * Загружает информацию о задаче
	 * @param float $issueId
	 */
	public function load($issueId) {
		if (!$issue = Issue::load((float)$issueId)) 
			return $this->error('Нет такой задачи');
		
		// TODO проверка на возможность просмотра
		
		/*$obj = $issue->getClientObject();
		$members = $issue->getMembers();
		$obj['members'] = array();

		foreach ($members as $member) {
			array_push( $obj['members'], $member->getClientObject() );
		}*/				
		
		$this->add2Answer('issue', $this->getIssue4Client($issue));
		return $this->answer();
	}

    /**
     * Загружает информацию о задаче
     * @param float $idInProject
     * @param int $projectId
     * @return array
     */
    public function loadByIdInProject($idInProject, $projectId) {
    	$projectId = (int) $projectId;

        if (!$issue = Issue::loadByIdInProject($projectId, (float) $idInProject))
            return $this->error('Нет такой задачи');

        // TODO проверка на возможность просмотра

        $this->add2Answer('issue', $this->getIssue4Client($issue));
        return $this->answer();
    }
	
	/**
	 * Удаляет задачу
	 * @param float $issueId
	 */
	public function remove($issueId) {
		$issueId = (float)$issueId;
		// удалять задачу может создатель задачи или модератор
		if (!$issue = Issue::load((float)$issueId)) 
			return $this->error('Нет такой задачи');
		
		// TODO проверка прав
		//if (!$issue->checkEditPermit( $this->_auth->getUserId() ))
		//return $this->error( 'У Вас нет прав на редактирование этой задачи' );
		
		// отправка оповещений
		$members = $issue->getMemberIds();
		array_push( $members, $issue->authorId );
		
		EmailNotifier::getInstance()->sendMail2Allowed(
			'Удалена задача "' . $issue->name . '"', 
			$this->getUser()->getName() . ' удалил задачу "' . $issue->name .  '"', 
			$members,
			EmailNotifier::PREF_ISSUE_STATE
		);
		
		$sql = "update `%s` set `deleted` = '1' where `id` = '" . $issueId . "'";
		if (!$this->_db->queryt( $sql, LPMTables::ISSUES )) return $this->errorDBSave();

		Project::updateIssuesCount(  $issue->projectId );
		
		return $this->answer();
	}
	
	public function comment( $issueId, $text ) {
		$issueId = (int)$issueId;

		try {
	        $issue = Issue::load($issueId);
	        if (!$issue)
	        	return $this->error('Нет такой задачи');

			$comment = $this->postComment($issue, $text);

	        $this->add2Answer('comment', $issue->getClientObject());
	    } catch (\Exception $e) { 
	        return $this->exception($e); 
	    }

		return $this->answer();
	}

	/**
	 * Отмечает что задача прошла тестирование.
	 * @param  int $issueId Идентификатор задачи
	 * @return {
	 *     string comment Добавленный комментарий.
	 * }
	 */
	public function passTest($issueId) {
		$issueId = (int)$issueId;

		try {
	        $issue = Issue::load($issueId);
	        if (!$issue)
	        	return $this->error('Нет такой задачи');

			$comment = $this->postComment($issue, 'Прошла тестирование');

			// Отправляем оповещенив в slack
			$slack = SlackIntegration::getInstance();
			$slack->notifyIssuePassTest($issue);

	        $this->add2Answer('comment', $comment->getClientObject());
	    } catch (\Exception $e) { 
	        return $this->exception($e); 
	    } 

		return $this->answer();
	}

	/**
	 * Меняет приоритет задачи.
	 * @param  int $issueId Идентификатор задачи
	 * @param  int $delta Изменение приоритета.
	 * @return {
	 *     int priority Новое значение приоритета.
	 * }
	 */
	public function changePriority($issueId, $delta) {
		$issueId = (int)$issueId;
		$delta   = (int)$delta;

	    try {
	        $issue = Issue::load($issueId);
	        if (!$issue)
	        	return $this->error('Нет такой задачи');
	        Issue::changePriority($issue, $delta);

	        $this->add2Answer('priority', $issue->priority);
	    } catch (\Exception $e) { 
	        return $this->exception($e); 
	    } 
	
	    return $this->answer();
	}

	/**
	 * Изменяет состояние стикера
	 * @param  int $issueId Идентификатор задачи
	 * @param  int $state   Новое состояние стикера
	 * @return 
	 */
	public function changeScrumState($issueId, $state) {
		$issueId = (int)$issueId;
		$state   = (int)$state;

	    try {
	    	// Проверяем состояние 
	    	if (!ScrumStickerState::validateValue($state))
	    		throw new Exception('Неизвестный стейт');
	    	 
	        $sticker = ScrumSticker::load($issueId);
	        if ($sticker === null)
	        	throw new Exception('Нет стикера для этой задачи');

			// Менять состояние стикера может любой пользователь
	        if (!ScrumSticker::updateStickerState($issueId, $state))
	        	return $this->errorDBSave();

	        $issue = $sticker->getIssue();
	        if ($state === ScrumStickerState::TESTING) {
	        	// Если состояние "Тестируется" - ставим задачу на проверку
				Issue::updateStatus($this->getUser(), $issue, Issue::STATUS_WAIT);
	        } else if ($state === ScrumStickerState::DONE) {
	        	// Если "Готово" - закрываем задачу
				Issue::updateStatus($this->getUser(), $issue, Issue::STATUS_COMPLETED);
	        } else if ($issue->status == Issue::STATUS_WAIT && 
	        		($state === ScrumStickerState::TODO || $state === ScrumStickerState::IN_PROGRESS)) {
				// Если она в режиме ожидания - переоткрываем задачу
				Issue::updateStatus($this->getUser(), $issue, Issue::STATUS_IN_WORK);
	        }
	    } catch (\Exception $e) { 
	        return $this->exception($e); 
	    } 
	
	    return $this->answer();
	}

	/**
	 * Помещает стикер задачи на скрам доску
	 * @param  int $issueId Идентификатор задачи
	 * @return 
	 */
	public function putStickerOnBoard($issueId) {
		$issueId = (int)$issueId;

	    try {
	    	$issue = Issue::load($issueId);
			if ($issue === null) 
				return $this->error('Нет такой задачи');

	        if (!ScrumSticker::putStickerOnBoard($issue))
	        	return $this->errorDBSave();
	    } catch (\Exception $e) { 
	        return $this->exception($e); 
	    } 
	
	    return $this->answer();
	}

	/**
	 * Убирает в архив стикеры с доски
	 * @param int $projectId Идентификатор проекта
	 * @return 
	 */
	public function removeStickersFromBoard($projectId) {
		$projectId = (int)$projectId;

	    try {
			// прежде чем отправлять все задачи в архив, делаем snapshot доски
			ScrumStickerSnapshot::createSnapshot($projectId, $this->getUser()->userId);

			$state = ScrumStickerState::ARCHIVED;
	    	$activeStates = implode(',', ScrumStickerState::getActiveStates());

	    	$db = $this->_db;
	    	$sql = <<<SQL
	    	UPDATE `%1\$s` `s`
    	INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id`
	    	   SET `s`.`state` = ${state}
	    	 WHERE `s`.`state` IN (${activeStates}) 
	    	   AND `i`.`projectId` = ${projectId}
SQL;
			if (!$db->queryt($sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES))
				return $this->errorDBSave();
	    } catch (\Exception $e) { 
	        return $this->exception($e); 
	    } 
	
	    return $this->answer();
	}

	/**
	 * Забрать задачу себе. Удаляет других исполнителей, 
	 * оставляя только текущего 
	 * @param  int $issueId 
	 */
	public function takeIssue($issueId) {
	    $issueId = (int)$issueId;

	    try {
	        $issue = Issue::load($issueId);
			if ($issue === null) 
				return $this->error('Нет такой задачи');

			if (!Member::deleteIssueMembers($issueId))
				return $this->errorDBSave();

			if (!Member::saveIssueMembers($issueId, [$this->getUser()->userId]))
				return $this->errorDBSave();
	    } catch (\Exception $e) { 
	        return $this->exception($e); 
	    } 
	
	    return $this->answer();
	}

    /**
     * Добавляет новую метку.
     * @param $label Текст метки.
     * @param $isForAllProjects Для всех ли проектов.
     * @param $projectId Идентификатор проекта (используется в случае, если не для всех проектов).
     * @return mixed
     */
	public function addLabel($label, $isForAllProjects, $projectId) {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $projectId = $isForAllProjects ? 0 : $projectId;

        $labels = Issue::getLabelsByLabelText($label);
        $uses = 0;
        $id = 0;
        if (!empty($labels))
        {
            $count = count($labels);
            while ($count-- > 0) {
                $labelData = $labels[$count];
                if ($projectId == 0) {
                    if ($labelData['projectId'] != 0 && $labelData['deleted'] == LabelState::ACTIVE) {
                        $uses += $labelData['countUses'];
                        Issue::changeLabelDeleted($labelData['id'], LabelState::DISABLED);
                    } elseif ($labelData['projectId'] == 0) {
                        if ($labelData['deleted'] == LabelState::ACTIVE) {
                            return $this->error("Метка уже существует");
                        } else {
                            $uses += $labelData['countUses'];
                            $id = $labelData['id'];
                        }
                    }
                } elseif ($labelData['projectId'] == 0 && $labelData['deleted'] == LabelState::ACTIVE) {
                    return $this->error("Метка уже существует");
                } elseif ($labelData['projectId'] == $projectId) {
                    if ($labelData['deleted'] == LabelState::ACTIVE)
                        return $this->error("Метка уже существует");
                    else
                        $id = $labelData['id'];
                }
            }
        }

	    $id = Issue::saveLabel($label, $projectId, $id, $uses, LabelState::ACTIVE);
	    if ($id == null) {
            return $this->error($db->error);
        } else {
            $this->add2Answer('id', $id);
            return $this->answer();
        }
    }

    /**
     * Удаляет метку.
     * @param $id
     * @param $projectId
     */
    public function removeLabel($id, $projectId) {
        $label = Issue::getLabel($id);
        $projectId = (int) $projectId;

        if ($label == null)
            return $this->error("Метка не найдена.");

        $state = ($label['projectId'] == 0) ? LabelState::DISABLED : LabelState::DELETED;
        if ($label['projectId'] == 0) {
            $labels = Issue::getLabelsByLabelText($label['label']);
            if (!empty($labels)) {
                $count = count($labels);
                while ($count-- > 0) {
                    $labelData = $labels[$count];
                    if ($labelData['projectId'] == 0 && $labelData['id'] != $label['id']) {
                        Issue::changeLabelDeleted($labelData['id'], LabelState::DISABLED);
                    } elseif ($labelData['projectId'] != 0 && $labelData['deleted'] == LabelState::DISABLED) {
                        if ($labelData['projectId'] != $projectId)
                            Issue::changeLabelDeleted($labelData['id'], LabelState::ACTIVE);
                        else
                            Issue::changeLabelDeleted($labelData['id'], LabelState::DELETED);
                    }
                }
            }
        }

        if (Issue::changeLabelDeleted($label['id'], $state)) {
            return $this->answer();
        } else {
            $db = LPMGlobals::getInstance()->getDBConnect();
            return $this->error($db->error);
        }
    }

    /**
     * Экспорт завершенных задач в Excel.
     * @param  int $projectId Идентификатор проекта.
     * @param  string $fromDate Минимальная дата завершения задачи.
     * @param  string $toDate Максимальная дата завершения задачи.
     * @return {
     *    string fileUrl URL сформированного файла.
     * }
     */
    public function exportCompletedIssuesToExcel($projectId, $fromDate, $toDate) {
    	$projectId = (int) $projectId;

        try {
        	$user = $this->getUser();
        	$project = Project::loadById($projectId);

			if ($project == null)
				return $this->error("Не найден проект с идентификатором " . $projectId);
        	if (!$project->hasReadPermission($user))
				return $this->error("Нет прав на просмотр задач проекта");

			$fromDateU = strtotime($fromDate);
			$toDateU = strtotime($toDate);

			if ($fromDateU > $toDateU) {
				$tmpDate = $fromDateU;
				$fromDateU = $toDateU;
				$toDateU = $tmpDate;
			}

        	$fromCompletedDate = DTU::mysqlDate($fromDateU);
        	$toCompletedDate = DTU::mysqlDate($toDateU);
            $list = Issue::loadListByProject($projectId, array(Issue::STATUS_COMPLETED),
        		$fromCompletedDate, $toCompletedDate);

            $filename = $project->uid . '_completed_issues_' . 
            	DTU::date('ymd', $fromDateU) . '-' . DTU::date('ymd', $toDateU) . '_' . 
            	DTU::date('YmdHis');
            $exporter = new IssuesExporterToExcel($list, $filename);
            $fileUrl = $exporter->export();

            $this->add2Answer('fileUrl', $fileUrl);
        } catch (\Exception $e) { 
            return $this->exception($e); 
        } 
    
        return $this->answer();
    }
	
	/**
	 * Загружает html информации о задаче
	 * @param float $issueId
	 */
	/*public function loadHTML( $issueId ) {
		if (!$issue = Issue::load( (float)$issueId )) return $this->error( 'Нет такой задачи' );
		
		$this->add2Answer( 'issue', $issue );
		return $this->answer();
	}*/
	
	protected function getIssue4Client( Issue $issue, $loadMembers = true ) {
		$obj = $issue->getClientObject();
		$members = $issue->getMembers();
		$testers = $issue->getTesters();
		$images = $issue->getImages();
		$obj->members = array();
		$obj->testers = array();
		$obj->images = array();
		$obj->isOnBoard = $issue->isOnBoard();

		foreach ($members as $member) {
			array_push( $obj->members, $member->getClientObject() );
		}

		foreach ($testers as $tester) {
			array_push( $obj->testers, $tester->getClientObject() );
		}

		foreach ($images as $image) {
			array_push( $obj->images, array( 'imgId' => $image->imgId,
                'source' => $image->getSource(),
                'preview' => $image->getPreview()));
		}

		return $obj;
	}

	// TODO: вынести из сервиса
	private function postComment(Issue $issue, $text) {
		$issueId = $issue->id;
		if (!$comment = $this->addComment(LPMInstanceTypes::ISSUE, $issueId, $text)) 
			throw new Exception("Не удалось добавить комментарий");
		
		// отправка оповещений
		$members = $issue->getMemberIds();
		$members[] = $issue->authorId;
		
		EmailNotifier::getInstance()->sendMail2Allowed(
			'Новый комментарий к задаче "' . $issue->name . '"',
			$this->getUser()->getName() . ' оставил комментарий к задаче "' .
			$issue->name .  '":' . "\n" .
			strip_tags( $comment->text ) . "\n\n" .
			'Просмотреть все комментарии можно по ссылке ' . $issue->getConstURL(),
			$members,
			EmailNotifier::PREF_ISSUE_COMMENT
		);
		
		// обновляем счетчик коментариев для задачи
		Issue::updateCommentsCounter($issueId);

		return $comment;
	}
}
?>