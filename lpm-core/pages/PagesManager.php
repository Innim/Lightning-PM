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
     * @var LPMPage
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
            new StatusPage(),
            new SettingsPage(),
            new ChangelogPage()
        );
        
        $this->addLink2UserMenu('Выход', ProfilePage::SUID_EXIT, true);
    }
    
    /**
     *
     * @return array <code>array of Link</code>
     */
    public function getLinks4Menu()
    {
        $list = array();
        $currentPage = $this->_engine->getCurrentPage();
        $activeUid = $currentPage ? $currentPage->getMenuSectionUid() : null;
        foreach ($this->_pages as /*@var $page LPMPage */ $page) {
            if (!$page->notInMenu && (!$page->needAuth || $this->_engine->isAuth())
                    && $page->checkUserRole()) {
                $link = $page->getLink();
                $link->setCurrent($page->uid === $activeUid);
                array_push($list, $link);
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
     * return LPMPage|false
     */
    public function getPageByUid($pageUID)
    {
        foreach ($this->_pages as /*@var $page LPMPage */ $page) {
            if ($page->uid == $pageUID) {
                return $page;
            }
        }
        return false;
    }
    
    /**
     *
     * return LPMPage|false
     */
    public function getDefaultPage()
    {
        return $this->_defaultPage;
    }
    
    /**
     * @param string $label    Подпись пункта.
     * @param string $suid     Идентификатор подстраницы профиля.
     * @param bool   $isAction Пункт меняет состояние - отправляется формой.
     */
    private function addLink2UserMenu($label, $suid, $isAction = false)
    {
        $link = new Link($label, Link::getUrlByUid('profile', $suid));
        $link->isAction = $isAction;

        array_push($this->_userMenu, $link);
    }
}
