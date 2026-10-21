<?php
/**
 * Базовый класс для взаимодействия с api соц. сетей 
 * @package ru.vbinc.gm.framework.social
 * @author GreyMag
 * @copyright 2011
 * @version 0.2
 */
class SocApi
{
    /**
     * Ошибка при выполнении метода
     * @var string
     */
    public $error = '';
	/**
     * Объект, обесепечивающий выполнение запросов к api
     * @var SocRequest
     */
    protected $_invoker;
    /**
     * Параметры, передаваемые с соц. сети
     * @var SocParams
     */
    protected $_info;
    /**
     * Вызванный метод
     * @var String
     */ 
    /**
     * Ключ приложения, который используется для подписи запросов
     * @var string
     */
    protected $_secureCode;
    
    function __construct()
    {
        // для корректной работы класса,
        // должна быть определена константа
        // содержащая ключ приложения
        if (!defined( 'SECURE_CODE' )) {
            throw new Exception( 'Не установлен ключ приложения! Настройте файл конфигурации!' );
        }
        
        $this->_secureCode = SECURE_CODE;
    }
    
    /**
     * Отправляет уведомления пользователям
     * @param float || array $toUids id пользователя кому отправлять
     * или массив id'ов пользователей
     * @param string $message текст сообщения
     * @return boolean
     */
    public function sendNotification( $toUids, $message )
    {
        // требует переопределения в наследниках
        // там надо просто вызвать метод doSendNotification с нужными параметрами
        return false;
    }
    
    /**
     * @see SocRequest#request
     * @param $method
     * @param $parameters
     */
    public function request( $method, $parameters = array() )
    {
        return $this->_invoker->request( $method, $parameters );
    }

    /**
     * Осуществляет рассылку уведомления пользователям
     * @param float || array $toUids id пользователя кому отправлять
     * или массив id'ов пользователей
     * @param string $message текст сообщения
     * @param string $method имя методы, вызываемого у API
     * @param int $maxOneSend максимальное количество пользователей,
     * которым можно отправить оповещения за 1 раз
     * @param string $uidsField название поля, в котором будет пересланы идентификаторы
     * @param string $messField название поля, в котором будет переслан текст сообщения
     * @return boolean
     */
    protected function doSendNotification( $toUids, $message, $method, $maxOneSend, $uidsField, $messField )
    {
        $toUids = ( is_array( $toUids ) ) ? $toUids : array( (int)$toUids );

        $message = str_replace( '"', '&#34;', $message );
        $message = str_replace( "'", '&#39;', $message );
        //$message = $this->_db->escape_string( $message );

        $__arrayOfArrays = array_chunk( $toUids, $maxOneSend );
        
        $errorCount = 0;

        foreach ($__arrayOfArrays as $__uidsArray)
        {
            $__uids = implode( ',', $__uidsArray );

            if (!$request = $this->_invoker->request( $method, array( $uidsField => $__uids, $messField => $message ) ))
            {
                //$this->error( $this->_kontakt->error );
                // не надо запарывать все изи-за дурацких вконтактовских глюков
                $this->error = $this->_invoker->error;
                //return false;
                $errorCount++;
            }

            usleep( $this->getRequestDelay() ); // а то контакт ругаться будет
            // под виндой usleep не работает
            //sleep(1); // а sleep не может задержаться меньше чем на секунду
        }

        // считаем отправку успешной,
        // если отправилось больше половины оповещений
        return ( count( $toUids ) > $errorCount * 2 );
    }
    
    /**
     * Проверяет и сохраняет параметры, переданные на сервер
     * @param $flashvars
     * @return boolean
     */
    public function checkFlashvars( $flashvars )
    {
        if ($this->_info == null) {
            $this->error( 'Не создан объект информации' );
            return false;
        }

        $this->_info->parse( $flashvars );
        
        if (DefaultGlobals::isDebugMode()) {
            $this->_info->isAppUser = true;
            $this->_info->apiId = defined( 'API_ID' ) ? API_ID : $this->_info->apiId;
            $this->_info->authKey = $this->getAuthKey();
        }
        
        // если не установлено или ключ не совпадает - нафик
        if( !$this->_info->isAppUser ) {
            $this->error( 'Приложение не установлено' );
            return false;
        } elseif( defined( 'API_ID' ) && $this->_info->apiId != API_ID ) {
            $this->error( 'Неверное приложение' );
            return false;
        } elseif( $this->_info->authKey !== $this->getAuthKey() ) {
            $this->error( 'Ошибка безопасности' );
            return false;
        }

        SocGlobals::setFlashVars( $this->_info );

        return true;
    } 
    
    /**
     * Получает параметры, сохранённые в сессии
     * @return boolean
     */
    public function getFlashvars() {    	
    	if (!$info = SocGlobals::getFlashVars()) return false;
    	$this->_info = $info;
    	return $this->_info;
    }
    
    /**
     * Возвращает правильный ключ аторизации для текущей информации
     * @return String
     */
    protected function getAuthKey()
    {
        // требует переопределения в наследниках
        return '';
    }
    
    /**
     * Возвращает необходимую задержку запросами в миллисекундах
     */
    protected function getRequestDelay()
    {
        // требует переопределения в наследниках
        return 0;
    }
    
    protected function error( $error )
    {
    	$this->error = $error;
    	return false;
    }
}
?>