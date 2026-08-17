<?php
require_once( F2P_ROOT . 'core/exceptions/F2PException.php' );

/**
 * 
 * @version 0.1
 * @author GreyMag <greymag@gmail.com>
 *
 */
class Flash2PHP
{
	protected $_serviceName;
	protected $_methodName;
	protected $_params; 
	protected $_service;
	/**
	 * 
	 * @var ReflectionMethod
	 */
	protected $_method;
	
	protected $_servicesDir;

	private $_serviceParam;
	private $_methodParam;
	private $_paramsParam;
	/**
	 * Белый список сервисов - см. getAvailableServices()
	 * @var array|null
	 */
	private $_availableServices;

	function __construct()
	{
		$this->_servicesDir = F2P_ROOT . F2P_SERVICES_PATH;
		if( substr( $this->_servicesDir, -1, 1 ) != '/' ) $this->_servicesDir .= '/';
		
		if (F2P_USE_SHORT_NAMES) {
			$this->_serviceParam = 's';
			$this->_methodParam  = 'm';
			$this->_paramsParam  = 'p';
		} else {
			$this->_serviceParam = 'service';
            $this->_methodParam  = 'method';
            $this->_paramsParam  = 'params';
		}
	}
	
	/**
	 * Определяет, что тело POST-запроса превысило post_max_size.
	 * В этом случае PHP отбрасывает тело целиком ($_POST и $_FILES пусты),
	 * поэтому надёжный признак — размер тела (Content-Length) больше лимита.
	 * @return bool
	 */
	private function isPostSizeExceeded()
	{
		// Дешёвые отсечки в первую очередь: у нормального запроса тело разобрано,
		// поэтому подавляющее большинство отсеивается здесь, не доходя до парсинга ini.
		if ( ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
			|| !empty( $_POST ) || !empty( $_FILES ) ) {
			return false;
		}

		$contentLength = (int)( $_SERVER['CONTENT_LENGTH'] ?? 0 );
		if ( $contentLength <= 0 ) {
			return false;
		}

		// Есть конкретное подозрение (POST с телом, но $_POST/$_FILES пусты) —
		// только теперь сверяемся с лимитом. Лимит 0 означает «без ограничения».
		$maxBytes = $this->parseIniSize( ini_get( 'post_max_size' ) );

		return $maxBytes > 0 && $contentLength > $maxBytes;
	}

	/**
	 * Переводит значение размера из php.ini (например, "512M") в байты.
	 * @param string $value Значение вида "512M", "64K", "1G" или число байт.
	 * @return int Размер в байтах (0, если значение пустое).
	 */
	private function parseIniSize( $value )
	{
		$value = trim( (string)$value );
		if ( $value === '' ) {
			return 0;
		}

		$number = (int)$value;
		switch ( strtolower( substr( $value, -1 ) ) ) {
			case 'g': $number *= 1024;
			// no break
			case 'm': $number *= 1024;
			// no break
			case 'k': $number *= 1024;
		}

		return $number;
	}

	/**
	 * Возвращает белый список сервисов, доступных для вызова.
	 *
	 * Имя сервиса приходит из запроса, поэтому подставлять его в путь к файлу
	 * нельзя: значение вида "../../lpm-cli/migrate" подключило бы произвольный
	 * php-файл. Вместо проверок пути сверяемся со списком того, что вообще
	 * является сервисом - файлов вида ИМЯ_КЛАССА.php в директории сервисов.
	 *
	 * Список строится один раз за запрос. Межзапросный кэш не используется
	 * намеренно: директория маленькая, а протухший кэш прятал бы вновь
	 * добавленные сервисы до его сброса.
	 * @return array Ассоциативный массив [имя сервиса => путь до файла].
	 */
	private function getAvailableServices()
	{
		if( $this->_availableServices === null ) {
			$services = array();

			$files = @scandir( $this->_servicesDir );
			if( $files !== false ) {
				foreach( $files as $file ) {
					if( !preg_match( '/^([A-Za-z0-9_]+)\.php$/', $file, $matches ) ) continue;

					$path = $this->_servicesDir . $file;
					if( is_file( $path ) ) $services[$matches[1]] = $path;
				}
			}

			$this->_availableServices = $services;
		}

		return $this->_availableServices;
	}

	/**
	 * Определяет, что метод разрешено вызывать из запроса.
	 *
	 * Вызывать можно только публичные методы, объявленные в самом классе
	 * сервиса: служебные методы базовых классов наружу не отдаём. Поэтому
	 * наследование метода, доступного для вызова, от другого сервиса
	 * не поддерживается - такой метод надо объявлять в самом сервисе.
	 *
	 * Отдельно закрыт beforeFilter: его вызывает сам шлюз для проверки прав,
	 * и снаружи он вызываться не должен.
	 * @param ReflectionMethod $method
	 * @return bool
	 */
	private function isCallableMethod( ReflectionMethod $method )
	{
		if( !$method->isPublic() || $method->isStatic() ) return false;
		if( $method->getName() === 'beforeFilter' ) return false;

		return $method->getDeclaringClass()->getName() === $this->_serviceName;
	}

