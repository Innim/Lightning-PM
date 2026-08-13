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
               
        $packages = explode( '.', $this->_serviceName );
        if( count( $packages ) > 1 ) {
        	$this->_serviceName = array_pop( $packages );        	
        	$this->_servicesDir .= implode( '/', $packages ) . '/';
        }
        
        // ищем и подключаем файл с сервисом, если найдём
        // это для имени типа ИМЯ_КЛАССА(.*).php
        // теперь будем делать проще - зададим формат жёстко
        /*$services = scandir( $this->_servicesDir );
        foreach( $services as $serviceFile ) {
        	$nameParts = explode( '.', $serviceFile );
        	$count = count( $nameParts );
        	if( $count > 1 && $nameParts[0] == $this->_serviceName && $nameParts[$count - 1] == 'php' ) {
        		include_once( $this->_servicesDir . $serviceFile );
        		break;
        	}
        }*/
        $serviceFile = $this->_servicesDir . $this->_serviceName . '.php';        
        if( !file_exists( $serviceFile ) || !is_file( $serviceFile ) )
            throw new F2PException( 'Service file not found', F2PException::ERRNO_UNKNOWN_SERVICE );
        
        include_once( $serviceFile );
        
        if( !class_exists( $this->_serviceName ) ) 
            throw new F2PException( 'Service not exist or not load', 
                                    F2PException::ERRNO_UNKNOWN_SERVICE );
        
        $this->_service = new $this->_serviceName;

        if( !method_exists( $this->_service, $this->_methodName ) ) 
            throw new F2PException( 'Method not exist in this service', 
                                    F2PException::ERRNO_UNKNOWN_METHOD );
        
        $this->_method = new ReflectionMethod( $this->_serviceName, $this->_methodName );
        
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