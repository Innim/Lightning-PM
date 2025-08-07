<?php
class PagesManager
{
    private $_pages = array();
    /**
     * @var LightningEngine
     */
    private $_engine;
    /**
     *
     * @var BasePage
     */
    private $_defaultPage;
    
    private $_userMenu = array();
    
    public function __construct(LightningEngine $engine)
    {
        $this->_engine = $engine;
        
        if ($engine->isAuth()) {
            $this->_defaultPage = new ProjectsPage();
            array_push($this->_pages, new AuthPage());
        } else {
            $this->_defaultPage = new AuthPage();
            array_push($this->_pages, new ProjectsPage());
            array_push($this->_pages, new PassRecoveryPage());
        }
        
        array_push(
            $this->_pages,
            $this->_defaultPage,
            new ProjectPage(),
            new UsersPage(),
            new UserPage(),
            new ProfilePage(),
            new StatusPage()
        );
        
        $this->addLink2UserMenu('Выход', ProfilePage::SUID_EXIT);
    }
    
    /**
     *
     * @return array <code>array of Link</code>
     */
    public function getLinks4Menu()
    {
        $list = array();
        foreach ($this->_pages as /*@var $page BasePage */ $page) {
            if (!$page->notInMenu && (!$page->needAuth || $this->_engine->isAuth())
                    && $page->checkUserRole()) {
                array_push($list, $page->getLink());
            }
        }
        
        return $list;
    }
    
    /**
     *
     * @return array <code>array of Link</code>
     */
    public function getLinks4SubMenu()
    {
        $list = array();
        foreach ($this->_engine->getCurrentPage()->getSubMenu() as /*@var $link Link */ $link) {
            if ($link->checkRole()) {
                array_push($list, $link);
            }
        }
        
        return $list;
    }
    
    /**
     *
     * @return array <code>array of Link</code>
     */
    public function getLinks4UserMenu()
    {
        return $this->_userMenu;
    }
    
    /**
     *
     * @param string $pageUID
     * return BasePage|false
     */
    public function getPageByUid($pageUID)
    {
        foreach ($this->_pages as /*@var $page BasePage */ $page) {
            if ($page->uid == $pageUID) {
                return $page;
            }
        }
        return false;
    }
    
    /**
     *
     * return BasePage|false
     */
    public function getDefaultPage()
    {
        return $this->_defaultPage;
    }
    
    private function addLink2UserMenu($label, $suid)
    {
        array_push(
            $this->_userMenu,
            new Link($label, Link::getUrlByUid('profile', $suid))
        );
    }
}
