<?php

/**
 * @author GreyMag
 * @copyright 2009
 */

/**
 * Utils
 * Класс, содержащий вспомогательные функции для работы с датой и временем
 * @package mag
 * @author GreyMag
 * @copyright 2009
 * @version 0.5
 * @access public
 */
class WSUtils
{
    /**
     * Проверяет время на соответствие формату ЧЧ:ММ
     * @return bool
     */
    public function checkTime(/*String*/$time)
    {
        if (!preg_match("/^[0-9]{1,2}:[0-9]{2}$/", $time)) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Проверяет время на соответствие формату ГГГГ-ММ-ДД
     * @return bool
     */
    public function checkDate(/*String*/$date)
    {
        if (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $date)) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     *  Изменяет формат даты из базы на пользовательский
     *  @return Строку даты в соответсвии с printFormat
     */
    public function printMysqlDate(/*String*/$mysqlDate, /*String*/$printFormat = '')
    {
        preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/", $mysqlDate, $date);
    
        $timestamp = mktime((int)$date[4], (int)$date[5], (int)$date[6], (int)$date[2], (int)$date[3], (int)$date[1]);
    
        if (empty($printFormat)) {
            return $timestamp;
        } else {
            setlocale(LC_ALL, 'ru_RU.UTF-8', 'rus_RUS.UTF-8', 'Russian_Russia.65001');
            return strftime($printFormat, $timestamp);
        }
    }
    
    /**
     * Расчет длины временного промежутка между двумя значениями времени в пределах одного дня
     * @return Строку с длиной промежутка в зависимости от format
     */
    public function getTimeIntervar(/*String*/$timeStart, /*String*/$timeEnd, /*String*/$format = 'time')
    {
        if (!$this->checkTime($timeStart) || !$this->checkTime($timeEnd)) {
            return false;
        }

        // переводим в секунды
        $timeInterval = $this->timeToSeconds($timeEnd) - $this->timeToSeconds($timeStart);
        
        return $this->secondsToTime($timeInterval, $format);
    }
    
    /**
     * Расчет разности между двумя датами
     * @param String $dateStart Дата-вычитаемое в формате ГГГГ-ММ-ДД
     * @param String $dateEnd Дата-уменьшаемое в формате ГГГГ-ММ-ДД
     * @return int Количество дней
     */
    public function getDaysInterval(/*String*/$dateStart, /*String*/$dateEnd)
    {
        if (!$this->checkDate($dateStart) || !$this->checkDate($dateEnd)) {
            return false;
        }
        
        $daysInterval = $this->printMysqlDate($dateEnd . ' 00:00:00') - $this->printMysqlDate($dateStart . ' 00:00:00');
        
        return (int)($daysInterval / (24 * 60 * 60));
    }
    
    /**
     * Расчёт количества секунд с начала дня для времени в формате ЧЧ:ММ
     * @return int
     */
    private function timeToSeconds(/*String*/$time)
    {
        if (!$this->checkTime($time)) {
            return $this->error('Неверный формат даты');
        }
        
        
        preg_match("/^([0-9]{1,2}):([0-9]{2})$/", $time, $timeArray);
        
        return count($timeArray) == 3 ? ($timeArray[1] * 60 + $timeArray[2]) * 60 : false;
    }
    
    /**
     * Получение времени в формате ЧЧ:ММ по количеству секунд
     * @return Строку времени в зависимости от format
     */
    private function secondsToTime(/*int*/$seconds, /*String*/$format)
    {
        $seconds = (int)$seconds;
        
        switch ($format) {
            case 'number':
            {
                $hours = $seconds / (60 * 60);
                return round($hours, 2);
            } break;
            default:
            {
                $minutes = floor($seconds / 60);
                $hours = floor($minutes / 60);
                $minutes = $minutes % 60;
                
                return $hours . ':' . $minutes;
            }
        }
    }
    
    /**
     * Расчёт дат начала и конца недели по её номеру
     * @return Ассоциативный массив с полями start, end
     */
    public function getWeek(/*int*/$weekNumber)
    {
        $weekNumber = (int)$weekNumber;
        
        $currentUnixTime =  time() + TIMEADJUST;
        
        // получаем номер текущей недели
        $currentWeek = date('W', $currentUnixTime);
        
        // теперь получим время начала недели
        $currentUnixTime -= (((date('N', $currentUnixTime) - 1) * 24 + date('H', $currentUnixTime)) * 60 + date('i', $currentUnixTime)) * 60 + date('s', $currentUnixTime);
        
        // получаем время начала и конца искомой недели
        $secondsInWeek =  7 * 24 * 60 * 60;
        $timeStart = $currentUnixTime - ($currentWeek - $weekNumber) * $secondsInWeek;
        $timeEnd = $timeStart + $secondsInWeek - 3601;
        // echo date('d m y h:i:s', $timeStart) . ' -> ' . date('d m y h:i:s', $timeStart + $secondsInWeek).'<br/>';
        
        // преобразуем в даты
        $dateFormat = 'Y-m-d';
        return array( 'start' => date($dateFormat, $timeStart), 'end' => date($dateFormat, $timeEnd) );
    }
    
    public function getMonth(/*int*/$monthNumber)
    {
        $monthNumber = (int)$monthNumber;
        
        $currentUnixTime =  time() + TIMEADJUST;
        
        // получаем номер текущей недели
        $currentMonth = date('m', $currentUnixTime);
        
        // теперь получим время начала недели
        $currentUnixTime -= (((date('d', $currentUnixTime) - 1) * 24 + date('H', $currentUnixTime)) * 60 + date('i', $currentUnixTime)) * 60 + date('s', $currentUnixTime);
        
        // получаем время начала и конца искомой недели
        switch ($monthNumber) {
            case 1:
            case 3:
            case 5:
            case 7:
            case 8:
            case 10:
            case 12: $k = 31; break;
            case 2: $k = (date('y') % 4 == 0) ? 29 : 28 ; break;
            default: $k = 30;
        }
        $secondsInMonth =  $k * 24 * 60 * 60;

        $timeStart = $currentUnixTime - ($currentMonth - $monthNumber) * $secondsInMonth;
        $timeEnd = $timeStart + $secondsInMonth - 1;
        
        // преобразуем в даты
        $dateFormat = 'Y-m-d';
        return array( 'start' => date($dateFormat, $timeStart), 'end' => date($dateFormat, $timeEnd) );
    }
    
    /**
     * Обработка времени из базы - убирает секунды
     * @param String $dbTime время из базы данных
     * @return Строку времени в формате ЧЧ:ММ
     */
    public function getTimeFromDB(/*String*/$dbTime)
    {
        if (!preg_match("/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/", $dbTime)) {
            return false;
        }
        
        return substr($dbTime, 0, 5);
    }
    
    /**
     * Прибавление дней к дате
     * @param String $date изначальная дата
     * @param int $days количество прибавляемых дней
     * @return Строка с итоговой датой в формате ГГГГ-ММ-ДД
     */
    public function addDays(/*String*/$date, /*int*/$days)
    {
        if (!$this->checkDate($date)) {
            return false;
        }
        $days = (int)$days;
        
        $unixDate = $this->printMysqlDate($date . ' 00:00:00');
        $resultUnixDate = $unixDate + $days * 24 * 60 * 60;
         
        return DateTimeUtils::mysqlDate($resultUnixDate, false);
    }
}
