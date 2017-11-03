<?php
namespace GMFramework;

/**
 * Хранилище экземпляров классов: которые должны создаваться один раз 
 * (по первому запросу)
 * @package ru.vbinc.gm.framework.store
 * @author greymag
 * @version 0.1
 * @copyright 2013
 * @see  SingletonsStore::initFields()
 * @see  SingletonsStore::createInstance()
 */
abstract class SingletonsStore extends StoreAbstract
{
    // Название свойства => инстанция
    protected $_instances = array();
    // Список зарегистрированых классов название свойства => класс
    protected $_register  = array();
    
    // namespace по умолчанию для регистрируемых классов
    private $_namespace = '';

    function __construct()
    {
        parent::__construct();

        $this->initFields();
    }

    function __get($field)
    {
        if (isset($this->_register[$field]))
        {
            return $this->getInstance($field, true);
        }
    }

    /**
     * 
     * @param  string  $field Имя поля, содержащего инстанцию
     * @return 
     */
    protected function getInstance($field, $safe = false)
    {
        if (isset($this->_register[$field]))
        {
            if (!isset($this->_instances[$field]))
            {
                $instance = $this->createInstance($this->_register[$field]);
                $this->_instances[$field] = $instance;
            }
            else 
            {
                $instance = $this->_instances[$field];
            }

            return $instance;
        }
        else 
        {
            return null;
        }
    }

    /**
     * Инициализирует свойства класса, сопоставляя им соответствующие классы 
     * с помощью метода <code>registerClass()</code>.
     * Метод вызывается автоматически в конструкторе 
     * и рекомендуется к переопределении в наследниках для создания свойств хранилища.
     * @see  SingletonsStore::registerClass()
     */
    protected function initFields()
    {

    }

    /**
     * Устанавливает пространство имен по умолчанию для регистрируемых классов 
     * (будет подставлено к переданному имени класса)
     * @param string $value пространство имен
     * @see  SingletonsStore::registerClass()
     */
    protected function setNamespace($value)
    {
        if ($value != '' && mb_substr($value, -1) != '\\') $value .= '\\';
        $this->_namespace = $value;
    }

    /**
     * Регистрирует класс, назначая указанному свойству инстанцию этого класса
     * (инстанция будет создана только по первому запросу)
     * @param  string $field название поля
     * @param  string $class названия класса
     * @see  SingletonsStore::createInstance()
     * @see  SingletonsStore::setNamespace()
     */
    protected function registerClass($field, $class)
    {
        if (isset($this->_register[$field]))
            throw new Exception('Попытка повторной регистрации свойства ' . $field);
            
        $this->_register[$field] = $this->_namespace . $class;
    }

    /**
     * Создает инстанцию класса. 
     * Реализация по умолчанию вызывает конструктор без параметров, 
     * наследники могут переопределять метод для изменения повоедения.
     * @param  string $class класс
     * @return object созданная инстанция класса
     */
    protected function createInstance($class)
    {
        return new $class();
    }
}
?>