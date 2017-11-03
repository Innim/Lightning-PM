<?php
namespace GMFramework;

/**
 * 
 * @todo реализовать
 * @todo документация
 */
abstract class LoggerBySize extends Logger
{
    /**
     * Максимальный размер основного файла логов (если 0 - не ограничен)
     * @var int
     */
    protected $_logFileMaxSize;
    /**
     * Максимальный размер одного файла архива логов (если 0 - не ограничен)
     * @var int
     */
    protected $_logFileArchiveSize;

    function __construct($logFileName, $logPath, $logFileNameExt = 'log') {
        parent::__construct($logFileName, $logPath, $logFileNameExt);
    } 

    public function setFileMaxSizes($logFileMaxSize, $logFileArchiveSize) {
        $this->_logFileMaxSize     = $logFileMaxSize;
        $this->_logFileArchiveSize = $logFileArchiveSize;
    }
}
?>