<?php
namespace GMFramework;

/**
 * Вспомогательный класс для импорта классов
 * @author GreyMag
 *
 */
class ImportClasses
{
	/**
	 * Инстанция
	 * @var ImportClasses
	 */
	//private static $_instance;
	/**
	 * Массив импортеров
	 * @var array <code>Array of ImportClasses</code>
	 */ 
	private static $_importers = array();
	
	/**
	 * Возвращает инстанцию класса (последюю созданную)
	 * @return ImportClasses
	 * @deprecated Теперь возможно создание нескольких импортеров - для разных директорий, 
	 * поэтому класс больше не является синглтоном 
	 */
	public static function getInstance()
	{
		//if (!self::$_instance) 
		$count = count( self::$_importers );
		if ($count === 0) throw new Exception( 'Сначала необходимо вызвать метод ' . __CLASS__ . '::createInstance' );
		
		//return self::$_instance;
		return self::$_importers[$count - 1];
	}
	
	/**
	 * Создаёт инстанцию класса 
	 * @param string $classesPath путь до директории классов
	 * @param boolean $useTypeInName использовать тип в имени файла (.class.php, .interface.php) или нет
	 * @return ImportClasses
	 */
	public static function createInstance( $classesPath, $namespace = '', $useTypeInName = false )
    {
        //if (!self::$_instance) self::$_instance 
        $importer = new ImportClasses( $classesPath, $namespace, $useTypeInName );
        //else throw new Exception( 'Инстанция класса ' . __CLASS__ . ' может быть только одна' );
        
        if (count( self::$_importers ) === 0) GMFramework::addAutoload( __CLASS__ . '::load' );
        self::$_importers[] = $importer;
		//self::beNice();

        return $importer;
        //return self::getInstance();
    }
    
    public static function load( $className )
    {
        //if (self::$_instance) self::$_instance->loadClass( $className );
        foreach (self::$_importers as $importer) {
        	$importer->loadClass( $className );
        }
    }
	
	/** 
	 * В файле описан класс
	 * @var string
	 */
	const TYPE_CLASS = 'class';
	/**
	 * В файле описан интерфейс
	 * @var int
	 */
	const TYPE_INTERFACE = 'interface';
	
	protected $_classes = array();
	protected $_classesPath = array();
	protected $_useTypeInName = false;
	protected $_namespace = '';
	/**
	 * Использовать автопоиск и кэширование результатов
	 * @var boolean
	 */
	private $_useAutoSearch = false;
	/**
	 * Абсолютный путь до файла с кэшированными данными
	 * @var string
	 */
	private $_cacheFile = '';
	/**
	 * Данные, полученные автопоиском классов, для кэширования
	 * @var array ассоциативный массив вида имя_класса=>путь
	 */
	private $_cacheData = null;
	/**
	 * Кэш был изменён
	 * @var boolean
	 */
	private $_cacheChanged = false;
	
	function __construct( $classesPath = '', $namespace = '', $useTypeInName = false )
	{
		$this->_classesPath   = $classesPath;
		$this->_namespace     = $namespace;
		$this->_useTypeInName = $useTypeInName;
	}

    function __destruct() 
    {
	   if ($this->_useAutoSearch) $this->dumpCache();
    } 
	
	//function __destruct() {
	//	//var_dump( $this->_cacheFile, $this->_cacheData);
	//	file_put_contents( '111', date('U').'.log' );
	//	//if ($this->_useAutoSearch) $this->dumpCache();
	//}
	
	/**
	 * Включает автопоиск,
	 * Автопоиск пробегает по всем файлам директории классов и её поддиректорий и
	 * ищет файлы с именем, удовлетворяющим заданным правилам формирования имени файла класса.
	 * Наличие определения класса в этом файле НЕ ПРОВЕРЯЕТСЯ,
	 * Подключается первый подходящий результат, при этом интерфейсы и классы не различаются.
	 * Найденные автопоиском классы кэшируются,
	 * если поменялась структура директорий или были перемещены файлы классов -
	 * необходимо удалить файл кэша. Пути кэшируются отностительно директории классов,
	 * поэтому директорию целиком можно переносить без очистки кэша
	 * @param string $cacheFile абсолютный путь до файла,
	 * в котором будут кэшироваться найденные классы
	 */
	public function enableUseAutoSearch($cacheFile) 
	{
		$this->_useAutoSearch = true;
		$this->_cacheFile = $cacheFile;
	}
	
