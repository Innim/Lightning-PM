<?php
/**
 * Информация о теге
 */
class LPMTag extends LPMBaseObject
{
	/**
	 * Идентификатор тега 
	 * @var int
	 */
	public $tagId;
	/**
	 * Название тега
	 * @var string
	 */
	public $name;
	/**
	 * Тип объекта, для которого предназначен тег
	 * @var int
	 */
	public $instanceType;

	function __construct() {
		parent:__construct();

		$this->_typeConverter->addIntVars( 'tagId', 'instanceType' );
	}
}
?>