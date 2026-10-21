<?php
/**
 * Утилита для записи логов - синглтон
 * @package ru.vbinc.gm.framework.utils.log
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2011
 * @version 0.1
 * 
 * @property-read string $logsPath Путь для записи логов
 */
class GMLog
{
	/**
	 * Инстанция класса
	 * @var GMLog
	 */
	private static $_instance;
    
    /**
     * Возвращает инстанцию класса. 
     * Если инстанция не создана, то создаёт
     * @return GMLog
     */
	public static function getInstance()
    {
        if( !self::$_instance ) {
            self::$_instance = new GMLog();
        }
        
    	return self::$_instance;
    }
    
    /* (non-PHPdoc)
     * @see GMLog#writeLog($var,$comment='undefined',$object=null)
     */
    public static function writeLog( $var, $comment = 'undefined', $object = null )
    {
    	self::getInstance()->addLogMessage( $var, $comment, $object );
    }  
    
    /*public static function writeLightLog( $var, $comment = 'undefined', $object = null )
    {
        self::getInstance()->addLogMessage( $var, $comment, $object, true );
    }*/
    
    /**
     * Делать дамп пеменной с помощью функции print_r
     * @var int
     */
    const DUMP_WITH_PRINT_R  = 1;
    /**
     * Делать дамп пеменной с помощью функции var_dump
     * @var int
     */
    const DUMP_WITH_VAR_DUMP = 2;
    /**
     * Делать дамп пеменной с помощью нестандартной функции
     * @var int
     */
    const DUMP_WITH_DO_DUMP  = 0;
    
    /**
     * Максимальный размер основного файла логов
     * @var int
     */
    private $_logFileMaxSize;
    /**
     * Максимальный размер одного файла архива логов
     * @var int
     */
    private $_logFileArchiveSize;
    /**
     * Путь по умолчанию до директории логов
     * @var string
     */
    private $_logsDefaultPath;
    /**
     * Логгирование работает только в дебаг режиме или всегда
     * @var bool
     */
    private $_workInDebug = false;
    
    /**
     * Массив логов <code>ArrayList of GMLogMessage</code>
     * @var ArrayList
     */
	private $_logs;
	
	private $_dumpType;
	
    /**
     * Если инстанция класса уже инициализорована - 
     * конструктор породит исключение 
     */
    function __construct()
    {
    	if( self::$_instance ) throw new Exception( 'GMLog is singleton' );
    	
    	$this->_logs = new ArrayList();
    	$this->init();
    	
    	$this->_dumpType = self::DUMP_WITH_DO_DUMP;
    }  

    function __destruct() {
    	$this->saveLog( 'gmlog' );
    }
    
    function __get( $var )
    {
    	switch( $var ) {
    		case 'logsPath' : return $this->_logsDefaultPath;
    	}
    }

    /**
     * Инициализировать основные параметры записи логов
     * @param string $logsPath путь до директории логов, по умолчанию ./logs/
     * @param int $logFileMaxSize максимальный размер основного файла логов, по умолчанию 100 Кб
     * @param int $logFileArchiveSize максимальный размер одного файла архива логов,
     * по умолчанию 10 Мб
     * @param bool $workInDebug если параметр выставлен в true -
     *  логгирование работает только в режиме дебага
     * @param int $dumpType с помощью какой функции логгировать переменные,
     * используются константы <code>GMLog::DUMP_WITH_*</code>,
     * по умолчанию используется <code>GMLog::DUMP_WITH_DO_DUMP</code>
     */
    public function init( $logsPath = './logs/', $logFileMaxSize = 102400, 
                          $logFileArchiveSize = 10485760, $workInDebug = true,
                          $dumpType = 0 )
    {
    	$this->_logsDefaultPath    = $logsPath;
    	$this->_logFileMaxSize     = $logFileMaxSize;
    	$this->_logFileArchiveSize = $logFileArchiveSize;
    	$this->_dumpType           = $dumpType; 
    }
    
