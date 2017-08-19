<?php
namespace GMFramework;

/**
 * Утилита для записи логов - синглтон
 * @package ru.vbinc.gm.framework.utils.log
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2011
 * @version 0.2
 * @todo дописать
 * 
 * @property-read string $logsPath Путь для записи логов
 */
class Log extends LoggerBySize
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
            self::$_instance = new Log();
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
     * Если инстанция класса уже инициализорована - 
     * конструктор породит исключение 
     */
    function __construct()
    {
    	if( self::$_instance ) throw new Exception( 'GMLog is singleton' );
    	
    	parent::__construct('gmlog', '');

    	$this->init();
    }  
    
    function __get( $var )
    {
    	switch( $var ) {
    		case 'logsPath' : return $this->_logPath;
    	}
    }

    /**
     * Инициализировать основные параметры записи логов
     * @param string $logsPath путь до директории логов, по умолчанию ./logs/
     * @param int $logFileMaxSize максимальный размер основного файла логов, по умолчанию 100 Кб
     * @param int $logFileArchiveSize максимальный размер одного файла архива логов,
     * по умолчанию 10 Мб
     * @param bool $workInDebug если параметр выставлен в true -
     *  логирование работает только в режиме дебага
     * @param int $dumpType с помощью какой функции логгировать переменные,
     * используются константы <code>GMLog::DUMP_WITH_*</code>,
     * по умолчанию используется <code>GMLog::DUMP_WITH_DO_DUMP</code>
     */
    public function init( $logsPath = './logs/', $logFileMaxSize = 102400, 
                          $logFileArchiveSize = 10485760, $workInDebug = true,
                          $dumpType = 0 )
    {
    	$this->setLogPath( $logsPath );
        $this->setFileMaxSizes($logFileMaxSize, $logFileArchiveSize);
        $this->setDumpType($dumpType);

        $this->setEnabled(GMFramework::isDebugMode() || !$this->_workInDebug);
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
        if( $this->_logs->size() == 0 || !$this->isEnabled()) return;
        
        if( $logFilePath == '' ) $logFilePath = $this->_logPath;
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
    	if (!$this->isEnabled()) return;
        /*
    	if (is_object( $value ) || is_array( $value )) {
	        ob_start(); 
	        var_dump( $value ); 
	        $str = str_replace( '&apos;', "'", html_entity_decode( strip_tags( ob_get_clean() ) ) );
    	} else $str = $value;*/
    	$str = $this->var2Str( $value );      
            
        $mess = new LogMessage( $str, $comment, '' );
        
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
        if (!$this->isEnabled() || $this->_logs->size() == 0) return;
        
        $this->saveLog( $logFileName, $logFileExt, $logFilePath );
        $this->_logs->reset(); 
    }
} 
?>