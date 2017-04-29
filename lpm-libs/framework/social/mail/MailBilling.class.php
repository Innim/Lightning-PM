<?php 
namespace GMFramework;

/**
 * Класс биллинга для мой мир@mail.ru 
 * @package ru.vbinc.gm.framework.social.mail
 * @author Antonio & GreyMag
 * @copyright 2011
 * @version 0.1
 * 
 * @property-read int $serviceId Идентификатор услуги, который был передан при вызове функции API по приему платежа
 * @property-read int $smsPrice Номинал SMS-платежа, который был указан при вызове функции payments.showDialog
 * @property-read int $price Cтоимость услуги в копейках, которая была указана при вызове функции payments.showDialog
 */
class MailBilling extends SocBilling
{
    /**
     * 701 User not found — если приложение не смогло найти пользователя для оказания услуги
    */
    const ER_USER_NOT_FOUND = 701;
    /**
     * 702 Service not found — если услуга с данный идентификатором не существуем в вашем приложении
    */
    const ER_SERVICE_NOT_FOUND = 702;
    /**
     * 703 Incorrect price for given uid and service_id — если данная услуга для данного пользователя не могла быть оказана за указанную цену 
    */
    const ER_INCORRECT_PRICE = 703;
    /**
     * 700 Other error — другая ошибка
    */
    const ER_OTHER_ERROR = 700;
    
    /**
     * Идентификатор услуги, который был передан при вызове функции API по приему платежа
     * @var int
     */
    private $_serviceId;
    /**
     * Номинал SMS-платежа, который был указан при вызове функции payments.showDialog; 
     * возможные значения — 1, 3, 5; передается если пользователь оплатил услугу с помощью SMS
     * @var int
     */
    private $_smsPrice = 0;
    /**
     * Cтоимость услуги в копейках, которая была указана при вызове функции payments.showDialog; 
     * передается если пользователь оплатил услугу любым способом, кроме SMS
     * @var int
     */
    private $_price = 0;
    
    /**
     * 
     * app_id   int идентификатор вашего приложения
        transaction_id  int идентификатор денежной транзакции
        service_id  int идентификатор услуги, который был передан при вызове функции API по приему платежа
        uid string  идентификатор пользователя, который оплатил услугу
        sig string  подпись запроса, рассчитывается по аналогии с подписью запроса любого вызова API по защищенной схеме «сервер-сервер»
        sms_price   int номинал SMS-платежа, который был указан при вызове функции payments.showDialogjs; возможные значения — 1, 3, 5; передается если пользователь оплатил услугу с помощью SMS
        other_price int стоимость услуги в копейках, которая была указана при вызове функции payments.showDialogjs; передается если пользователь оплатил услугу любым способом, кроме SMS
        profit  int сумма в копейках, которую вы получите от Платформы (ваша прибыль)
        debug   bool    флаг, определяющий режим отладки; если debug=1, то приложение должно учитывать, что это тестовый вызов
     * 
    */
    
    /**
     *
     * @param $params Параметры, передаваемые принимающему скрипту
     */
    function __construct( $params )
    {
        parent::__construct( $params );

//        if($this->doTransaction($params['transaction_id'])){
//            $this->seterror(MailBilling::$ER_INCORRECT_PRICE);
//            return;
//        }  
        
        $this->_answer = array( 'status' => 1 );
    }
    
    function __get( $var )
    {       
        switch ($var)
        {
            case 'answer'    : return json_encode( $this->_answer ); break;            
            case 'serviceId' : return $this->_serviceId; break;                    
            case 'smsPrice'  : return $this->_smsPrice; break;                    
            case 'price'     : return $this->_price; break;
        }
        
        return parent::__get( $var );
    }       

    public function error( $errno, $status = 2 )
    {
        parent::error( $errno );
        $this->_answer = array( 'status' => $status, 'error_code' => $errno );        
    }    
    
    protected function parse( $params )
    {
        parent::parse( $params );
                
        if (isset( $params['other_price'] )) $this->_price    = $params['other_price'];
        if (isset( $params['sms_price'  ] )) $this->_smsPrice = $params['sms_price'];
        
        $this->_userId        = $params['uid'];
        $this->_serviceId     = $params['service_id'];
        $this->_transactionId = $params['transaction_id'];              
    }
    
    protected function checkAppId()
    {
        if ($this->_params['app_id'] != API_ID) {
            $this->error( MailBilling::ER_SERVICE_NOT_FOUND );
            return false;
        } return true;
    }
    
    protected function checkSig()
    {
        // GMF2DO переделать на статический метод в MailService
        $params = $this->_params;
        $sig = $params['sig'];
        unset( $params['sig'] );        
        ksort( $params );
        $strparams = '';
        
        foreach ($params as $k => $v) {
            $strparams .= $k."=".$v;
        }

        if ($sig != md5( $strparams . SECURE_CODE )) {
            $this->error( MailBilling::ER_SERVICE_NOT_FOUND );
            return false;
        }
        return true;
    }
    
}

?>