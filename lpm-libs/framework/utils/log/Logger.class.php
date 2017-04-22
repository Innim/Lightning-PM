<?php
namespace GMFramework;

/**
 * Класс для записи логов
 * @package ru.vbinc.gm.framework.utils.log
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2013
 * @version 0.2
 * @todo дописать
 * 
 * @property-read string $logsPath Путь для записи логов
 */
abstract class Logger
{   
    /**
     * Имя файла лога (без пути до него и расширения)
     * @var string
     */ 
    protected $_logFileName;
    /**
     * Расширение файла лога
     * @var string
     */ 
    protected $_logFileNameExt;
    /**
     * Путь по умолчанию до директории логов
     * @var string
     */
    protected $_logPath;
    /**
     * Лог включен
     * @var boolean
     */ 
    private $_enabled;
    /**
     * Тип дампа переменных
     * @var int GMLog::DUMP_WITH_*
     */ 
    private $_dumpType;
    
    /**
     * Массив логов <code>ArrayList of GMLogMessage</code>
     * @var ArrayList
     */
    protected $_logs;

    protected $_useMicrotime = false;

    protected $_filePermissions = 0766;

    function __construct($logFileName, $logPath, $logFileNameExt = 'log') 
    {
        $this->_logFileName     = $logFileName;
        $this->_logFileNameExt  = $logFileNameExt;

        $this->_enabled     = true;
        $this->_dumpType    = Log::DUMP_WITH_DO_DUMP;
        $this->_logs        = new ArrayList();

        $this->setLogPath( $logPath );
    }  

    function __destruct() 
    {
        $this->flush();
    }

    public function setUseMicrotime($value)
    {
        $this->_useMicrotime = $value;
    }

    public function setLogPath($value) 
    {
        $logsPath = str_replace('\\', '/', $value);

        $len = mb_strlen($logsPath);
        if ($len > 0 && mb_substr($logsPath, -1) != '/') $logsPath .= '/';
 
        $this->_logPath    = $logsPath;
    }

    public function setDumpType($value) 
    {
        $this->_dumpType    = $value;
    }

    public function setEnabled($value) 
    {
        $this->_enabled    = $value;
    }

    public function isEnabled() 
    {
        return $this->_enabled;
    }
    
    /**
     * Запись в лог
     * @param mixed $var то что будет записано в лог
     * @param string $comment строка комментария к логгируемому объекту
     * @param object $object логгирующий объект
     */
    public function addLogMessage( $var, $comment = '', $object = null/*, $light = false*/ )
    {
        if (!$this->_enabled) return;

        $class = null !== $object ? get_class($object) : 'undefined';
        $str = $this->var2Str($var);
        $this->_logs->push(new LogMessage($str, $comment, $class, $this->_useMicrotime));
    }

    public function log($string, $_ = '') 
    {
        call_user_func_array(array($this, 'addLogString'), func_get_args());
    }
    
    /**
     * Запись в лог строки
     * @param string $str строка для записи в дог
     */
    public function addLogString( $str, $_ = '' )
    {
        $args = func_get_args();
        $str  = implode(' ', $args);
    
        $this->_logs->push(new LogMessage($str, '', '', $this->_useMicrotime));
    }

    /**
     * Сбрасывает лог в файл
     */ 
    public function flush() 
    {
        if ($this->_enabled && $this->_logs->size() > 0 && $this->_logPath != '') {
            $log  = $this->getLog4Write();
            $file = $this->getLogFileName();
            $this->write2File($file, $log);
            $this->_logs->reset();
        }
    }

    /**
     * Создает переданный путь (все промежуточные директории). <br>
     * Путь не должен включать имя файла!
     */ 
    protected function createPath($path) 
    {
        $path = str_replace('\\', '/', $path);
        $pathArr = explode('/', $path);
        $curPath = '';
        while (count($pathArr) > 0) {
            $dir = array_shift($pathArr);
            if ($dir != '') {
                $curPath .= $dir;
                if(!file_exists( $curPath ) || !is_dir( $curPath )) {
                    if (!@mkdir($curPath, 0777, true)) return false;
                    // благодаря umask при создании директории права могут выставляться не такие,
                    // как я передел, поэтому дополнительно делаем chmod
                    chmod($curPath, 0777);
                }
                $curPath .= '/';
            } else if ($curPath == '') $curPath = '/'; 
        }

        return true;
    }

    protected function getLog4Write() 
    {
        $log = '';

        foreach ($this->_logs->getArray() as /* @var $logMessage GMLogMessage */ $logMessage)
        {
            $message = $logMessage->getLogString(); 
            $log .= $message . "\n";
        }

        return $log;
    }

    protected function getLogFileName() 
    {
        return $this->_logPath . $this->_logFileName . '.' . $this->_logFileNameExt;
    }

    private function write2File($file, $str) 
    {
        $path = dirname($file);
        
        if (!$this->createPath($path)) return;
        // Надо назначить права при создании, иначе например крон создает логи, в которые не могут писать 
        if (file_exists($file))
        {
            $logFile = @fopen($file, 'a');
        }
        else 
        {
            touch($file);
            chmod($file, $this->_filePermissions);
            $logFile = @fopen($file, 'a');
        }
        if (!$logFile) return;
        
        fwrite( $logFile, $str );
        fclose( $logFile );
    }
    
    /**
     * Возвращает дамп переменной в строку, 
     * с учетом выбранного способа 
     * @param $var
     * @return String
     */
    protected function var2Str( &$var ) 
    {
        switch ($this->_dumpType) {
            case Log::DUMP_WITH_PRINT_R  : return $this->printRVar( $var );
            case Log::DUMP_WITH_VAR_DUMP : return $this->dumpVar  ( $var );
            case Log::DUMP_WITH_DO_DUMP  : 
            default                        : return $this->doDump( $var );
        }
    }
    
    private function dumpVar( &$var ) 
    {
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

    private function printRVar( &$var ) 
    {
        return print_r( $var, true );
    }
    
    /**
     * @param mixed $var  -- variable to dump
     * @param int отступ
     * @return String
     */
    private function doDump( &$var, $indent = 0, $varName = '', $printSimpleType = false )
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
            foreach ($var as $name => $value) 
                $str .= $this->doDump( $value, $indent + 1, $name, $printSimpleType );            
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
            if ($printSimpleType) 
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
    
    private function is_assoc($var) 
    { 
        return is_array($var) && array_keys($var)!==range(0,sizeof($var)-1); 
    } 
}
?>