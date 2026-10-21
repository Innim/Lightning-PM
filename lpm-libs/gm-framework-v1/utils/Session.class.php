<?php
/**
 * Use the static method getInstance to get the object.
 * @package ru.vbinc.gm.framework.utils
 * @author linblow (edited by GreyMag)
 * from PHP.net
 * 
 */
class Session
{
    const SESSION_STARTED = true;
    const SESSION_NOT_STARTED = false;
    
    // THE only instance of the class
    private static $_instance;
    
    /**
    *    Returns THE instance of 'Session'.
    *    The session is automatically initialized if it wasn't.
    *    
    *    @return Session
    **/
    
    public static function getInstance( $sessionId = '' )
    {
        if ( !isset(self::$_instance))
        {
            self::$_instance = new Session();
        }
        
        self::$_instance->startSession( $sessionId );
        
        return self::$_instance;
    }
    
    // The state of the session
    private $_sessionState = self::SESSION_NOT_STARTED;

    private function __construct() {}
    
    /**
    *    (Re)starts the session.
    *    
    *    @return    bool    TRUE if the session has been initialized, else FALSE.
    **/
    
    public function startSession( $sessionId = '' )
    {
        if ( $this->_sessionState == self::SESSION_NOT_STARTED )
        {
            // GMF2DO проверить корректность проверки
            if (!session_id()) {
            	if ($sessionId != '') session_id( $sessionId );
            	$this->_sessionState = session_start();
            }
            else $this->_sessionState = true;
        }
        
        return $this->_sessionState;
    }
    
    /**
     * Получить значение переменной по имени
     * @param mixed $var
     */
    public function get( $var )
    {
    	return $this->$var;
    } 
    
    /**
     * Задать значение переменной
     * @param mixed $var
     */
    public function set( $var, $value )
    {
        $this->$var = $value;
    }
    
    /**
     * Уничтожить переменную по имени
     * @param $var
     */
    public function unsetVar( $var )
    {
        unset( $this->$var );
    } 
    
    
    /**
    *    Stores datas in the session.
    *    Example: $instance->foo = 'bar';
    *    
    *    @param    name    Name of the datas.
    *    @param    value    Your datas.
    *    @return    void
    **/
    
    public function __set( $name , $value )
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
    
    public function __get( $name )
    {
        if ( isset($_SESSION[$name]))
        {
            return $_SESSION[$name];
        }
    }
    
    
    public function __isset( $name )
    {
        return isset($_SESSION[$name]);
    }
    
    
    public function __unset( $name )
    {
        unset( $_SESSION[$name] );
    }
    
    
    /**
    *    Destroys the current session.
    *    
    *    @return    bool    TRUE is session has been deleted, else FALSE.
    **/
    
    public function destroy()
    {
        if ( $this->_sessionState == self::SESSION_STARTED )
        {
            $this->_sessionState = !session_destroy();
            unset( $_SESSION );
            
            return !$this->_sessionState;
        }
        
        return false;
    }
}
?>