<?php
namespace GMFramework;

/**
 * Use the static method getInstance to get the object.
 * @package ru.vbinc.gm.framework.utils
 * @author linblow (edited by GreyMag)
 * from PHP.net
 * 
 */
class Session
{
    // THE only instance of the class
    private static $_instance = null;
    
    /**
    *    Returns THE instance of 'Session'.
    *    The session is automatically initialized if it wasn't.
    *    
    *    @return Session
    **/
    
    public static function getInstance($sessionId = '')
    {
        if (self::$_instance === null)
        {
            self::$_instance = new Session();
        }
        
        self::$_instance->startSession($sessionId);
        
        return self::$_instance;
    }

    public static function hasInstance()
    {
        return self::$_instance !== null;
    }
    
    private $_started = false;

    private function __construct() {}
    
    /**
    *    (Re)starts the session.
    *    
    *    @return    bool    TRUE if the session has been initialized, else FALSE.
    **/
    
    public function startSession($sessionId = '')
    {
        if (!$this->_started)
        {
            // GMF2DO проверить корректность проверки
            if ($this->getId() === null) 
            {
            	if (!empty($sessionId)) session_id($sessionId);
            	$this->_started = session_start();
            }
            else $this->_started = true;
        }
        
        return $this->_started;
    }

    /**
     * Удаляет все текущие данные, уничтожает сессию и создает новую, 
     * с новым идентификатором
     * @return bool Сессия инициализирована
     */
    public function restartSession()
    {
        if (!$this->_started) $this->startSession();

        if ($this->_started)
        {
            session_unset();
            session_regenerate_id(true);
        }
        
        return $this->_started;
    }

    /**
     * Возвращает идентификатор текущей сессии
     * @return string|null Если сессия не создана - вернет null
     */
    public function getId() 
    {
        $sessionId = session_id();
        return empty($sessionId) ? null : $sessionId;
    } 
    
    /**
     * Получить значение переменной по имени
     * @param mixed $var
     */
    public function get($var)
    {
    	return $this->$var;
    } 
    
    /**
     * Задать значение переменной
     * @param mixed $var
     */
    public function set($var, $value)
    {
        $this->$var = $value;
    }
    
    /**
     * Уничтожить переменную по имени
     * @param $var
     */
    public function unsetVar($var)
    {
        unset($this->$var);
    } 
    
    
    /**
    *    Stores datas in the session.
    *    Example: $instance->foo = 'bar';
    *    
    *    @param    name    Name of the datas.
    *    @param    value    Your datas.
    *    @return    void
    **/
    
    public function __set($name, $value)
    {
        $_SESSION[$name] = $value;
    }
    
    
    /**
    *    Gets datas from the session.
    *    Example: echo $instance->foo;
    *    
    *    @param    name    Name of the datas to get.
    *    @return    mixed    Datas stored in session.
    **/
    
    public function __get($name)
    {
        if (isset($_SESSION[$name]))
        {
            return $_SESSION[$name];
        }
    }
    
    
    public function __isset($name)
    {
        return isset($_SESSION[$name]);
    }
    
    
    public function __unset($name)
    {
        unset($_SESSION[$name]);
    }
    
    
    /**
    *    Destroys the current session.
    *    
    *    @return    bool    TRUE is session has been deleted, else FALSE.
    **/
    
    public function destroy()
    {
        if ($this->_started)
        {
            session_unset();
            $this->_started = !session_destroy();
            //unset($_SESSION);
            
            return !$this->_started;
        }
        
        return false;
    }
}
?>