	public function init( $request )
	{
        // тело запроса превысило post_max_size и было отброшено PHP
        if ( $this->isPostSizeExceeded() ) {
            throw new F2PException(
                'Слишком большой размер запроса. Уменьшите размер или количество вложений.',
                F2PException::ERRNO_REQUEST_TOO_LARGE
            );
        }

        // пришел сжатый запрос
        if (isset($request['z'])) {
        	if (!function_exists( 'gzuncompress' )) {
        		 throw new F2PException( 'Can\'t uncompress request' , F2PException::ERRNO_COMPRESS_UNAVAILABLE );
        	}
        	
        	$requestStr = gzuncompress( base64_decode( str_replace( ' ', '+', urldecode( $request['z'] ) ) ) );
        	parse_str( $requestStr, $request );
        }
		
		if (!isset( $request[$this->_serviceParam] )) throw new F2PException( 'Service name not defined', F2PException::ERRNO_UNKNOWN_SERVICE );
        if (!isset( $request[$this->_methodParam ] )) throw new F2PException( 'Method name not defined', F2PException::ERRNO_UNKNOWN_METHOD );
        
        $this->_serviceName = $request[$this->_serviceParam];
        $this->_methodName  = $request[$this->_methodParam ];

        $services = $this->getAvailableServices();
        if( !is_string( $this->_serviceName ) || !isset( $services[$this->_serviceName] ) )
            throw new F2PException( 'Service file not found', F2PException::ERRNO_UNKNOWN_SERVICE );

        include_once( $services[$this->_serviceName] );

        if( !class_exists( $this->_serviceName ) )
            throw new F2PException( 'Service not exist or not load',
                                    F2PException::ERRNO_UNKNOWN_SERVICE );

        $this->_service = new $this->_serviceName;

        if( !is_string( $this->_methodName ) || !method_exists( $this->_service, $this->_methodName ) )
            throw new F2PException( 'Method not exist in this service',
                                    F2PException::ERRNO_UNKNOWN_METHOD );

        $this->_method = new ReflectionMethod( $this->_serviceName, $this->_methodName );

        if( !$this->isCallableMethod( $this->_method ) )
            throw new F2PException( 'Method is not available for call',
                                    F2PException::ERRNO_UNKNOWN_METHOD );

        $args = $this->_method->getParameters();
        
        $this->_params = ( !isset( $request[$this->_paramsParam] ) ) 
                        ? array()
                        : json_decode( $request[$this->_paramsParam] );
        if( !is_array( $this->_params ) ) 
            throw new F2PException( 'Wrong params', F2PException::ERRNO_WRONG_PARAMS );

        $countOptional = 0;
        foreach( $args as $param ) {
        	if( $param->isOptional() ) $countOptional++;
        }
        
        $countParams = count( $this->_params );
        $countArgs   = count( $args );
        if( $countParams < $countArgs - $countOptional || $countParams > $countArgs ) 
            throw new F2PException( 'Wrong number of arguments', F2PException::ERRNO_WRONG_NUM_ARGS );
         
        if( method_exists( $this->_service, 'beforeFilter' ) ) {
			try {
				if( !/*@*/$this->_service->beforeFilter( $this->_methodName ) ) throw new Exception();
			} catch (Exception $e) {
				throw new F2PException( 'Method call blocked by beforeFilter', F2PException::ERRNO_AUTH_BLOCKED ); 
			}
        }
	}
	
	public function execute()
	{
		try {			
			$result = $this->_method->invokeArgs( $this->_service, $this->_params );
			// TODO если вернули null, то ошибка			
			$this->answer( $result );
		}
		catch ( ReflectionException $e ) {
			$this->error( 'Call method error', F2PException::ERRNO_CALL_METHOD_ERROR );
		} 
		catch( Exception $e ) {
			$this->error( 'Execute method error', F2PException::ERRNO_EXECUTE_METHOD_ERROR );
		}
	}
	
	public function error( $error, $errno = 0 )
	{
        $this->answer( $this->generateError( $error, $errno ) );
	}
	
	public function exception( F2PException $e )
	{
		$this->error( $e->error, $e->errno );
	}
    
    public function simpleException( Exception $e )
    {
        $this->error( $e->getMessage(), $e->getCode() );
    }
	
	protected function answer( $obj )
	{
		$answer = json_encode( $obj );
		//$answer .= strlen( $answer );
		if( F2P_USE_COMPRESS && strlen( $answer ) > 40 ) {			
			if( function_exists( 'gzencode' ) ) 
			{
				header( "Content-Encoding: gzip" ); 
				$answer = gzencode( $answer );
			}
			else 
			{
				//if( F2P_DEBUG_MODE ) throw new F2PException( 'Compress could not be used. Check gzip lib for PHP' );
				$answer = json_encode( $this->generateError( 'Compress could not be used. Check gzip lib for PHP or disable F2P_USE_COMPRESS option' ) );
			}
		}
		
		echo $answer;
	}
	
	private function generateError( $error, $errno )
	{
		$obj = array( 'error' => $error );
        if( $errno > 0 ) $obj['errno'] = $errno;
		
		return $obj;
	}
		
} 
?>