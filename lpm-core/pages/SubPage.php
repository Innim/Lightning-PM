<?php
class SubPage extends LPMBaseObject
{
    public $uid;
    /**
     *
     * @var Link
     */
    public $link;
    public $title;
    public $pattern;
    public $js;

    public $showInMenu;
    
    public function __construct($uid, Link $link, $title, $pattern, $js = null, $showInMenu = true)
    {
        $this->uid     = $uid;
        $this->link    = $link;
        $this->title   = $title;
        $this->pattern = $pattern;
        $this->js      = ($js == null) ? array() : $js;
        $this->showInMenu = $showInMenu;
    }
}
