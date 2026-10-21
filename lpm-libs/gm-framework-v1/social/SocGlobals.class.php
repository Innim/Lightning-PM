<?php
/**
 * @package ru.vbinc.gm.framework.social
 * @author GreyMag
 * @copyright 2010
 * @version 0.2
 */
class SocGlobals extends Globals
{
    /**
     * Имя переменной сессии
     * @var String
     */
    const SESSION_FV_NAME = 'socfv';
    /**
     * Платформа Вконтакте API
     * @var int
     */
    const PLATFORM_VKONTAKTE     = 1;
    /**
     * Платформа Мой мир@mail.ru
     * @var int
     */
    const PLATFORM_MAILRU        = 2;
    /**
     * Платформа Odnoklassniki.ru Third Party Platform
     * @var int
     */
    const PLATFORM_ODNOKLASSNIKI = 3;

    /**
     * Установить параметры с соц. сети
     * @param $flashVars
     */
    public static function setFlashVars( SocParams $flashVars )
    {
        //session_start();
        $session = Session::getInstance();

        // записываем в сессию
        //$_SESSION[VKGlobals::SESSION_FV_NAME . API_ID] = serialize( $flashVars );
        $session->set( self::SESSION_FV_NAME . API_ID, serialize( $flashVars ) );
    }

    /**
     * Получить параметры от соц. сети
     * @return SocParams
     */
    public static function getFlashVars()
    {
        //if( !session_start() ) throw new Exception( 'Session not started' );
        $session = Session::getInstance();
        // ----------------- для локального теста ---------------------------
        //$flashvars = new FlashVars( array( "api_id" => 1766188, "viewer_id" => 44027152, "viewer_type" => 0, "user_id" => 44027152, "group_id" => 0, "is_app_user" => 1, "auth_key" => "2f4987050aab9e81c76538af8e886a44", "api_url" => "http://api.vkontakte.ru/api.php" ) );
        //if( $_SERVER['REMOTE_ADDR'] == '192.168.6.241' )
        //echo
        /*
         if( $_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '192.168.6.241' || $_SERVER['REMOTE_ADDR'] == '192.168.6.254' )
         $flashvars = new FlashVars( array( "api_id" => 1809424, "viewer_id" => 1711999, "viewer_type" => 0, "user_id" => 1711999, "group_id" => 0, "is_app_user" => 1, "auth_key" => "0c44e6a4be0f5c1f7cda55adb2992e3e", "api_url" => "http://api.vkontakte.ru/api.php" ) );
         else //* /
         $flashvars = new FlashVars( array( "api_id" => 1809424, "viewer_id" => 44027152, "viewer_type" => 0, "user_id" => 44027152, "group_id" => 0, "is_app_user" => 1, "auth_key" => "1b24cb67f03b23f1342e5680503cacc3", "api_url" => "http://api.vkontakte.ru/api.php" ) );
         //$flashvars = new FlashVars( array( "api_id" => 1809424, "viewer_id" => 1711999, "viewer_type" => 0, "user_id" => 1711999, "group_id" => 0, "is_app_user" => 1, "auth_key" => "0c44e6a4be0f5c1f7cda55adb2992e3e", "api_url" => "http://api.vkontakte.ru/api.php" ) );
         // ----------------- для локального теста ---------------------------
         //*/
        //$flashvars = unserialize( $_SESSION[VKGlobals::SESSION_FV_NAME . API_ID] );
        $flashvars = unserialize( $session->get( self::SESSION_FV_NAME . API_ID ) );
        return $flashvars;
    }
}
?>