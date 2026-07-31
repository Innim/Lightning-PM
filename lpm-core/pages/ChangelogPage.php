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

        $entries = ChangelogParser::parse();
        foreach ($entries as &$entry) {
            $entry['url'] = Link::getUrl(self::UID, [], $entry['slug']);
        }
        unset($entry);

        $this->addTmplVar('entries', $entries);

        return $this;
    }
}
