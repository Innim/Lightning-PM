<?php
/**
 * Базовый объект модели.
 */
class LPMBaseObject extends StreamObject
{
    protected static function getDB()
    {
        return LPMGlobals::getInstance()->getDBConnect();
    }

    protected static function loadFromDb($hash, $tables = null)
    {
        $res = self::getDB()->queryb($hash, $tables);
        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }
        return $res;
    }

    protected static function loadValFromDb($table, $field, $where)
    {
        $res = self::getDB()->querybSingle([
            'SELECT' => $field,
            'FROM'   => $table,
            'WHERE'  => $where,
            'LIMIT'  => 1
        ]);

        return $res[$field];
    }

    protected static function loadIntValFromDb($table, $field, $where)
    {
        return intval(self::loadValFromDb($table, $field, $where));
    }

    protected static function getDateStr($date)
    {
        if ($date == 0) {
            return  '';
        }

        return DateTimeUtils::date(
            DateTimeFormat::DAY_OF_MONTH_2 . '-' .
            DateTimeFormat::MONTH_NUMBER_2_DIGITS . '-' .
            DateTimeFormat::YEAR_NUMBER_4_DIGITS,
            $date
        );
    }
    
    public static function getDate4Input($date)
    {
        if ($date == 0) {
            return  '';
        }

        return DateTimeUtils::date(
            DateTimeFormat::DAY_OF_MONTH_2 . '/' .
            DateTimeFormat::MONTH_NUMBER_2_DIGITS . '/' .
            DateTimeFormat::YEAR_NUMBER_4_DIGITS,
            $date
        );
    }
    
    protected static function getDateTimeStr($date)
    {
        if ($date == 0) {
            return  '';
        }
                
        return DateTimeUtils::date(
            DateTimeFormat::DAY_OF_MONTH_2 . '.' .
            DateTimeFormat::MONTH_NUMBER_2_DIGITS . '.' .
            DateTimeFormat::YEAR_NUMBER_4_DIGITS . ' ' .
            DateTimeFormat::HOUR_24_NUMBER_2_DIGITS . ':' .
            DateTimeFormat::MINUTES_OF_HOUR_2_DIGITS,
            $date
        );
    }
    
    protected function getShort($text, $len = 100)
    {
        $txtLen = mb_strlen($text, 'UTF-8');
        if ($txtLen > $len) {
            if (preg_match('/(^[\w\W]{0,' . $len . '}\s{1})/u', $text, $matches)) {
                $text = trim($matches[1]);
            } else {
                $text = mb_substr($text, 0, $len, 'UTF-8');
            }
            
            $text .= '...';
        }
        
        return $text;
    }
    
    protected function getRich($text)
    {
        $text = str_replace("\n", '<br/>', $text);
        return $text;
    }

    public function parseData($hash)
    {
        return $this->loadStream($hash);
    }
}
