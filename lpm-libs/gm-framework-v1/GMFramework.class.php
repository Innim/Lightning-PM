<?
/**
 * Класс подключения фреймворка.<br/>
 * Для работы фреймворка необходим PHP >= 5.4
 * @package ru.vbinc.gm.framework
 * @author GreyMag <greymag@gmail.com>
 * @version 1.1.1
 * 
 */
class GMFramework 
{
    private static $_inited = false;
 
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
	   if ( !spl_autoload_register( $func ) ) {
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

		switch( $className )
		{
			// amfphp
			case 'AuthInfo'       :
			case 'SecureService'  :
			case 'Service'        : $package .= "amfphp/"; break;
			// array
			case 'ArrayUtils'     :
			case 'ArrayList'      : $package .= "array/"; break;
			// datetime
			case 'DateTimeUtils'  :
			case 'DateTimeFormat' :
		    case 'GMDate'         : $package .= "datetime/"; break;
			// db
			case 'DBConnect'      :
			case 'DBException'    : 
			case 'MySqlDump'      : $package .= "db/"; break;
            // filesystem
            case 'FileSystemUtils': $package .= "filesystem/"; break;
			// images
			case 'ImageResize'    : $package .= "images/"; break;
			// mail
			case 'MailMessage'    :  
			case 'MailSender'     : $package .= "mail/"; break;
			// math 
			// math.geom
			case 'Point'          : 
		    case 'Polygon'        : 
			case 'Rectangle'      : $package .= "math/geom/"; break;
			// net
			case 'FTP'            : $package .= "net/"; break;
			// object
			case 'Cutdowner'      : 
            case 'GMFBase'        :
			case 'StreamObject'   : $package .= "object/"; break;
            // social
            case 'SocApi'         :
            case 'SocApiService'  :
            case 'SocBilling'     :
            case 'SocGlobals'     :
            case 'SocParams'      :
            case 'SocRequest'     : $package .= "social/"; break;
            // social.mail            
            case 'MailBilling'    :
            case 'MailParams'     :
            case 'MailRequest'    : 
            case 'MailApi'        : $package .= "social/mail/"; break;
            // social.OK            
            case 'OKBilling'      :
            case 'OKParams'       :
            case 'OKRequest'      : 
            case 'OKApi'          : $package .= "social/OK/"; break;
            // social.VKontakte                        
            case 'VKParams'       :
            case 'VKRequest'      : 
            case 'VKApi'          : $package .= "social/VKontakte/"; break;
			// string
			case 'BaseString'     :
			case 'Validation'     : $package .= "string/"; break;
            // string.lang
            case 'Lang'           :
            case 'LangLocale'     : 
            case 'LangStore'      : $package .= "string/lang/"; break;
			// utils
            case 'DefaultGlobals' :
			case 'Globals'        :
			case 'ImportClasses'  :	
			case 'Options'        : 
			case 'Session'        : 
            case 'TypeConverter'  : $package .= "utils/"; break;
			// utils.log
			case 'GMLog'          : 
            case 'GMLogMessage'   : $package .= "utils/log/"; break;
			//case ''         : include_once( dirname( __FILE__ ) . '/.class.php' ); break;
			default : return false;
		}

		include_once( dirname( __FILE__ ) . $package . $className . '.class.php' );
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