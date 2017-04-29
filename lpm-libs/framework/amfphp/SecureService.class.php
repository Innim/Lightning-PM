<?php
namespace GMFramework;

/**
 * Защищённый сервис - требует сначала авторизации
 * @package ru.vbinc.gm.framework.amfphp
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1
 */
class SecureService extends Service
{
	/**
	 * Имя сессии
	 * @var string
	 */
	const AUTH_SESSION = 'authInfo';

	/**
	 * Авторизован сейчас или нет
	 * @var bool
	 */
	protected $_isLogin = false;
	
	/**
	 * Информация авторизации
	 * @var AuthInfo
	 */
	protected $_authInfo;

	/**
	 * Методы, вызывать которые можно без авторизации
	 * @var array
	 */
	protected $_allowMethods = array();
	
	function __construct()
	{
		parent::__construct();
		array_push($this->_allowMethods, 'auth');
	}

	/**
	 * Авторизация по-умолчанию
	 * @param string $pass пароль
	 * @param string $login логин
	 */
	protected function defaultAuth( $pass, $login = '' )
	{
		$this->_success = $this->checkAuth( $this->_db->escape_string( $login ),
		                                    $this->_db->escape_string( $pass ) );
		
        if (!$this->initAuthInfo( $this->_success, $login )) return false;
		return true;
	}
	
	/**
	 * Иницализирует информацию авторизации
	 * @param $success успешная авторизация или нет
	 */
	protected function initAuthInfo( $success, $login )
	{
	    $session = Session::getInstance();
	    	    
        if ($success) {
	        try {	            
	        	$this->_authInfo = $this->createAuthInfo( $login );
	        	$session->set( $this->getSessionName(), serialize( $this->_authInfo ) );
	        } catch (\Exception $e) {
	            $success = false;
	        }        	            
        } 
        
        if (!$success) {
             $session->unsetVar( $this->getSessionName() );
        }
        
        return $success;
	}

	/**
	 * Проверить авторизацию
	 * Это метод надо переопределять в наследниках
	 * @param string $login
	 * @param string $pass
	 * @return boolean
	 */
	protected function checkAuth( $login, $pass )
	{
		return false;
	}
	
	/**
	 * Создаёт объект с информацией об авторизованном юзере
	 * @param string $login
	 * @return AuthInfo
	 */
	protected function createAuthInfo( $login )
	{
		return new AuthInfo( $login );
	}
    
    /**
     * Возвращает имя сессии
     * @return String
     */
    protected function getSessionName()
    {
        return self::AUTH_SESSION;
    }

	/**
	 * Метод, который вызывается перед вызовом любого метода
	 * @param String $calledFunc
	 * @return bool
	 */
	public function beforeFilter( $calledFunc )
	{
		//if (in_array( $calledFunc, $this->_allowMethods )) return true;

		return $this->initCurAuth() || in_array( $calledFunc, $this->_allowMethods );
	}
    
	protected function initCurAuth() {
		// GMF2DO почему не через глобалс тогда уж все пустить?
		$session = Session::getInstance();
		$this->_authInfo = unserialize( $session->get( $this->getSessionName() ) );
        
        /*print_r( $this->getSessionName() . '|' );
        print_r( $session->get( $this->getSessionName() ) ); echo '|';
        print_r( $this->_authInfo ); echo '|';
        exit;*/

        return $this->isAuth();
	}
	
	protected function isAuth() {
		return ( (boolean)$this->_authInfo );
	}
}
?>