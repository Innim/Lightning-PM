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
	 * @param int $itemType 
	 * @param string $startWith = '' Тег должен начинаться с указанной подстроки
	 * @return array <code>Array of LPMTag</code> 
	 * @throws Exception В случае ошибки формирования или выполнения запроса
	 */
	public function getTagsByType( $itemType, $startWith = '' )
	{
		$where = array(
			'`itemType` = ' . $itemType
		);

		if ($startWith !== '') {
			$startWith = $this->_db->escape_string( $startWith );
			$where[] = '`tag` LIKE \'' . $startWith . '%\'';
		}

		return $this->loadObjectsList( 
			LFMTables::TAGS_LIST,
			$where
		);
	}

	/**
	 * Сохраняет теги
	 */
	public function saveTage( $tags, $itemType ) {
		$values = array();

		foreach ($tags as $tag) {
			$values[] = array($itemType, $this->_db->escape_string( $tag ));
		}

		return $this->_db->queryb(
			'INSERT' 	=> array('itemType', 'name');,
			'IGNORE'    => '',
			'INTO' 		=> LFMTables::TAGS_LIST,
			'VALUES'	=> $values
		);
	}

	/**
	 * Сохраняет набор тегов для конктерной инстанции
	 */
	public function updateTags4Instance( $tags, $instanceType, $instanceId )
	{

	}
}
?>