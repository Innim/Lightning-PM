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
     * Скелет описания задачи, которым заполняется пустое описание в форме.
     *
     * Незаполненная настройка означает скелет по умолчанию, поэтому
     * возвращается всегда непустое значение.
     *
     * @return string
     */
    public static function getIssueDescTemplate()
    {
        $value = (string)self::getInstance()->issueDescTemplate;

        return trim($value) === '' ? self::DEFAULT_ISSUE_DESC_TEMPLATE : $value;
    }

    /**
     * Правила оформления задачи, принятые в команде.
     *
     * Незаполненная настройка означает правила по умолчанию, поэтому
     * возвращается всегда непустое значение.
     *
     * @return string
     */
    public static function getIssueGuidelines()
    {
        $value = (string)self::getInstance()->issueGuidelines;

        return trim($value) === '' ? self::DEFAULT_ISSUE_GUIDELINES : $value;
    }

    /**
     * Значение по умолчанию для настройки оформления задач.
     *
     * @param string $name имя опции: `issueDescTemplate` или `issueGuidelines`
     * @return string умолчание; пустая строка, если у опции его нет
     */
    public static function getIssueTextDefault($name)
    {
        switch ($name) {
            case 'issueDescTemplate':
                return self::DEFAULT_ISSUE_DESC_TEMPLATE;
            case 'issueGuidelines':
                return self::DEFAULT_ISSUE_GUIDELINES;
            default:
                return '';
        }
    }

    /**
     * Совпадает ли текст с умолчанием для настройки оформления задач.
     *
     * Совпадающий с умолчанием текст сохранять не нужно: пустая настройка
     * означает умолчание, и оно продолжит обновляться вместе с приложением.
     * Различия в переводах строк и в отступах по краям несущественны.
     *
     * @param string $name имя опции: `issueDescTemplate` или `issueGuidelines`
     * @param string $text проверяемый текст
     * @return bool
     */
    public static function isIssueTextDefault($name, $text)
    {
        $text = trim(str_replace("\r\n", "\n", (string)$text));

        return $text === trim(self::getIssueTextDefault($name));
    }

    /**
     * Скелет описания задачи по умолчанию.
     * @see self::getIssueDescTemplate()
     */
    const DEFAULT_ISSUE_DESC_TEMPLATE = "### Проблема\n\n\n\n### Что сделать\n\n";

    /**
     * Правила оформления задачи по умолчанию.
     * @see self::getIssueGuidelines()
     */
    const DEFAULT_ISSUE_GUIDELINES = <<<TEXT
- название — одна короткая строка по сути задачи, без точки в конце,
  без префиксов вроде «Баг:» и без номера задачи;
- тип выбирай так: bug — что-то работает не так, как должно;
  support — вопрос, консультация, разовое действие силами команды;
  develop — всё остальное, то есть новая функциональность и доработки;
- описание оформляй в разметке Markdown разделами «### Проблема» и
  «### Что сделать»: в первом — суть и последствия, во втором — что
  требуется от команды;
- шаги воспроизведения приводи разделом «### Шаги воспроизведения» только
  для ошибки и только если из исходных данных понятно, как её повторить;
  каждый шаг — одно действие;
- не выдумывай того, чего нет в исходных данных: функциональность, названия
  экранов и кнопок, версии, окружение, шаги воспроизведения; неполное
  описание лучше выдуманного.
TEXT;

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

    /**
     * Показывать ли обновлённый вид страницы задачи.
     * Экспериментальная настройка: пока выключена, страница отдаётся в прежнем виде.
     * @var bool
     */
    public $newIssueView = false;

    /**
     * Скелет описания задачи: разделы, которые кнопка шаблона в форме задачи
     * подставляет в описание.
     *
     * Пустое значение означает скелет по умолчанию — читать настройку следует
     * через {@see self::getIssueDescTemplate()}.
     * @var string
     */
    public $issueDescTemplate = '';

    /**
     * Правила оформления задачи, принятые в команде: им следуют и ИИ,
     * составляющий черновик, и внешние клиенты API.
     *
     * Пустое значение означает правила по умолчанию — читать настройку следует
     * через {@see self::getIssueGuidelines()}.
     * @var string
     */
    public $issueGuidelines = '';

    public function __construct()
    {
        self::$_instance = $this;
        parent::__construct();
    }

    protected function initialization()
    {
        parent::initialization();

        $this->_typeConverter->addIntVars('cookieExpire');
        $this->_typeConverter->addBoolVars('allowRegistration', 'newIssueView');
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