	/**
	 * Отключает функцию автопоиска
	 */
	public function disableAutoSearch() 
	{
		$this->_useAutoSearch = false;
	}
	
	/**
	 * Импортировать класс
	 * @param string $class имя класса
	 * @param string $package путь до файла, относительно основной директории классов
	 * @param string $type тип содержимого файла. 
	 * Используются константы <code>ImportClasses::TYPE_CLASS, ImportClasses::TYPE_INTERFACE</code>
	 */
	public function importClass( $class, $package, $type = ImportClasses::TYPE_CLASS )
	{
		if (isset( $this->_classes[$class] )) throw new Exception( 'Попытка дважды импортировать класс с одним именем ' . $class );
		
		$this->_classes[$class] = new _ClassInfo( $class, $package, $type );
	}
    
    /**
     * Импортировать класс с указанием абсолютного пути
     * @param string $class имя класса
     * @param string $absolutePath асолютный путь до файла, включая имя файла
     */
    public function import( $class, $absolutePath )
    {
        //if (isset( $this->_classes[$class] )) throw new Exception( 'Попытка дважды импортировать класс с одним именем' );
        
        //$this->_classes[$class] = new _ClassInfo( $class, '', '' );
        $this->importClass( $class, '', '' );
        $this->getInfo( $class )->setAbsolutePath( $absolutePath );
    }
    
    /**
     * Импортировать классы из указанного пакета
     * @param string $package путь до файла, относительно основной директории классов
     * @param string $class1 название класса 
     * @param string $_ ... можно передавать много названий классов
     */
    public function importPackageClasses( $package, $class1, $_ = '' )
    {
        $params = func_get_args();
        $this->importFromPackage( self::TYPE_CLASS, $params );
    }
    
    /**
     * Импортировать интерфейсы из указанного пакета
     * @param string $package путь до файла, относительно основной директории классов
     * @param string $class1 название интерфейса 
     * @param string $_ ... можно передавать много названий интерфейсов
     */
    public function importPackageInterfaces( $package, $class1, $_ = '' )
    {
        $params = func_get_args();
        $this->importFromPackage( self::TYPE_INTERFACE, $params );
    }
	
	/**
	 * Загрузить класс
	 * @param string $className имя класса
	 * @return boolean
	 */
	public function loadClass( $className )
	{		
		if ($info = $this->getInfo( $className )) 
		{
			$path = ( $info->isAbsolutePath() ) 
			             ? $info->getAbsolutePath() 
			             : $this->_classesPath . $info->getClassPath( $this->_useTypeInName ); 

            include_once $path;
			return true;
		} 
		else if ($this->_useAutoSearch) 
		{
			// если требуется использовать автопоиск
			// - то рекурсивно пробегаем директорию и кэшируем результат
			return $this->autoSearch( $className );
		}
		
		return false;
	}
	
	/**
	 * Возвращает информацию о классе, по его имени
	 * @return _ClassInfo || false
	 */
	public function getInfo( $className )
	{
		if (!isset( $this->_classes[$className] )) return false;
		
		return $this->_classes[$className];
	}
    
    private function importFromPackage( $type, $params )
    {
        for ($i = 1; $i < count( $params ); $i++) {
            $this->importClass( $params[$i], $params[0], $type );
        }
    }
	
	private function loadCache() 
	{
		//$cacheData = @file_get_contents( $this->_cacheFile );
		$cacheData = null;
		@include( $this->_cacheFile );
		if ($cacheData) {
			$this->_cacheData = $cacheData;//unserialize( $cacheData );
		} else {
			$this->_cacheData = array();
		}
	}
	
