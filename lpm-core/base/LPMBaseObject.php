<?php
/**
 * Базовый объект модели.
 */
class LPMBaseObject extends StreamObject
{
    /**
     * Суффикс имени колонки, хранящей момент абсолютным временем.
     * @see LPMBaseObject::setVar()
     */
    private const UTC_COLUMN_SUFFIX = 'Utc';

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
     * Имя колонки - для подстановки туда, где конструктор запросов ждёт
     * значение. Нужно, например, чтобы сравнить колонку с колонкой:
     * <code>'ON' => ['`fl`.`fileId`' => self::col('f.fileId')]</code>.
     * Обычная строка в этом месте всегда экранируется как значение.
     * @param  string $name Имя колонки, при необходимости с именем таблицы
     *                      или алиасом через точку: `fileId`, `f.fileId`.
     * @return \GMFramework\DBColumn
     */
    protected static function col($name)
    {
        return new \GMFramework\DBColumn($name);
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

    protected static function buildAndExecuteSingle($sqlHash, $tables = null) {
        $result = self::buildAndExecute($sqlHash, $tables);
        if (!$result) {
            throw new \GMFramework\ProviderLoadException();
        }
        return $result->fetch_assoc();
    }

    protected static function loadAndParse($hash, $class)
    {
        $res = self::loadFromDb($hash);
        $list = StreamObject::parseListResult($res, $class);
        return $list;
    }

    protected static function loadAndParseV2($hash, $class)
    {
        $res = self::loadFromDV2($hash);
        $list = StreamObject::parseListResult($res, $class);
        return $list;
    }

    protected static function loadAndParseSingle($hash, $class)
    {
        $list = self::loadAndParse($hash, $class);
        return empty($list) ? null : $list[0];
    }

    protected static function loadAndParseSingleV2($hash, $class)
    {
        $list = self::loadAndParseV2($hash, $class);
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

    protected static function loadFromDV2($hash, $tables = null)
    {
        $res = self::buildAndExecute($hash, $tables);
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

        // Формат ISO (ГГГГ-ММ-ДД) — значение для нативного поля <input type="date">.
        return DateTimeUtils::date(
            DateTimeFormat::YEAR_NUMBER_4_DIGITS . '-' .
            DateTimeFormat::MONTH_NUMBER_2_DIGITS . '-' .
            DateTimeFormat::DAY_OF_MONTH_2,
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

    /**
     * Загружает значение колонки выборки в свойство модели.
     *
     * Колонка `<имя>Utc` наполняет свойство `<имя>`, если у модели есть такое
     * свойство и нет своего `<имя>Utc`. Значение колонки-близнеца при этом
     * сильнее значения одноимённого свойству столбца, а пустой близнец
     * оставляет свойству то, что уже было в него загружено.
     *
     * @param  string $var   Имя колонки выборки.
     * @param  mixed  $value Значение колонки.
     * @return bool true, если значение записано в свойство модели.
     */
    protected function setVar($var, $value)
    {
        $localField = $this->getFieldByUtcColumn($var);
        if ($localField !== null) {
            // Близнец перекрывает старую колонку, потому что в выборке идёт
            // сразу за ней. Пустой близнец - это либо отсутствие момента, либо
            // строка, записанная до его появления: решает старая колонка.
            if ($value === null) {
                return false;
            }

            $var = $localField;
        }

        return parent::setVar($var, $value);
    }

    /**
     * Возвращает свойство модели, которому соответствует колонка с абсолютным
     * временем.
     *
     * Пары «старая колонка - близнец» перечислены в миграции
     * `..._dates_absolute_timestamp_columns.php`.
     *
     * @param  string $column Имя колонки выборки.
     * @return string|null Имя свойства либо null, если колонка не является
     * близнецом свойства модели.
     */
    private function getFieldByUtcColumn($column)
    {
        $suffixLength = strlen(self::UTC_COLUMN_SUFFIX);
        if (substr($column, -$suffixLength) !== self::UTC_COLUMN_SUFFIX) {
            return null;
        }

        // Собственное свойство с таким именем сильнее: значение принадлежит ему.
        if (property_exists($this, $column)) {
            return null;
        }

        $field = substr($column, 0, -$suffixLength);

        return $field !== '' && property_exists($this, $field) ? $field : null;
    }
}