    /**
     * Запись в лог
     * @param mixed $var то что будет записано в лог
     * @param string $comment строка комментария к логгируемому объекту
     * @param object $object логгирующий объект
     */
    public function addLogMessage( $var, $comment = '', $object = null/*, $light = false*/ )
    {
    	if( !DefaultGlobals::isDebugMode() && $this->_workInDebug ) return;
    	$class = ( $object != null ) ? get_class( $object ) : 'undefined';
    	/*if ($light) {
    	    $str = '';
    	} else {*/
    		//$str = print_r( $var, true );
    		/*ob_start(); 
    		var_dump( $var ); 
    		$str = str_replace( 
    		          '&apos;', "'", 
    		          htmlspecialchars_decode( strip_tags( ob_get_clean() ) ) ); */   
    	   $str = $this->var2Str( $var );		
    	/*} */    		
    	$this->_logs->push( new GMLogMessage( $str, $comment, $class ) );
    }
    
    /**
     * Сохранить запомненные логи в файл, без очистки массивов
     * @param string $logFileName имя файла логов
     * @param string $logFileExt расширение файла логов, по умолчанию log 
     * @param string $logFilePath путь до директории логов, 
     * если не указан - используется путь по умолчанию
     * @return void
     */
    public function saveLog( $logFileName, $logFileExt = 'log', $logFilePath = '' )
    {
        if( $this->_logs->size() == 0 || !DefaultGlobals::isDebugMode() && $this->_workInDebug ) return;
        
        if( $logFilePath == '' ) $logFilePath = $this->_logsDefaultPath;
        if( substr( $logFilePath, -1, 1 ) != '/' ) $logFilePath .= '/';
        
        // проверяем - если такой директории нет,
        // то пытаемся создать
        if( !file_exists( $logFilePath ) || !is_dir( $logFilePath ) ) {
            if( !@mkdir( $logFilePath, 0777, true ) ) return;
        }
        
        $objectLogFile = $logFilePath . $logFileName . '.' . $logFileExt;

        $log = '';
        if( file_exists( $objectLogFile ) ) {           
            // если файл уже слишком большой
            // то начинаем писать в новый
            if( filesize( $objectLogFile ) < $this->_logFileMaxSize ) $log = file_get_contents( $objectLogFile );
                                
            //{
                //copy( $objectLogFile, LOGS_PATH . $fileName . DateTimeUtils::date( 'YmdHis', time() + TIMEADJUST ) . '.' . $fileExt );
            //} else $log = file_get_contents( $objectLogFile );
        }
        
        $archive = '';        
        $log = "==========================================\n\r\n\r" . $log;
        
        foreach( $this->_logs->getArray() as /* @var $logMessage GMLogMessage */ $logMessage )
        {
            $message = $logMessage->getLogString(); 
            $log = $message . "\n\r" . $log;
            $archive .= $message . "\n\r";
        }
        
        @file_put_contents( $objectLogFile, $log );
        
        // сохраняем архив
        $i = 0;
        do {
            $i++;
            $archiveFile = $logFilePath . $logFileName . '.' . $i . '.' . $logFileExt;
        } while( file_exists( $archiveFile ) );
        
        // можно еще дописать в предыдущий - или новый создавать
        if( $i > 1 ) {
            $prevFile = $logFilePath . $logFileName . '.' . ( $i - 1 ) . '.' . $logFileExt;
            if( filesize( $prevFile ) + strlen( $archive ) <= $this->_logFileArchiveSize ) {
                $archiveFile = $prevFile;
            }
        }
        
        if( !$achiveFile = @fopen( $archiveFile, 'a' ) ) return;
        
        fwrite( $achiveFile, $archive );
        fclose( $achiveFile );
    }
    
    /**
     * Логирует переданный объект сразу в указанный файл,
     * не сохраняя в основном потоке лога.
     * При этом данные просто дописываются в конец файла, 
     * отслеживание размера не происходит
     * @param String $file путь до файла, в который будет производиться запись
     * @param any $value значение, которое будет логироваться 
     * @param String $comment комментарий
     */
    public function logIt( $file, $value, $comment = '' ) {
    	if (!Globals::isDebugMode() && $this->_workInDebug) return;
        /*
    	if (is_object( $value ) || is_array( $value )) {
	        ob_start(); 
	        var_dump( $value ); 
	        $str = str_replace( '&apos;', "'", html_entity_decode( strip_tags( ob_get_clean() ) ) );
    	} else $str = $value;*/
    	$str = $this->var2Str( $value );      
            
        $mess = new GMLogMessage( $str, $comment, '' );
        
        // проверяем - если такой директории нет,
        // то пытаемся создат
        $logFilePath = dirname( $file );
        if( !file_exists( $logFilePath ) || !is_dir( $logFilePath ) ) {
            if( !@mkdir( $logFilePath, 0777, true ) ) return;
        }        
        if (!$achiveFile = @fopen( $file, 'a' )) return;
        
        fwrite( $achiveFile, $mess->getLogString() . "\n\r" );
        fclose( $achiveFile );
    }
    
