<?php
namespace GMFramework;

/**
 * Загружаемые настройки. <br>
 * Для использования необходимо реализовать провайдер данных, 
 * реализующий интрфейс  <code>IOptionsDataProvider</code>.
 * Для загрузки БД MySQL провайдер уже реализован - 
 * можно просто наследоваться от <code>OptionsMySQLDataProvider</code>
 * @package ru.vbinc.gm.framework.utils
 * @author GreyMag
 * @version 0.4
 * @see IOptionsDataProvider
 * @see OptionsMySQLDataProvider
 * @see Options::createDataProvider()
 * @see Options::initialization()
 */
abstract class Options extends StreamObject
{

	/**
	 * Провайдер данных для опциий
	 * @var IOptionsDataProvider
	 */
	private $_provider;
	
	function __construct( $loadOptions = true )
	{
		parent::__construct();

		$this->initialization();

		$this->_provider = $this->getDataProvider();
		
		if ($loadOptions) $this->loadOptions();
	}

	/**
	 * Получается одну или несколько опций из БД
	 * @param $optionName,... Неограниченное количество имён опций. 
	 * Имена опций экранируются перед запросом к БД
	 * @return false|string|array|null 
	 * Если не было передано ни одного имени опции - null.
	 * Если не было выбрано ни одной опции - вернется false.	 
	 * Если было передано одно имя опции, то вернется её значение.
	 * Если несколько - ассоциативный массив значений.
	 * @throws Exception 
	 */
	public function getOption( $optionName = '' )
	{
		if (func_num_args() === 0) return null;

		$props 	 = func_get_args();
		$options = $this->_provider->loadOptions($props);

		switch (count( $options ))
        {
            case 0  : return false;
            case 1  : return array_pop( $options );
            default : return $options;
        }
	}
	
	/**
	 * Загружает опции
	 */
	protected function loadOptions()
	{
		// Загружаем все опции
		$options = $this->_provider->loadOptions($this->getPublicProps());
		
		if (!$options) throw new Exception( 'Ошибка при загрузке опций' );

		$this->loadStream($options);

		$this->initOptions();
	}

	protected function saveOptions()
	{
		 $this->_provider->saveOptions($this);
	}

	/**
	 * Инициализирует опции после загрузки
	 */ 
	protected function initOptions()
	{

	}

	/**
	 * Инициализация. Вызывается до загрузки опций. 
	 * Рекомендуется для переопределения в наследниках 
	 * в случае необходимости дополнительной инициализации объекта опций
	 */
	protected function initialization() {}

	/**
	 * Возвращает провайдер данных для опций
	 * @return IOptionsDataProvider
	 */
	abstract protected function getDataProvider();
}
?>