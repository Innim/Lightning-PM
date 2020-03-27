<?php
/**
 * Базовый класс внешнего API.
 */
abstract class ExternalApi {
	private $_uid;

	function __construct($uid) {
		$this->_uid = $uid;
	}

	/**
	 * Уникальный идентификатор API.
	 *
	 * Испрользуется в адресе.
	 * @return string
	 */
	public function getUid() {
		return $this->_uid;
	}

	/**
	 * Возвращает URL для доступа к API.
	 * @return string
	 */
	public function getUrl() {
		return Link::getUrl(LightningEngine::API_PATH, [$this->getUid()]);
	}

	/**
	 * Запускает обработку запроса API.
	 * @param  string $input Входящие данные (php://input).
	 * @return string Строка ответа, которая будет распечатана.
	 */
	public abstract function run($input);
}