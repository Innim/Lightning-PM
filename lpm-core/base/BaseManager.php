<?php
/**
 * Базовый менеджер. 
 * Менеджер должен выбирать данные из базы и обновлять их там
 */
abstract class BaseManager extends LPMBaseObject
{
	function __construct() {
		parent::__construct();
	}

	/**
	 * Загружает список объектов
	 * @param string|array $from
	 * @param string|array $where = null
	 * @param string $class = null
	 * @param string|array $fields = null
	 * @param int $limitCount = 0
	 * @param int $limitStart = -1
	 * @param string|array $tables = null
	 * @throws Exception В случае ошибки при выолнении запроса
	 * @throws Exception Если переданые неврные входные параметры
	 */
	protected function loadObjectsList( $from, $where = null, $class = null, 
										$fields = null, $limitCount = 0, $limitStart = -1,
										$tables = null ) 
	{
		if ($class !== null && is_subclass_of($class, 'StreamObject'))
			throw new Exception('Переданный класс должен быть наследником StreamObject');
			

		$sql  =  array();

		if ($fields === null) $fields = '*';

		$sql['SELECT'] = $fields;
		$sql['FROM'] = $from;

		if ($where !== null) $sql['WHERE'] = $where;
		if ($limitCount > 0) {
			$sql['LIMIT'] = $limitCount;
			if ($limitStart > 0)
				$sql['LIMIT'] = $limitStart . ',' . $sql['LIMIT'];
		}

		$query = $this->_db->queryb( $sql, $tables );

		if (!$query) throw new Exception('Ошибка при выполнении запроса к БД');
		
		$list = array();

		while ($row = $query->fetch_assoc()) {
			if ($class === null) {
				$list[] = $row;
			} else {
				$obj = new $class();
				$obj->loadStream( $row );
				$list[] = $obj;
			}
		}

		return $list;		
	}
}
?>