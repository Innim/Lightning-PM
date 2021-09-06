<?php
class Link
{
    public static function getUrlByUid($uid, $_subUids = '')
    {
        $args = func_get_args();
        array_shift($args);
        
        return self::getUrl($uid, $args);
    }

    /**
     * Строит URL страницы сайта.
     * @param  string        $pageUid Строковый идентификатор страницы.
     * @param  array<string> $args    Массив дополнительных аргументов.
     * @param  string        $hash    Хэш параметр.
     * @return string URL страницы.
     */
    public static function getUrl($pageUid, $args, $hash = '')
    {
        $url = SITE_URL . $pageUid . '/' . implode('/', $args);
        if (!empty($hash)) {
            $url .= '#' . $hash;
        }
        
        return $url;
    }
    
    public $href;
    public $label;
    
    private $_reqRole;
    private $_isCurrent = false;
    
    public function __construct($label = '', $href = '', $reqRole = -1)
    {
        $this->href     = $href;
        $this->label    = $label;
        $this->_reqRole = $reqRole;
    }
    
    public function setCurrent($value = true)
    {
        $this->_isCurrent = $value;
    }
    
    public function isCurrent()
    {
        return $this->_isCurrent;
    }
    
    public function checkRole()
    {
        if ($this->_reqRole == -1) {
            return true;
        }
        if (!$user = LightningEngine::getInstance()->getUser()) {
            return false;
        }
        
        return $user->checkRole($this->_reqRole);
    }
}
