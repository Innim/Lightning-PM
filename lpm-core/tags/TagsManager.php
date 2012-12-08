<?php
/**
 * Менеджер тегов
 */
class TagsManager extends BaseManager
{
	function __construct() {
		parent __construct();
	}

	/**
	 * Получает теги для указанного типа объектов
	 * @param int $instanceType 
	 * @param string $startWith = '' Тег должен начинаться с указанной подстроки
	 * @return array <code>Array of LPMTag</code> 
	 * @throws Exception В случае ошибки формирования или выполнения запроса
	 */
	public function getTagsByType( $instanceType, $startWith = '' )
	{
		$where = array(
			'`instanceType` = ' . $instanceType
		);

		if ($startWith !== '') {
			$startWith = $this->_db->escape_string( $startWith );
			$where[] = '`tag` LIKE \'' . $startWith . '%\'';
		}

		return $this->loadObjectsList( 
			LFMTables::TAGS,
			$where
		);
	}
}
?>