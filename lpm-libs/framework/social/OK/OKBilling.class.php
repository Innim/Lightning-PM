<?php
namespace GMFramework;

/**
 * Биллинг на одноклассниках
 * @package ru.vbinc.gm.framework.social.OK
 * @author Antonio & GreyMag
 * @copyright 2011
 * @version 0.1
 * 
 * @property-read string $header Заголовок, который будет отправлен в ответ
 * @property-read string $productCode Код продукта
 * @property-read int $amount Общая сумма в виртуальной валюте портала (ОК)
 */
class OKBilling extends SocBilling
{   
    /**
     * 1 UNKNOWN Unknown error
     * @var int
     */
    const ER_UNKNOWN = 1;
    /**
     * 2 SERVICE — Service temporary unavailable
     * @var int
    */
    const ER_SERVICE = 2;
    /**
     * 1001 CALLBACK_INVALID_PAYMENT Payment is invalid and can not be processed 
     * @var int
    */
    const ER_CALLBACK_INVALID_PAYMENT = 1001;
    /**
     * 9999 SYSTEM — Critical system failure, which can not be recovered
     * @var int
    */
    const ER_SYSTEM = 9999;

    
    /**
     * Product code
     * @var string
     */
    private $_productCode;
    /**
     * Total amount in virtual currency of the portal
     * @var int
     */
    private $_amount;
    
    private $_errorHeader   = '';
    private $_defaultHeader = 'Content-Type: application/xml; charset=utf-8';
    
    public $_debug;
    
    
    /**
     * 
    uid	 Y	 String	 User ID
    transaction_time	 Y	 DateTime	 Time of transaction
    transaction_id	 Y	 String	 Unique ID of transaction
    product_code	 Y	 String	 Product code
    product_option	 N	 String	 Code of product option selected
    amount	 Y	 Integer	 Total amount in virtual currency of the portal
    currency N	 String	 The currency of payment (except for "ok" payments)
    payment_system N	 String	 The payment system in case of direct payments in RUR currency
    extra_attributes N	 String	 JSON encoded key/value pairs, containing additional transaction parameters, passed by application in ActionScript API - showPayment.
    
    
    [uid] => 8386534952720796902
    [amount] => 200
    [application_key] => CBAMMJABABABABABA
    [transaction_time] => 2011-04-14 12:33:03
    [product_code] => code-5
    [method] => callbacks.payment
    [call_id] => 1302773583510
    [transaction_id] => 576256_1302773583510
    [sig] => 40be3852f55f1b8db4bc2accbe69d5dc
    [product_option] => 222222 

     * 
    */
    
    /**
     *
     * @param $params Параметры, передаваемые принимающему скрипту
     */
    function __construct( $params )
    {        
        parent::__construct( $params ); 

        $this->_answer = '<?xml version="1.0" encoding="UTF-8"?><callbacks_payment_response xmlns="http://api.forticom.com/1.0/">true</callbacks_payment_response>';
    }
    
    function __get( $var )
    {       
        switch ($var)
        {
        	case 'header'      : return ( $this->_success ) ? $this->_defaultHeader : $this->_errorHeader; break;
        	case 'productCode' : return $this->_productCode; break;
            case 'amount'      : return $this->_amount; break;
        }
        
        return parent::__get( $var );
    }    

    public function error( $errno )
    {
        parent::error( $errno );
    	$this->_errorheader = $this->_defaultHeader . "\n" .
                              "invocation-error: " . $errno;        
        $this->_answer = "<ns2:error_response xmlns:ns2='http://api.forticom.com/1.0/'>" .
                          "<error_code>" . $errno . "</error_code>" .
                          "<error_msg>Error : " . $errno . "</error_msg>" .
                         "</ns2:error_response>";        
    }
    
    /**
     * Выводит ответ на экран
     */
    public function showAnswer()
    {
        header( $this->header );
        print( $this->_answer );
    } 
    
    protected function parse( $params )
    {
        parent::parse( $params );
        
        $this->_transactionId = $params['transaction_id'];
        $this->_productCode   = $params['product_code'];
        $this->_amount        = $params['amount'];
        $this->_userId        = $params['uid'];               
    }
    
    protected function checkAppId()
    {
        if ($this->_params['application_key'] != APPLICATION_KEY) {
        	$this->error( OKBilling::ER_SERVICE );
        	return false;
        } return true;
    }
    
    protected function checkSig()
    {
        // GMF2DO переделать на статический метод в OKService
    	
    	$sig = $this->_params['sig'];
        unset( $this->_params['sig'] );        
        ksort( $this->_params );
        $strparams = '';
        foreach ($this->_params as $k => $v) {
            $strparams .= $k."=".$v;
        }
        #$strparams = strtolower($strparams);        
        if ($sig != md5( $strparams . SECURE_CODE )) {
            $this->error( OKBilling::ER_SYSTEM );
        	return false;
        }
        return true;
    }    
}

?>