<?php
/**
* Настройки
* @author GreyMag
*
*/
class LPMOptions extends Options
{	
    private static $_instance;
	/**
	 * @return LPMOptions
	 */
	public static function getInstance()
	{
        if (self::$_instance == null) new LPMOptions();
        return self::$_instance;
		//return Options::getInstance( __CLASS__ );
	}
	
    /**
     * Время хранения куков для авторизации
     * в секундах (в базе хранятся в днях)
     * @var int
     */
    public $cookieExpire = 0;
    
    /**
     * Текущая тема оформления
     * @var default
     */
    public $currentTheme = 'default';
    /**
     * Название (заголовок) сайта
     * @var string
     */
    public $title = '';
    /**
     * Подзаголовок сайта
     * @var string
     */
    public $subtitle = '';
    /**
     * url логотипа сайта
     * @var string
     */
    public $logo = '';
    /**
     * Email, от имени которого будут отправляться письма
     * 
     * @var string
     */
    public $fromEmail = '';
    /**
     * Имя отправителя 
     * (по умолчанию берется заголовок сайта)
     * 
     * @var string
     */
    public $fromName = '';
    /**
     * Подпись для писем
     * 
     * @var string
     */
    public $emailSubscript = '';
    
    function __construct()
	{
        self::$_instance = $this;
		parent::__construct(); 
	}

    protected function initialization() {
        parent::initialization();
        
        $this->_typeConverter->addIntVars( 'cookieExpire' );
    }

	protected function initOptions()
	{
		parent::initOptions();
		
		$this->cookieExpire *= 86400; 
 
		if ($this->fromName == '') $this->fromName = $this->title;
		
		if ($this->logo != '' && substr( $this->logo, 0, 7 ) != 'http://') 
			$this->logo = SITE_URL . FILES_DIR . $this->logo;
	}

    protected function getTableName() {
        return LPMTables::OPTIONS;
    }
}
?>