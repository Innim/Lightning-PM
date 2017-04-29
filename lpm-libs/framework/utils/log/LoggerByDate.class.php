<?php
namespace GMFramework;

/**
 * Класс для записи логов по дням.<br>
 * в указанной директории будет создана директория
 * с указанным именем, внутри которой будет структура:
 * <pre>
 * year [dir]
 *  month [dir]
 *      day [file]
 * </pre>
 * 
 * @package ru.vbinc.gm.framework.utils.log
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2013
 * @version 0.1
 * 
 * @property-read string $logsPath Путь для записи логов
 */
class LoggerByDate extends Logger
{   
    private $_logFilePrefix;

    function __construct($dirName, $logPath, $logFileNameExt = 'log', $logFilePrefix = '') {
        parent::__construct($dirName, $logPath, $logFileNameExt);
        $this->_logFilePrefix = $logFilePrefix;
    }

    protected function getLogFileName() {
        return $this->_logPath .
               $this->_logFileName . '/' .
               DateTimeUtils::date('Y') . '/' .
               DateTimeUtils::date('m') . '/' .
               $this->_logFilePrefix .
               DateTimeUtils::date('Y-m-d') . '.' . $this->_logFileNameExt;
    }
}