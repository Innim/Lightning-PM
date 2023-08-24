<?php
class PageConstructor
{
    public static function getSiteURL()
    {
        return SITE_URL;
    }
    
    public static function getUsingScripts()
    {
        return self::$_usingScripts;
    }

    public static function getUsingJSModules()
    {
        return self::$_usingJSModules;
    }
    
    public static function getMainMenu()
    {
        return LightningEngine::getInstance()->getPagesManager()->getLinks4Menu();
    }
    
    public static function getSubMenu()
    {
        return LightningEngine::getInstance()->getPagesManager()->getLinks4SubMenu();
    }
    
    public static function getUserMenu()
    {
        return LightningEngine::getInstance()->getPagesManager()->getLinks4UserMenu();
    }
    
    public static function getBasePageURL()
    {
        return LightningEngine::getInstance()->getCurrentPage()->getBaseUrl();
    }

    public static function getIssuesList()
    {
        return Issue::getCurrentList();
    }
    
    public static function getUsersList()
    {
        return User::loadList('');
    }
    
    public static function getUsersChooseList()
    {
        return User::loadList(' `locked` <> 1 ');
    }

    public static function getUserIssues()
    {
        return Issue::getListByMember(LightningEngine::getInstance()->getUserId());
    }
    
    public static function getProject()
    {
        return Project::$currentProject;
    }
    
    public static function getProjectMembers($onlyNotLocked = true)
    {
        return (Project::$currentProject != null)
                ? Project::$currentProject->getMembers($onlyNotLocked) : array();
    }

    public static function getIssueLabels()
    {
        $projectId = (Project::$currentProject != null) ? Project::$currentProject->id : 0;
        return Issue::getLabels($projectId);
    }

    public static function getErrors()
    {
        return LightningEngine::getInstance()->getErrors();
    }
    
    public static function canCreateProject()
    {
        if (!$user = LightningEngine::getInstance()->getUser()) {
            return false;
        }
        return $user->canCreateProject();
    }
    
    public static function isModerator()
    {
        if (!$user = LightningEngine::getInstance()->getUser()) {
            return false;
        }
        return $user->isModerator();
    }
    
    public static function getCurrentPage()
    {
        return LightningEngine::getInstance()->getCurrentPage();
    }

    public static function isAuth()
    {
        return LightningEngine::getInstance()->isAuth();
    }
        
    public static function getUser()
    {
        return LightningEngine::getInstance()->getUser();
    }

    public static function checkDeleteComment($authorId, $commentId)
    {
        return Project::checkDeleteComment($authorId, $commentId);
    }
    
    /*public static function includeCSS( $name ) {
        include self::$_instance->getThemeDir() . 'css/' . $name . '.css';
    }*/
    
    public static function includePattern($name, $args = null)
    {
        if (null !== $args) {
            extract($args);
        }
        include LightningEngine::getInstance()->getConstructor()->getThemePath() . $name . '.html';
    }

    public static function getHtml(callable $printHtml)
    {
        ob_start();
        $printHtml();
        return ob_get_clean();
    }

    private static $_usingScripts = [
//        'libs/bootstrap.bundle.min',
        'libs/jquery-1.12.4.min',
        'libs/jquery-ui-1.12.1.min',
        'libs/jquery.form',
        'libs/jquery.validate.min',
        'libs/F2PInvoker',
        'libs/iLoad',
        'libs/highlight.pack',
        'js-options.php$' ,
        'libs/clipboard.min',
        'libs/lodash.min',
        'libs/vue@2',
        'libs/vue-multiselect.min',
        'lightning'
    ];

    private static $_usingJSModules = [
        'filters/index'
    ];
    
    public $_title    = '';
    public $_header   = '';

    private $_openGraph = null;
    private $_themeDir  = '';
    
    /**
     * @var PagesManager
     */
    private $_pagesManager;

    // Параметр версии для сброса кэша
    private $_versionParam;
    
    public function __construct(PagesManager $pagesManager)
    {
        $this->_versionParam = mb_substr(md5(VERSION), 0, 7);
        if (Globals::isDebugMode()) {
            $this->_versionParam = uniqid();
        }
        $this->_themeDir = THEMES_DIR . LPMOptions::getInstance()->currentTheme . '/';
        
        $this->_pagesManager = $pagesManager;
    }


    public function createPage()
    {
        $page = LightningEngine::getInstance()->getCurrentPage();
        $this->_title  		= $page->getTitle();
        $this->_header	 	= $page->getHeader();
        $this->_openGraph   = $page->getOpenGraph();
        self::$_usingScripts = array_merge(self::$_usingScripts, $page->getJS());
        self::includePattern('page');
    }
    
    public function getTitle()
    {
        return $this->_title . ' :: ' . LPMOptions::getInstance()->title;
    }
    
    public function getHeader()
    {
        return $this->_header;
    }
    
    public function getCSSLink($file)
    {
        return $this->getThemeUrl() . 'css/' . $file . '.css?' . $this->_versionParam;
    }
    
    public function getJSLink($file)
    {
        if ($file !== '' && $file{mb_strlen($file)-1} === '$') {
            $file = mb_substr($file, 0, -1);
        } else {
            $file = $file . '.js?' . $this->_versionParam;
        }
        return SITE_URL . SCRIPTS_DIR . $file;
    }
    
    /**
     * Возвращает данные Open Graph.
     * @return array Ассоциативный массив данных.
     */
    public function getOpenGraph()
    {
        return $this->_openGraph;
    }
    
    public function getThemePath()
    {
        return ROOT . $this->_themeDir;
    }
    
    public function getThemeUrl()
    {
        return SITE_URL . $this->_themeDir;
    }
}
