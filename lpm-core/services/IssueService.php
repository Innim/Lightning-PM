<?php
require_once( dirname( __FILE__ ) . '/../init.inc.php' );

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
		$issueId = (float)$issueId;
		// удалять задачу может создатель задачи или модератор
		if (!$issue = Issue::load( (float)$issueId )) return $this->error( 'Нет такой задачи' );
		
		// TODO проверка прав
		//if (!$issue->check???Permit( $this->_auth->getUserId() ))
		//return $this->error( 'У Вас нет прав на комментировние задачи' );
		
		if (!$comment = $this->addComment( LPMInstanceTypes::ISSUE, $issueId, $text )) 
			return $this->error();				
		
		// отправка оповещений
		$members = $issue->getMemberIds();
		array_push( $members, $issue->authorId );
		
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
		Issue::updateCommentsCounter( $issueId );
		
		$this->add2Answer( 'comment', $comment->getClientObject() );
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
	 * @param  int $projectId Идентификатор проекта
	 * @return 
	 */
	public function removeStickersFromBoard($projectId) {
		$projectId = (int)$projectId;

	    try {
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
		$obj->members = array();
		
		foreach ($members as $member) {
			array_push( $obj->members, $member->getClientObject() );
		}
		
		return $obj;
	}
}
?>