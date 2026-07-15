<?php
/**
* Настройки
* @author GreyMag
*/
class LPMOptions extends Options
{
    private static $_instance;
    /**
     * @return LPMOptions
     */
    public static function getInstance()
    {
        if (self::$_instance == null) {
            new LPMOptions();
        }

        return self::$_instance;
        //return Options::getInstance( __CLASS__ );
    }

    /**
     * Сохраняет переданные настройки.
     *
     * @param array $values ассоциативный массив `имя_опции => значение`
     * @throws Exception если передано несуществующее имя опции
     * @throws \GMFramework\ProviderSaveException при ошибке записи в БД
     */
    public static function save(array $values)
    {
        if (empty($values)) {
            return;
        }

        $options = self::getInstance();

        foreach ($values as $name => $value) {
            if (!property_exists($options, $name)) {
                throw new Exception('Неизвестная опция: ' . $name);
            }
        }

        $db = LPMGlobals::getInstance()->getDBConnect();
        $builder = new \GMFramework\DBQueryBuilder($db, $db->prefix);
        $table = $options->getTableName();

        foreach ($values as $name => $value) {
            $dbValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;

            $sql = $builder->buildQuery([
                'INSERT' => ['option' => $name, 'value' => $dbValue],
                'INTO'   => $table,
                'ODKU'   => ['value'],
            ]);

            if (!$db->query($sql)) {
                throw new \GMFramework\ProviderSaveException();
            }

            $options->$name = $value;
        }
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

    /**
     * Разрешена ли регистрация новых пользователей.
     * Если false — форма регистрации скрыта, а попытки регистрации отклоняются.
     * @var bool
     */
    public $allowRegistration = true;

    public function __construct()
    {
        self::$_instance = $this;
        parent::__construct();
    }

    protected function initialization()
    {
        parent::initialization();

        $this->_typeConverter->addIntVars('cookieExpire');
        $this->_typeConverter->addBoolVars('allowRegistration');
    }

    protected function initOptions()
    {
        parent::initOptions();
        
        $this->cookieExpire *= 86400;

        if ($this->fromName == '') {
            $this->fromName = $this->title;
        }
        
        if ($this->logo != '' && substr($this->logo, 0, 7) != 'http://') {
            $this->logo = SITE_URL . FILES_DIR . $this->logo;
        }
    }

    protected function getTableName()
    {
        return LPMTables::OPTIONS;
    }
}
