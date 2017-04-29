<?php
namespace GMFramework;

/**
 * Реализация DBConnect с возможность логгирования всех медленных и неудачных запросов
 * @see DBConnect
 * @package ru.vbinc.gm.framework.db
 * @author greymag <greymag@gmail.com>
 * @copyright 2013
 * @version 0.1
 * @access public
 */
class DBConnectWithLog extends DBConnect
{
    private $_slowTime;
    private $_slowLogger;
    private $_allLogger;
    private $_failureLogger;

    function __construct($logPath = '', $logSlow = false, $logFailure = false, $slowTime = .1, $host = '', $username = '', $passwd = '', $dbname = '', $prefix = '', $logAll = false)
    {
        parent::__construct( $host, $username, $passwd, $dbname, $prefix );

        if (!empty($logPath))
        {
            if ($logSlow && $slowTime > 0)
            {
                $this->_slowTime = $slowTime;
                $this->_slowLogger = new LoggerByDate('slow', $logPath, 'log', 'slow-');
            }
            if ($logFailure)
            {
                $this->_failureLogger = new LoggerByDate('failed', $logPath, 'log', 'failed-');
            }
            if ($logAll)
            {
                $this->_allLogger = new LoggerByDate('all', $logPath, 'log', 'mysql-');
            }
        }
    }

    function __destruct()
    {
        if ($this->_failureLogger !== null)
        {
            $this->_failureLogger->flush();
        }

        if ($this->_slowLogger !== null)
        {
            $this->_slowLogger->flush();
        }

        if ($this->_allLogger !== null)
        {
            $this->_allLogger->flush();
        }
    }

    public function prepare($query) 
    {
        $start = $this->curtime();
        
        $result = parent::prepare( $query );

        $time = $this->curtime() - $start;

        $this->log($result, $time);

        return $result;
    }
    
    public function query($query, $resultmode = null) 
    {
        $start = $this->curtime();
        
        $result = parent::query( $query, $resultmode );

        $time = $this->curtime() - $start;

        $this->log($result, $time);

        return $result;
    }

    public function logBindParam($args)
    {
        // Записываем лог всех запросов
        if (null !== $this->_allLogger)
        {
            $this->_allLogger->log('bind_param(\'' . implode($args, "', '") . '\')');
        }
    }

    private function log($result, $time)
    {
        // Записываем в лог как неудачный запрос
        if ($this->_failureLogger !== null && !$result)
        {
            $this->_failureLogger->log(
                'Error #' . $this->errno . ': ' . $this->error . 
                ' [' . $this->lastQuery . ']');
        }

        // Записываем в лог как медленный запрос
        if ($this->_slowLogger !== null && $time > $this->_slowTime)
        {
            $this->_slowLogger->log( $time . ' s: ' . $this->lastQuery);
        }

        // Записываем лог всех запросов
        if (null !== $this->_allLogger)
        {
            $this->_allLogger->log($this->lastQuery);
        }
    }

    private function curtime()
    {
        return microtime(true);
        //Считываем текущее время
        //$mtime = microtime();
        //Разделяем секунды и миллисекунды
        //$mtime = explode(" ",$mtime);
        //Составляем одно число из секунд и миллисекунд
        //return $mtime[1] + $mtime[0];
    }
}
?>