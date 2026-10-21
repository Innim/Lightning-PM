<?
/**
 * Настройки из БД
 * Настройки должны храниться в таблице следующей структуры:
 * <code>
 * CREATE TABLE `prefix_tablename` (
 * `option` varchar(20) NOT NULL COMMENT 'опция',
 * `value` text NOT NULL COMMENT 'её значение',
 * PRIMARY KEY  (`option`)
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='таблица настроек';
 * </code> 
 * @package ru.vbinc.gm.framework.utils
 * @author GreyMag
 * @version 0.3
 */
abstract class Options
{
	/**
	 * Имя таблицы опций (без префикса).
	 * Выставляется обычно при инициализации. 
	 * Префикс должен подставляться автоматически при использовании метода 
	 * <code>DBConnect::queryt()</code>
	 * @var String
	 */
	//public static $tableName = '';
	
	/**
	 * 
	 * @var Options
	 */
	//protected static $_instance;

	/**
	 *
	 * @return Options
	 */
	/*protected static function getOptInstance( $class )
	{
		if( !self::$_instance ) {		
			if (!is_subclass_of( $class, __CLASS__)) 
			     throw new Exception( 'Класс для инстанции должен быть наследником Options' );	
			self::$_instance = new $class();
		}
		 
		return self::$_instance;
	}*/
	
	/**
	 * Преобразовательтипов
	 * @var TypeConverter
	 */
	protected $_typeConverter;
	
	function __construct( $loadOptions = true )
	{		
		$this->_typeConverter = new TypeConverter( $this );

		$this->initialization();
		
		if ($loadOptions) $this->loadOptions();
	}

	/**
	 * Получается одну или несколько опций из БД
	 * @param $optionName,... Неограниченное количество имён опций. 
	 * Имена опций экранируются перед запросом к БД
	 * @return false|string|array 
	 * Если не было передано ни одного имени опции - вернётся пустой массив.
	 * Если не было выбрано ни одной опции - вернется false.	 
	 * Если было передано одно имя опции, то вернется её значение.
	 * Если несколько - ассоциативный массив значений.
	 * @throws Exception Если не определено имя таблицы опций
	 * @throws Exception Если невозможно получить соединение с БД
	 * @throws DBException Если не получается выполнить запрос к БД или ошибка во время выполнения
	 */
	public function getOption( $optionName = '' )
	{
		if (func_num_args() === 0) return false;

		$tableName = $this->getTableName();
		if ($tableName == '') throw new Exception('Table name can not be empty');
		

		$dbConnect = $this->getDBConnect();
		if (!$dbConnect) throw new Exception('Method Options::getDBConnect return null');

		$options = array();
		 
		$where = '';		
		$props = func_get_args();
		
		if (count( $props ) > 0) {
			foreach ($props as $optionName) {
				$optionName = $dbConnect->escape_string_t( $optionName );
			    $where .= ( ( $where == '' ) ? '' : ',' ) . "'" . $optionName . "'";
			}
	
			$sql = "SELECT `option`, `value` FROM `%s` WHERE `option` IN (" . $where . ")";
			 
			if (!$query = $dbConnect->queryt( $sql, $tableName ))
				 throw new DBException( $dbConnect, 'Can not to load options from database');

			while ($option = $query->fetch_assoc()) {
				$options[$option['option']] = TypeConverter::autoCastValue( $option['value'] );
			}
			$query->close();
			
	        switch (count( $options ))
	        {
	            case 0 : return false;
	            case 1 : return array_pop( $options );
	            default : return $options;
	        }
		} else return $options;
	}
	
	/**
	 * Загружает опции
	 */
	protected function loadOptions()
	{
		// GMF2DO чтобы защищённые и приватные методы не грузились
		$options = call_user_func_array( array( 'Options', 'getOption' ),  
		                                 array_keys( get_object_vars( $this ) ) );
		
		if (!$options) throw new Exception( 'Ошибка при загрузке опций' );
		if (!is_array( $options )) $options = array( $options );
		 
		foreach ($options as $name => $value) {
			if (property_exists( $this, $name )) $this->setVar( $name, $value);
		}

		$this->initOptions();
	}

	/**
	 * Инициализация. Вызывается до загрузки опций. 
	 * Рекомендуется для переопределения в наследниках 
	 * в случае необходимости дополнительной инициализации объекта опций
	 */
	protected function initialization() {

	}

	/**
	 * Определяет имя таблицы опций (без префикса).
	 * Префикс должен подставляться автоматически при использовании метода 
	 * <code>DBConnect::queryt()</code>
	 * @return String
	 */
	abstract protected function getTableName();

	/**
	 * Возвращает инстанцию соединения с базой данных, 
	 * с помощью которого будут загружаться опции.
	 * По умолчанию берется инстанция из класса <code>DefaultGlobals</code>
	 * @return DBConnect
	 * @see Globals::getDBConnect()
	 * @see DefaultGlobals
	 */
	protected function getDBConnect() {
		return DefaultGlobals::getInstance()->getDBConnect();
	}
	
	/**
	 * Проводит дополнительные действия с опциями после загрузки, если требуется
	 */
	protected function initOptions()
	{
		
	}
	
	/**
	 * Устанавливает значение поля по данным из базы
	 * @param string $name
	 * @param mixed $value
	 */
	protected function setVar( $name, $value ) {
		if ($this->$name instanceof StreamObject) 
		{
			$value = @json_decode( $value );
			if ($value !== null)
				$this->$name->loadStream( $value );
		}
		else 
			$this->$name = $this->_typeConverter->setType( $name, $value );
	}
}
?>