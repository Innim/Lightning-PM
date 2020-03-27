<?php
/**
 * Внешнее API для обработки hook событий от GitLab.
 *
 * Для подключения надо вручную добавить нужный хук в Admin Area > System Hooks.
 *
 * URL: https://task_url/api/gitlab/
 * Secret Token: То, что передается в конструктор этого класса присоздании.
 * Trigger: Merge request events.
 *
 * // TODO: сделать автоматическое создание хука
 */
class GitlabExternalApi extends ExternalApi {
	const UID = 'gitlab';

	const FIELD_OBJECT_KIND = 'object_kind'; 
	const FIELD_OBJECT_ATTRIBUTES = 'object_attributes'; 
	const EVENT_TYPE_MR = 'merge_request';

	private $_token;

	function __construct($token) {
		parent::__construct(self::UID);

		$this->_token = $token;
	}

	public function run($input) {
		try {
			if (!$this->checkToken())
				throw new Exception('Token validation failed');

			$data = json_decode($input, true);
			if (!$data)
				throw new Exception("Can't parse input");

			if (empty($data['event_type']))
				throw new Exception("Can't find event_type field");

			$event = $data['event_type'];
			switch ($event) {
				case self::EVENT_TYPE_MR:
					return $this->onMREvent($data);
				default:
					throw new Exception("Unhandled event " . $event);
			}
		} catch (Exception $e) {
			return $this->onException($e);
		}
	}

	private function checkToken() {
		if (empty($this->_token))
			return true;

		return !empty($_SERVER['HTTP_X_GITLAB_TOKEN']) &&
			$_SERVER['HTTP_X_GITLAB_TOKEN'] == $this->_token;
	}

	private function onException(Exception $e) {
		// TODO: лог
		// TODO: формат ошибки
		return $e->getMessage();
	}

	private function onMREvent($data) {
		if (!isset($data[self::FIELD_OBJECT_KIND], $data[self::FIELD_OBJECT_ATTRIBUTES]))
			throw new Exception("Invalid data: there is no object data");

		if ($data[self::FIELD_OBJECT_KIND] != 'merge_request')
			throw new Exception("Invalid object kind: " . $data[self::FIELD_OBJECT_KIND]);

		$mr = new GitlabMergeRequest($data[self::FIELD_OBJECT_ATTRIBUTES]);

		// Если MR был влит, то возможно надо оповестить тестировщика
		if ($mr->isMerged()) {
			// Загружаем задачи по MR
			$issueIds = IssueMR::loadIssueIdsForOpenedMr($mr->id);
			if (!empty($issueIds)) {
				$slack = SlackIntegration::getInstance();
				foreach ($issueIds as $issueId) {
					$issue = Issue::load($issueId);
					if (empty($issueId) || $issue->status != Issue::STATUS_WAIT)
						continue;

					$testers = $issue->getTesters();
					if (!empty($testers))
						$slack->notifyMRMergedToTester($issue, $mr);
				}
			}
		}

		// Обновляем статус MR
		IssueMR::updateState($mr->id, $mr->state);
	}
}