    /**
     * Сбросить запомненные логи в файл 
     * @param string $logFileName имя файла логов
     * @param string $logFileExt расширение файла логов, по умолчанию log 
     * @param string $logFilePath путь до директории логов, 
     * если не указан - используется путь по умолчанию
     * @return void
     */
    public function flushLog( $logFileName, $logFileExt = 'log', $logFilePath = '' )
    {
    	if( $this->_logs->size() == 0 || !DefaultGlobals::isDebugMode() && $this->_workInDebug ) return;
    	
    //var_dump( $this->_logs ); exit;
    	$this->saveLog( $logFileName, $logFileExt, $logFilePath );
        $this->_logs->reset(); 
    }
    
    /**
     * Возвращает дамп переменной в строку, 
     * с учетом выбранного способа 
     * @param $var
     * @return String
     */
    private function var2Str( &$var ) {
    	switch ($this->_dumpType) {
    		case self::DUMP_WITH_PRINT_R  : return $this->printRVar( $var );
    		case self::DUMP_WITH_VAR_DUMP : return $this->dumpVar  ( $var );
    		case self::DUMP_WITH_DO_DUMP  : 
    		default                       : return $this->doDump( $var );
    	}
    }
    
    private function dumpVar( &$var ) {
    	ob_start(); 
        var_dump( $var ); 
        // если включен xdebug - то надо удалять теги
        /*$str = str_replace( 
                  '&apos;', "'", 
                  htmlspecialchars_decode( strip_tags( ob_get_clean() ) ) 
                );*/
        $str =  ob_get_clean();      
        return $str; 
    }

    private function printRVar( &$var ) {
        return print_r( $var, true );
    }
    
	/**
	 * @param mixed $var  -- variable to dump
	 * @param int отступ
	 * @return String
	 */
	private function doDump( &$var, $indent = 0, $varName = '' )
	{
		// GMF2DO доделать
		$type = ucfirst( gettype( $var ) );
        if ($type == "Double") $type = "Float";
        
        $indentStr = '';
        $indentSymb = ' ';        
        while (strlen( $indentStr ) < $indent) $indentStr .= $indentSymb;
        
        $str = $indentStr;
                        
        if ($varName != '') $str .= $varName . " : ";

        if (is_object( $var ) || $this->is_assoc( $var ))
        {
            //echo "$indent$var_name <span style='color:#666666'>$type</span><br>$indent(<br>";
            $str .= $type . "\n" . $indentStr .  "(\n";
            //foreach($avar as $name=>$value) do_dump($value, "$name", $indent.$do_dump_indent, $reference);
            foreach ($var as $name => $value) $str .= $this->doDump( $value, $indent + 1, $name );            
            //echo "$indent)<br>";
            $str .= $indentStr . ")\n";
        }
        elseif (is_array( $var ))
        {
            $count = count( $var );
            $str .= $type . '[' . $count . '] (' . "\n";
            $keys = array_keys( $var );
            foreach($keys as $name)
            {
                $value = &$var[$name];
                $str .= $this->doDump( $value, $indent + 1 );
            }
            $str .= $indentStr . ")\n";
        }
        else 
        {
        	$str .= $type . "[" . strlen( $var ) . "] ";
        	if (is_bool  ( $var )) 
	            $str .= ( $var ? "TRUE" : "FALSE" );
	        elseif (is_null  ( $var )) 
	            $str .= 'NULL';
	        else 
	            $str .= $var;
	        $str .= "\n";         	
        }
            
        return $str;
	}
	
	private function is_assoc($var) { 
        return is_array($var) && array_keys($var)!==range(0,sizeof($var)-1); 
    } 
} 
?>