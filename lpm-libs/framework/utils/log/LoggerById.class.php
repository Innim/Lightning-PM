<?php
namespace GMFramework;

/**
 * Класс для записи логов по идентификатору
 * 
 * @package ru.vbinc.gm.framework.utils.log
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2013
 * @version 0.1
 * 
 * @property-read string $logsPath Путь для записи логов
 */
class LoggerById extends Logger
{   
    private $_itemId;
    private $_logFileSuffix;
    private $_chunkSize = 100;

    function __construct($dirName, $logPath, $itemId, $logFileNameExt = 'log', $logFileSuffix = '') {
        parent::__construct($dirName, $logPath, $logFileNameExt);
        $this->_itemId = $itemId;
        $this->_logFileSuffix = $logFileSuffix;
    }

    protected function getLogFileName() {
      $start = $this->_itemId - ($this->_itemId % $this->_chunkSize);
      return $this->_logPath .
             $this->_logFileName . '/' .
             $start . '-' . ($start + $this->_chunkSize - 1) . '/' .
             $this->_itemId . $this->_logFileSuffix . '.' . $this->_logFileNameExt;
    }
}