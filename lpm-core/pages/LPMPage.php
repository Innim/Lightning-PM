<?php
/**
 * Базовый класс страницы.
 *
 * При создании новой надо обязательно добавлять её в PagesManager,
 * чтобы она начала работать.
 */
abstract class LPMPage
{
    /**
     * Уникальный идентификатор страницы.
     */
    public $uid;

    /**
     * Если true, то страница требует авторизации.
     */
    public $needAuth  = true;

    /**
     * Если true, то страница не отображается в меню.
     */
    public $notInMenu = false;

    protected $_title;
    protected $_header   = '';
    protected $_label    = '';
    
    protected $_subPages = [];

    protected $_pattern = '';
    protected $_js = [];
    
    /**
     * Количество параметров, которое является базовым
     *
     * @var int
     */
    protected $_baseParamsCount = 1;
    
    protected $_reqRole;
    
    protected $_error = '';
    
    protected $_defaultPUID  = '';

    /**
     *
     * @var SubPage
     */
    protected $_curSubpage;
    
    /**
     *
     * @var LightningEngine
     */
    protected $_engine;

    /**
     * Ассоциативный массив данных Open Graph.
     * @var array
     */
    protected $_openGraph;
    
    private $_isCurrent = false;

    private $_tmplVars;

    public function __construct($uid, $title, $needAuth = true, $notInMenu = false, $pattern ='', $label = '', $reqRole = -1)
    {
        $this->uid       = $uid;
        $this->_title    = $title;
        $this->_label    = empty($label) ? $title : $label;
        $this->_pattern  = $pattern;
        $this->needAuth  = $needAuth;
        $this->notInMenu = $notInMenu;
        $this->_reqRole  = ($reqRole == -1) ? User::ROLE_USER : $reqRole;
        
        $this->_engine   = LightningEngine::getInstance();
    }
    
    /**
     * Инициализирует страницу для показа
     */
    public function init()
    {
        $this->setCurrent();
        if ($this->check()) {
            return $this;
        } else {
            return false;
        }
    }

    /**
     * Проверяет, может ли пользователь просматривать эту страницу
     */
    public function check()
    {
        return (!$this->needAuth ||
                LightningEngine::getInstance()->isAuth() && $this->checkUserRole())
               && (!$this->_curSubpage || $this->_curSubpage->link->checkRole());
    }
    
    public function getSubMenu()
    {
        $subMenu = [];
        foreach ($this->_subPages as $subpage) {
            if ($subpage->showInMenu) {
                $subMenu[] = $subpage->link;
            }
        }
        return $subMenu;
    }

    public function isCurrentPage()
    {
        return $this->_isCurrent;
    }
    
    public function printContent()
    {
        if ($this->_pattern != '') {
            PageConstructor::includePattern($this->_pattern, $this->_tmplVars);
        }
    }
    
    public function getTitle()
    {
        return $this->_title;
    }
    
    public function getHeader()
    {
        return $this->_header == '' ? $this->_title : $this->_header;
    }
    
    public function getLabel()
    {
        return $this->_label == '' ? $this->_title : $this->_label;
    }

    /**
     * Возвращает данные Open Graph.
     * Эти же данные будут использоваться при редиректе с этой страницы
     * на страницу авторизации. Учитываем что в таком случае данные берутся даже когда
     * инициализация не прошла.
     * @return array Ассоциативный массив данных.
     */
    public function getOpenGraph()
    {
        return $this->_openGraph;
    }
    
    /**
     * @return string
     */
    public function getContent()
    {
        ob_start();
        $this->printContent();
        return ob_get_clean();
    }
    
    /**
     * Возвращает массив js скриптов для выбранной страницы
     */
    public function getJS()
    {
        return $this->_js;
    }

    /**
     * @return Link
     */
    public function getLink()
    {
        return new Link($this->getLabel(), $this->getBaseUrl());
    }
    
    /**
     * url текущей страницы, вместе с подстраницей
     *
     * @param string $_args
     */
    public function getUrl($_args = '')
    {
        $arr = func_get_args();
        if ($this->_curSubpage) {
            array_unshift($arr, $this->_curSubpage->uid);
        }
        
        return call_user_func_array([$this, 'getBaseUrl'], $arr);
    }

