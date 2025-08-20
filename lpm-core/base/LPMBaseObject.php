<?php
/**
 * Базовый объект модели.
 */
class LPMBaseObject extends StreamObject
{
    private static $_queryBuilder;

    /**
     * @return DBConnect
     */
    protected static function getDB()
    {
        return LPMGlobals::getInstance()->getDBConnect();
    }

    /**
     * @return \GMFramework\DBQueryBuilder
     */
    protected static function getQueryBuilder()
    {
        if (self::$_queryBuilder === null) {
            $db = self::getDB();
            self::$_queryBuilder = new \GMFramework\DBQueryBuilder($db, $db->prefix);
        }
        return self::$_queryBuilder;
    }
    
    /**
     * Строит SQL запрос с помощью конструктора запросов \GMFramework\DBQueryBuilder
     * @return string
     */
    protected static function buildQuery($sqlHash, $tables = null) 
    {
        return self::getQueryBuilder()->buildQuery($sqlHash, $tables);
    }

    /**
     * Выполняет SQL запрос, построенный помощью конструктора запросов \GMFramework\DBQueryBuilder
     * @return mysqli_result|bool
     */
    protected static function buildAndExecute($sqlHash, $tables = null) 
    {
        $db = self::getDB();
        $sql = self::buildQuery($sqlHash, $tables);
        return $db->query($sql);
    }

    protected static function loadAndParse($hash, $class)
    {
        $res = self::loadFromDb($hash);
        $list = StreamObject::parseListResult($res, $class);
        return $list;
    }

    protected static function loadAndParseSingle($hash, $class)
    {
        $list = self::loadAndParse($hash, $class);
        return empty($list) ? null : $list[0];
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

    /**
     * Строит запрос и сохраняет данные в БД с помощью 
     * старого конструктора запросов из DBConnect.
     */
    protected static function buildAndSaveToDb($sqlHash)
    {
        $db = self::getDB();
        $res = $db->queryb($sqlHash);

        if (!$res) {
            throw new \GMFramework\ProviderSaveException();
        }
    }

    /**
     * Строит запрос и сохраняет данные в БД с помощью 
     * нового конструктора запросов из \GMFramework\DBQueryBuilder.
     */
    protected static function buildAndSaveToDbV2($sqlHash)
    {
        $res = self::buildAndExecute($sqlHash);

        if (!$res) {
            throw new \GMFramework\ProviderSaveException();
        }
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
