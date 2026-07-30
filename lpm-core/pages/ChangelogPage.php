<?php
/**
 * Страница со списком изменений приложения.
 *
 * Содержимое формируется автоматически из CHANGELOG.md.
 */
class ChangelogPage extends LPMPage
{
    const UID = 'changelog';

    /**
     * Возвращает URL страницы изменений.
     *
     * @return string
     */
    public static function getPageUrl()
    {
        return Link::getUrlByUid(self::UID);
    }

    public function __construct()
    {
        parent::__construct(self::UID, 'Что нового', true, true, 'changelog');
    }

    public function init()
    {
        if (!parent::init()) {
            return false;
        }

        $this->addTmplVar('entries', ChangelogParser::parse());

        return $this;
    }
}