    public function getBaseUrl($_args = '')
    {
        $arr = func_get_args();
        
        $args = [$this->uid];
        for ($i = 1; $i < $this->_baseParamsCount; $i++) {
            $args[] = LightningEngine::getInstance()->getParams()->getArg($i);
        }
        
        $args = array_merge($args, $arr);
        
        return call_user_func_array(['Link', 'getUrlByUid'], $args);
    }
    
    public function checkUserRole()
    {
        if (!$this->needAuth) {
            return true;
        }
        if (!$user = LightningEngine::getInstance()->getUser()) {
            return false;
        }
        
        return $user->checkRole($this->_reqRole);
    }

    protected function setCurrent()
    {
        $this->_isCurrent = true;
        
        if ($this->_defaultPUID != '') {
            $this->initSubPage();
        }
    }
    
    protected function getPUID()
    {
        return $this->getParam($this->_baseParamsCount);
    }
    
    protected function getPageArg()
    {
        $index = $this->getParamIndex('page');
        if ($index === -1) {
            return 1;
        } else {
            $page = $this->getParam($index + 1);
            return empty($page) ? 1 : (int)$page;
        }
    }
    
    protected function getParam($num)
    {
        return $this->_engine->getParams()->getArg($num);
    }

    protected function getParamIndex($val)
    {
        return $this->_engine->getParams()->getArgIndex($val);
    }
    
    /**
     * Возвращает дополнительные параметры:
     * счет начинается с ($this->_baseParamsCount + 1)-го параметра
     * @param int $num
     * @return mixed
     */
    protected function getAddParam($num = 0)
    {
        return $this->getParam($this->_baseParamsCount + 1 + $num);
    }

    protected function initSubPage()
    {
        $curPUID = $this->getPUID();
        if (empty($curPUID)) {
            $curPUID = $this->_defaultPUID;
        }
            
        foreach ($this->_subPages as $subpage) {
            if ($subpage->uid == $curPUID) {
                $this->_curSubpage = $subpage;
                if ($subpage->pattern != '') {
                    $this->_pattern = $subpage->pattern;
                }
                $this->_title = $subpage->title;
                $this->_js = array_merge($this->_js, $subpage->js);
                break;
            }
        }
    }
    
    protected function error($value = '')
    {
        $this->_error = $value;
        return false;
    }
    
    protected function addSubPage($uid, $label, $pattern = '', $js = null, $title = '', $reqRole = -1, $showInMenu = true)
    {
        $args = [$this->uid];
        for ($i = 1; $i < $this->_baseParamsCount; $i++) {
            $args[] = LightningEngine::getInstance()->getParams()->getArg($i);
        }
        $args[] = $uid;
        
        $link = new Link(
            $label,
            call_user_func_array(['Link', 'getUrlByUid'], $args),
                    //Link::getUrlByUid( $this->uid, $uid ),
                    $reqRole
        );
        
        if ($title == '') {
            $title = $label;
        }

        $subpage = new SubPage($uid, $link, $title, $pattern, $js, $showInMenu);
        $this->_subPages[] = $subpage;

        return $subpage;
    }

    protected function getSubPage($uid)
    {
        foreach ($this->_subPages as $subpage) {
            if ($subpage->uid == $uid) {
                return $subpage;
            }
        }
        return null;
    }

    /**
     * Добавляет переменную, которая будет доступна в шаблоне
     * @param string $name
     * @param mixed  $value [description]
     */
    protected function addTmplVar($name, $value)
    {
        if (null === $this->_tmplVars) {
            $this->_tmplVars = [];
        }
        $this->_tmplVars[$name] = $value;
    }

    protected function addJS($_)
    {
        $arr = func_get_args();
        if (!empty($arr)) {
            $this->_js = array_merge($this->_js, $arr);
        }
    }

    protected function setOpenGraph($title, $url = null, $image = null, $type = "website")
    {
        if ($url == null) {
            $url = LightningEngine::getURL(LightningEngine::getInstance()->getCurrentUrlPath());
        }
        if ($image == null) {
            $image = LPMOptions::getInstance()->logo;
        }
        $og = compact('title', 'type', 'url', 'image');
        $og = array_filter($og);
        $this->_openGraph = $og;
    }
}
