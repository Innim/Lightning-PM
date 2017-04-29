<?php
namespace GMFramework;

/**
 * Базовый класс биллинга для соц. сетей
 * @package ru.vbinc.gm.framework.social
 * @author GreyMag & Antonio
 * @copyright 2011
 * @version 0.1
 * 
 * @property-read boolean $success Успешно прошла операция или нет 
 * @property-read mixed $answer Ответ
 * @property-read string $error Текст ошибки
 * @property-read array $params Параметры, передаваемые принимающему скрипту
 * @property-read string $userId ID пользователя
 */
class SocBilling
{
	/**
     * Успешно прошла операция или нет
     * @var boolean
     */
    protected $_success = false;
    /**
     * Текст ошибки
     * @var string
     */
    protected $_error   = '';
    /**
     * Параметры, передаваемые принимающему скрипту
     * @var array
     */
    protected $_params;
    /**
     * Идентификатор пользователя
     * @var string
     */
    protected $_userId;
    /**
     * Unique ID of transaction
     * @var string
     */
    protected $_transactionId;
    /**
     * Ответ
     * @var mixed
     */
    protected $_answer; 
    
    /**
     *
     * @param $params Параметры, передаваемые принимающему скрипту
     */
    function __construct( $params )
    {
        $this->parse( $params );
                 
        if (!$this->checkAppId()) {
            return;
        } 
        if ($this->doTransaction( $this->_transactionId )) {
            return;
        }  
        if (!$this->checkSig()) {
            return;
        }    
         
        $this->_success = true;    
    }
    
    function __get( $var )
    {       
        switch ($var)
        {
            case 'answer'      : return $this->_answer ; break;
            case 'success'     : return $this->_success; break;
            case 'error'       : return $this->_error  ; break;
            case 'params'      : return $this->_params ; break;
            case 'userId'      : return $this->_userId ; break;
        }
        
        return parent::__get( $var );
    }     
    
    /**
     * Обрабатывает ошибку
     * @param $errno номер ошибки
     */
    public function error( $errno )
    {                     
    	$this->_answer  = '';
    	$this->_success = false;
    } 
    
    /**
     * Парсинг параметров
     * @param array $params
     */
    protected function parse( $params )
    {
    	$this->_params = $params;
    }
    
    /**
     * Проверяет идентификатор приложения
     * @return boolean
     */
    protected function checkAppId()
    {
        // требует переопределения в наследниках
        return false;
    }
    
    /**
     * Проверяет сигнатуру
     * @return boolean
     */
    protected function checkSig()
    {
        // требует переопределения в наследниках
        return false;
    }
    
    /**
     * хз что это
     * @param $id
     * @return boolean
     */
    protected function doTransaction( $id )
    {
        return false;
    }
}