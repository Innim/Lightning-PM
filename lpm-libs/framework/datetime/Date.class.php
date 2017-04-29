<?php
namespace GMFramework;

/**
 * Объект даты
 * @package ru.vbinc.gm.framework.datetime
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.3
 * @access public
 */
class Date implements IStreamObject
{
  private $_u;
  private $_undefined;
  private $_ignoreTime;

  /**
   * Если нужно создать объект даты с неопределенным значением, 
   * то нужно передать null в качастве значения unixtime
   * @param float $unixtime
   * @param int $ignoreTime имеет значение только дата, время можно игнорировать
   */
  function __construct($unixtime = NAN, $ignoreTime = false) 
  {
    $this->_undefined = $unixtime === null;
    $this->_ignoreTime = $ignoreTime;

    if (!$this->_undefined)
    {
      $this->_u = is_nan($unixtime) ? (float)DateTimeUtils::$currentDate : $unixtime;
    }
  }

  function __toString()
  {
    return $this->toMysql();
  }

  /**
   * Определяет задано ли значение даты
   * @return boolean true, если значение даты не определено
   */
  public function isUndefined()
  {
    return $this->_undefined;
  }

  /**
   * Определяет можно ли игнорировать время 
   * (используется в случае если имеет значнеие только дата)
   * @return boolean
   */
  public function isIgnoreTime()
  {
    return $this->_ignoreTime;
  }

  /**
   * Сбрасывает дату на начала дня текущей установленной даты
   */
  public function setDayBegin() 
  {
    $this->_u = (int)DateTimeUtils::dayStart(DateTimeFormat::UNIXTIME_SECONDS, $this->_u);
    $this->_undefined = false;
  }

  /**
   * Сбрасывает дату на текущую
   */
  public function setNow() 
  {
    $this->_u = (float)DateTimeUtils::date();//$currentDate;
    $this->_undefined = false;
  }

  /**
   * Отнимает из текущей даты переданную и возвращает количество секунд разницы
   * @param GMDate $date
   * @return int количество секунд
   */
  public function diff( Date $date ) 
  {
    return $this->_u - $date->getUnixtime();
  }

  /**
   * Возвращает количество полных дней разности между датами
   * @param GMDate $date
   */
  public function diffDays( Date $date ) 
  {
    return floor( $this->diff( $date ) / 86400 );
  }

  public function addDays( $value = 1 ) 
  {
    return $this->addSec( $value * 86400 );
  }

  public function addHours( $value = 1 ) 
  {
    return $this->addSec( $value * 3600 );
  }

  public function addMinutes( $value = 1 ) 
  {
    return $this->addSec( $value * 60 );
  }

  public function addSec( $value = 1 ) 
  {
    $this->_u += $value;
    return $this->_u;
  }

  public function getUnixtime() 
  {
    return $this->_u;
  }

  public function toMysql($datetime = true)
  {
      return DateTimeUtils::mysqlDate($this->getUnixtime(), $datetime);
  }

  public function setUnixtime($value) 
  {
    $this->_u = $value;
    $this->_undefined = false;
  }

  public function setUndefined() 
  {
    $this->_u         = null;
    $this->_undefined = true;
  }

  /**
   * Возвращает отформатированную дату
   * @param  string $format строка формата
   * @return string отформатированная дата (если дата не определена - вернет пустую строку)
   * @see  GMFramework\DateTimeUtils::date()
   * @see  GMFramework\DateTimeFormat
   */
  public function format($format)
  {
    return $this->isUndefined() ? '' : DateTimeUtils::date($format, $this->_u);
  }

  public function getClientObject($addfields = null)
  {
    return $this->getUnixtime();
  }

  public function loadStream($data)
  {
    if (!$data || $data === '0000-00-00 00:00:00' || $data === '0000-00-00')
    {
      // Дата не определена
      $this->setUndefined();
    }
    else 
    {
      // Устанавливаем unixtime
      $this->setUnixtime(is_numeric($data) ? (float)$data : strtotime($data));
    }
  }

  public function equals(Date $date)
  {
      return $this->isUndefined() && $date->isUndefined() || 
        $this->getUnixtime() == $date->getUnixtime();
  }

  /**
   * Устанавливает значение 
   * @param Date $date 
   */
  public function setTo(Date $date)
  {
      if ($date->isUndefined()) 
        $this->setUndefined();
      else 
        $this->setUnixtime($date->getUnixtime());
  }

  /**
   * Изменяет значение
   * @param  Date|float|null $value Устанавливаемое значение
   * @return boolean Вернёт true, если значение изменилось
   */
  public function change($value)
  {
      $changed = false;
      if ($value instanceof Date)
      {
          if (!$this->equals($value))
          {
              $this->setTo($value);
              $changed = true;
          }
      }
      else if ($value === null || $value === '') 
      {
          if (!$this->isUndefined())
          {
              $this->setUndefined();
              $changed = true;
          }
      }
      else
      {
          $u = is_numeric($data) ? (float)$value : strtotime($value);
          if ($this->isUndefined() || $this->getUnixtime() != $u)
          {
              $this->setUnixtime($u);
              $changed = true;
          }
      }

      return $changed;
  }
}
?>