	private function dumpCache() 
	{
		// сохраняем только в случае изменений
		if ($this->_cacheChanged && $this->_cacheData !== null) {
			//$cacheData = serialize( $this->_cacheData );
			$cacheData = '<?php $cacheData = ' . var_export( $this->_cacheData, true ) . '; ?>';
			return file_put_contents( $this->_cacheFile, $cacheData ) !== false;
		} else
			return true;
	}
	
	private function autoSearch( $className ) 
	{
		if ($this->_cacheData === null) $this->loadCache();
		
		// TODO проверка file_exists отнимает много времени, надо делать ее только в дебаг режиме,
		// а в production полностью полагаться на кэш
		if (!isset($this->_cacheData[$className]) || 
			!file_exists($this->_classesPath . $this->_cacheData[$className])) 
		{
			// поиск
			$nsLen = mb_strlen($this->_namespace);
			if ($nsLen > 0)
			{
				$namespace = $this->_namespace . '\\';
				$nsLen++;

				// Если не подходит под простанство имен
				if ((string)mb_substr($className, 0, $nsLen) !== $namespace) return false;

				$classFileName = mb_substr($className, $nsLen);
			}
			else 
			{
				$classFileName = $className;
			}

			$result = $this->autoSearchInDir($classFileName, $this->_classesPath);
			if ($result) 
			{
				$pathLen = mb_strlen($this->_classesPath);
				if (mb_substr($result, 0, $pathLen) == $this->_classesPath) 
				{
					$result = mb_substr($result, $pathLen);
				}
				$this->_cacheData[$className] = $result;
				$this->_cacheChanged = true;
			}
		}
		
		if (isset($this->_cacheData[$className])) 
		{
			// грузим из кэша
			include_once $this->_classesPath . $this->_cacheData[$className];
			return true;
		} 
		else return false;
	}
	
	private function autoSearchInDir( $classFileName, $dir ) 
	{
		$types = array( self::TYPE_CLASS, self::TYPE_INTERFACE );
		$files = scandir( $dir );
		$dirName = '';
        foreach ($files as $item) 
        {
			$search = false;
	        if ($item == '.' || $item == '..') continue;
			$dirName = $dir . $item . '/';
			if (is_dir( $dirName ))
				$search = $this->autoSearchInDir( $classFileName, $dirName );
			else 
			{
				$nameParts = explode( '.', $item );
				$npCount = count( $nameParts );
				if ($this->_useTypeInName) 
				{
					if ($npCount != 3 || !in_array( $nameParts[1], $types )) continue;
				} else if ($npCount != 2)  continue;
				
				if ($nameParts[$npCount-1] == 'php' && $nameParts[0] == $classFileName) 
				{
					$search = $dir . $item;
				}
			}
			
			if ($search) return $search;
	    }
		
		return false;
	}
}

/**
 * Вспомогательный класс для хранения информации о классе
 * @author GreyMag
 */
class _ClassInfo
{
	public $package;
	public $class;
	public $type;
	
	private $_absolutePath = -1;
	
	function __construct( $class, $package, $type )
	{
		$this->package = $package;
		if (substr( $package, -1 ) != DIRECTORY_SEPARATOR) 
		  $this->package .= DIRECTORY_SEPARATOR;
		$this->class   = $class;
		$this->type    = $type;
	}
	
	/**
	 * Получить путь до файла с классам относительно 
	 * основной директории файлов классов
	 * @return string
	 */
	public function getClassPath( $useTypeInName )
	{
		return $this->package . $this->class . ( $useTypeInName ? ( '.' . $this->type ) : '' ) . '.php';
	}
	
	/**
	 * Устанавливает аблосютный путь до файла
	 * @param string $path
	 */
	public function setAbsolutePath( $path )
	{
		$this->_absolutePath = $path;
	}
    
	/**
	 * Возвращает абсолютный путь
	 * @return string
	 */
    public function getAbsolutePath()
    {
        return $this->_absolutePath;
    }
	
	/**
	 * Проверяет, используется абсолютный путь или нет
	 */
	public function isAbsolutePath()
	{
		return $this->_absolutePath != -1;
	}
}