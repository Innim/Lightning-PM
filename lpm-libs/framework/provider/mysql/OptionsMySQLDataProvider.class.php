<?php
namespace GMFramework;

/**
 * Провайдер данных, для загрузки опций из MySQL<br>
 * Настройки должны храниться в таблице следующей структуры:
 * <code>
 * CREATE TABLE `prefix_tablename` (
 * `option` varchar(32) NOT NULL COMMENT 'опция',
 * `value` text NOT NULL COMMENT 'её значение',
 * PRIMARY KEY  (`option`)
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='таблица настроек';
 * </code> 
 * @author greymag
 * @see Options
 * @see OptionsMySQLDataProvider::getTableName()
 */
abstract class OptionsMySQLDataProvider extends MySQLDataProvider implements IOptionsDataProvider
{
    function __construct(DBConnect $db)
    {
        parent::__construct($db);
    }

    /**
     * Определяет имя таблицы опций (без префикса).
     * Префикс должен подставляться автоматически при использовании метода 
     * <code>DBConnect::queryt()</code>
     * @return String
     */
    abstract protected function getTableName();

    protected function getNameField()
    {
        return 'option';
    }

    protected function getValueField()
    {
        return 'value';
    }

    public function saveOptions(Options $options)
    {
        if ($options->hasChanged())
        {
            $raw = $this->getChangedData4Save($options);

            $tableName = $this->getTableName();
            if ($tableName == '') throw new Exception('Table name can not be empty');
            $db = $this->_db;
            if (!$db) throw new Exception('Method Options::getDBConnect return null');

            $sql = 'UPDATE `%s` SET `' . $this->getValueField() . '` = ? ' .
                    'WHERE `' . $this->getNameField() . '` = ?';
            $prepare = $db->preparet($sql, $tableName);
            if (!$prepare) throw new DBException($db, 'Can not to save options to database');

            foreach ($raw as $option => $value) 
            {
                $prepare->bind_param('ss', $value,  $option);
                $prepare->execute();
            }

            $prepare->close();

            // Помечаем как сохраненный
            $options->resetChanged();
        }
    }

    public function loadOptions($options)
    {
        $result = array();

        $nameField  = $this->getNameField();
        $valueField = $this->getValueField();

        if (count( $options ) > 0) 
        {
            $tableName = $this->getTableName();
            if ($tableName == '') throw new Exception('Table name can not be empty');
            
            $dbConnect = $this->_db;
            if (!$dbConnect) throw new Exception('Method Options::getDBConnect return null');

            $where = '';        
        
            foreach ($options as $optionName) 
            {
                $optionName = $dbConnect->escape_string_t( $optionName );
                $where .= ( ( $where == '' ) ? '' : ',' ) . "'" . $optionName . "'";
            }
    
            $sql = 'SELECT `' . $nameField . '`, `' . $valueField . '` FROM `%s` ' .
                    'WHERE `' . $nameField . '` IN (' . $where . ')';
             
            if (!$query = $dbConnect->queryt( $sql, $tableName ))
                 throw new DBException( $dbConnect, 'Can not to load options from database');

            while ($option = $query->fetch_assoc()) {
                $result[$option[$nameField]] = TypeConverter::autoCastValue( $option[$valueField] );
            }
            $query->close();
        } 

        return $result;
    }
}
?>