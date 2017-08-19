<?php
namespace GMFramework;

/**
 * Переменные, передаваемые для приложения с Одноклассников
 * @package ru.vbinc.gm.framework.social.OK
 * @author GreyMag & Antonio
 * @version 0.1
 *
 */
class OKParams extends SocParams
{
    //var $window_id;
    //var $sig;
   // var $authorized; 
    /**
     * ID of the user+application session 
     * @var string
     */
    var $applicationKey;
    /**
     * secret key issued to the application, which must be used to sign all session related requests.
     * @var string
     */
    var $sessionSecretKey;
    /**
     * connection name, passed to ActionScript/JavaScript API
     * @var string
     */
    var $apiconnection;
    /**
     * An MD5 hash of the user+session_key+application_secret_key. It can be used for simplified verification of the logged in user
     * @var unknown_type
     */
    var $authSig;

	public function parse( $params )
	{		
        parent::parse( $params );
        @ksort( $this->_params ); 
        
        $this->apiId            = defined( 'API_ID' ) ? API_ID : 0;                				
        $this->isAppUser        = $this->getBooleanVar( 'authorized'         );
        $this->sessionSecretKey = $this->getStringVar ( 'session_secret_key' );
        $this->apiconnection    = $this->getStringVar ( 'apiconnection'      );
		$this->applicationKey   = $this->getStringVar ( 'application_key'    );        
        $this->authKey          = $this->getStringVar ( 'sig'                );
        $this->authSig          = $this->getStringVar ( 'auth_sig'           );
        
        $this->vid              = $this->getStringVar( 'logged_user_id'      );
        $this->viewerId         = ( isset( $this->_params['viewer_id'] ) ) 
                                     ? $this->getFloatVar( 'viewer_id' ) 
                                     : $this->vid;                
        
	}
	
//	function __get()
//	{
//		
//	}
}
?>