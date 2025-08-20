<?php
namespace GMFramework;

/**
 * Класс подключения фреймворка.<br/>
 * Для работы фреймворка необходим PHP >= 5.4
 * @package ru.vbinc.gm.framework
 * @author GreyMag <greymag@gmail.com>
 * @version 1.3.1
 * 
 */
class GMFramework
{
    private static $_inited = false;

    /**
     * Определяет, включен ли режим отладки для приложения. 
     * Для включения такого режима необходимо определить константу <code>DEBUG</code>
     * и установить ей значение <code>true</code>.
     * @return boolean
     */
    public static function isDebugMode()
    {
        return ( defined( 'DEBUG' ) && DEBUG );
    }

    /**
     * Определяет, локально ли запущено приложение. 
     * Для включения такого редима необходимо определить константу <code>_LOCAL</code>
     * и установить ей значение <code>true</code>.
     * @return boolean
     */ 
    public static function isLocalMode()
    {
        return ( defined( '_LOCAL' ) && _LOCAL );
    }


    /**
     * Определяет, запущено ли приложение в режиме разработки
     * (локально и в отладке)
     * @return bool
     */
    public static function dev() 
    {
        return GMFramework::isDebugMode() && GMFramework::isLocalMode();
    }
 
	/**
	 * Использовать фреймворк
	 */
	public static function useFramework()
	{
        if (!self::$_inited) {
    		if( function_exists( '__autoload' ) ) {
    			spl_autoload_register( '__autoload' );
    		}
    		if ( !spl_autoload_register( self::getLoadFunc() ) ) {
    			throw new Exception('Could not register class autoload function ');
    		}
            self::$_inited = true;
        }
	}
	
	public static function addAutoload( $func )
	{
	   if (!spl_autoload_register( $func )) 
       {
            throw new Exception('Could not register autoload function ');
       }
	}
    
	/**
	 * Отменить регистрацию автолаод-функции
	 */
	protected function unregister() {
		spl_autoload_unregister( self::getLoadFunc() );
	}
    
	/**
	 * Загрузить классы фреймворка
	 * @param string $className
	 */
	public static function loadClass( $className )
	{
		$package = "/";
        $interface = false;

        /*$name = explode('\\', $className);
        if (count($name) == 0) return false;

        $className = array_pop($name);

        if (__NAMESPACE__ !== implode('\\', $name)) return false;*/

        // Если не подходит под простанство имен
        if (strpos($className, 'GMFramework\\') !== 0) return false;

        $className = mb_substr($className, 12);

        /*$namespace = __NAMESPACE__;
        $nsLen = mb_strlen($namespace);
        if ($nsLen > 0)
        {
            $namespace .= '\\';
            $nsLen++;

            // Если не подходит под простанство имен
            if (!strpos($className, __NAMESPACE__ . '\\') !== 0) return false;
            if ((string)mb_substr($className, 0, $nsLen) !== $namespace) return false;

            $className = mb_substr($className, $nsLen);
        }*/

		switch ($className)
		{
            // .
            case 'AppAbstract'              : break;
			// amfphp
			case 'AuthInfo'                 :
			case 'SecureService'            :
			case 'Service'                  : $package .= 'amfphp/'; break;
			// array
			case 'ArrayUtils'               :
			case 'ArrayList'                : $package .= 'array/'; break;
            // const
            case 'Enum'                     :
            case 'ErrorCode'                : $package .= 'const/'; break;
			// datetime
			case 'DateTimeUtils'            :
			case 'DateTimeFormat'           :
		    case 'Date'                     : $package .= 'datetime/'; break;
			// db
			case 'DBConnect'                :
            case 'DBConnectWithLog'         :
            case 'DBQueryBuilder'           :
			case 'MySqlDump'                : $package .= 'db/'; break;
            // exceptions
            case 'DBException'              : 
            case 'CommonException'          :
            case 'Exception'                :
            case 'ProviderException'        :
            case 'ProviderLoadException'    :
            case 'ProviderSaveException'    :
            case 'SocException'             : $package .= 'exceptions/'; break;
            // filesystem
            case 'FileSystemUtils'          : $package .= 'filesystem/'; break;
			// images
			case 'ImageResize'              : $package .= 'images/'; break;
			// mail
			case 'MailMessage'              :  
			case 'MailSender'               : $package .= 'mail/'; break;
			// math 
			// math.geom
			case 'Point'                    : 
		    case 'Polygon'                  : 
			case 'Rectangle'                : $package .= 'math/geom/'; break;
			// net
			case 'FTP'                      : $package .= 'net/'; break;
			// object
            case 'GMFBase'                  : $package .= 'object/'; break;
            // provider
            case 'IOptionsDataProvider'     : {
                $package  .= 'provider/'; 
                $interface = true;
            } break;
            // provider.mysql
            case 'MySQLDataProvider'        : 
            case 'OptionsMySQLDataProvider' : $package .= 'provider/mysql/'; break;
            // social
            case 'SocApi'                   :
            case 'SocApiService'            :
            case 'SocBilling'               :
            case 'SocParams'                :
            case 'SocPlatform'              :
            case 'SocRequest'               :
            case 'SocRequestResult'         : $package .= 'social/'; break;
            // social.mail            
            case 'MailBilling'              :
            case 'MailParams'               :
            case 'MailRequest'              : 
            case 'MailApi'                  : $package .= 'social/mail/'; break;
            // social.OK            
            case 'OKBilling'                :
            case 'OKParams'                 :
            case 'OKRequest'                : 
            case 'OKApi'                    : $package .= 'social/OK/'; break;
            // social.VKontakte                        
            case 'VKParams'                 :
            case 'VKRequest'                : 
            case 'VKApi'                    : $package .= 'social/VKontakte/'; break;
            // socket
            case 'Socket'                   : $package .= 'socket/'; break;
            // store
            case 'SingletonsStore'          : 
            case 'StoreAbstract'            : $package .= 'store/'; break;
            // stream
            case 'Cutdowner'                : 
            case 'Options'                  : 
            case 'StreamFieldsSet'          :
            case 'StreamList'               :
            case 'StreamObject'             : $package .= 'stream/'; break;
            case 'IStreamObject'            : {
                $package  .= 'stream/';
                $interface = true;
            } break;
			// string
            case 'BaseString'               : 
			case 'Validation'               : $package .= 'string/'; break;
            // string.lang
            case 'Lang'                     :
            case 'LangLocale'               : 
            case 'LangStore'                : $package .= 'string/lang/'; break;
			// utils
			case 'ImportClasses'            :	
			case 'Session'                  : 
            case 'TypeConverter'            : $package .= 'utils/'; break;
			// utils.log
			case 'Log'                      : 
            case 'Logger'                   : 
            case 'LoggerByDate'             : 
            case 'LoggerById'               :
            case 'LoggerBySize'             : 
            case 'LogMessage'               : $package .= 'utils/log/'; break;
			//case ''         : include_once( dirname( __FILE__ ) . '/.class.php' ); break;
			default : return false;
		}

		include_once( dirname( __FILE__ ) . $package . $className . '.' . 
                        ( $interface ? 'interface' : 'class') . '.php' );
	}
    
	/**
	 * Получить имя автолоад-функции
	 * @return string 
	 */
	protected static function getLoadFunc()
	{
		return __CLASS__ . '::loadClass';
	}
}
?>