<?php
/**
 * GitLab MR от исполнителей по задачам.
 */
class IssueMR extends LPMBaseObject {
	/**
	 * Загружает список идентификаторов задач для открытого MR.
	 * @param  int $mrId Идентификатор MR.
	 * @return array<int>
	 */
	public static function loadIssueIdsForOpenedMr($mrId) {
		$db = self::getDB();
		$res = $db->queryb([
			'SELECT' => 'issueId',
			'FROM'   => LPMTables::ISSUE_MR,
			'WHERE'  => [
				'mrId'  => $mrId,
				'state' => GitlabMergeRequest::STATE_OPENED
			]
		]);
		if ($res === false)
			throw new \GMFramework\ProviderLoadException();

		$list = [];
		foreach ($res as $raw) {
			$list[] = (int)$raw['issueId'];
		}

		return $list;
	}

	/**
	 * Обновляет статус Merge Request с указанным ID.
	 * @param  int    $mrId  Идентификатор MR.
	 * @param  string $state Новый статус.
	 */
	public static function updateState($mrId, $state) {
		$db = self::getDB();
		return $db->queryb([
			'UPDATE' => LPMTables::ISSUE_MR,
			'SET'    => ['state' => $state],
			'WHERE'  => ['mrId'  => $mrId],
		]);
	}

	/**
	 * Создает запись.
	 * @param  int    $mrId    Идентификатор MR.
	 * @param  int    $issueId Идентификатор задачи.
	 * @param  string $state Новый статус.
	 */
	public static function create($mrId, $issueId, $state) {
		$db = self::getDB();
		return $db->queryb([
			'INSERT' => compact('mrId', 'issueId', 'state'),
			'INTO'   => LPMTables::ISSUE_MR
		]);
	}

	/**
	 * Уникальный идентификатор.
	 * @var int
	 */
	public $id;

	/**
	 * GitlabMergeRequest::$id
	 * @var int
	 */
	public $mrId;

	/**
	 * Issue::$id
	 * @var int
	 */
	public $issueId;

	/**
	 * GitlabMergeRequest::$state
	 * @var string
	 */
	public $state;

	function __construct() {
		parent::__construct();

		$this->_typeConverter->addIntVars('id', 'mrId', 'issueId');
	}
}