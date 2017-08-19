<?php
namespace GMFramework;

/**
 * Список загружаемых объектов
 * @author GreyMag
 */
class StreamList extends ArrayList implements IStreamObject
{
    private $_className;

    function __construct($streamClass)
    {
        parent::__construct();

        if (!is_subclass_of($streamClass, __NAMESPACE__ . '\IStreamObject'))
            throw new Exception('Переданный класс '. $streamClass . ' не реализует интерфейс IStreamObject');

        $this->_className = $streamClass;
    }

    public function loadStream($rawList)
    {
        if (!is_array($rawList))
            throw new Exception(__CLASS__ . ' может парсить только массивы данных');

        $this->reset();
        foreach ($rawList as $rawData) 
        {
            $obj = $this->createStreamObject();
            $obj->loadStream($rawData);

            $this->push($obj);
        }
    }

    public function getClientObject($addfields = null)
    {
        $result = array();
        $list   = $this->getArray();

        foreach ($list as $item) {
            $result[] = $item instanceof IStreamObject ? $item->getClientObject() : $item;
        }

        return $result;
    }

    protected function createStreamObject()
    {
        $className = $this->_className;
        return new $className();
    }
}
?>