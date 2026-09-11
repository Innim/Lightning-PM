<?php
/**
 * Страница просмотра приложенного HTML-файла.
 *
 * Содержимое файла показывается во фрейме и загружается отдельным адресом
 * ({@see FileDownloadController}), который отдаёт его изолированным от сессии.
 * Сама страница показывает, что открыт приложенный файл, а не страница
 * приложения: содержимое отчёта — произвольная разметка от того, кто его
 * приложил, и доверять ей нельзя.
 */
class FileViewPage extends LPMPage
{
    /**
     * Возвращает URL страницы просмотра файла.
     * @param  string $uid Уникальный идентификатор файла.
     * @return string
     */
    public static function getUrlFor($uid)
    {
        return Link::getUrlByUid(self::UID, $uid);
    }

    const UID = 'file-view';

    public function __construct()
    {
        parent::__construct(self::UID, 'Просмотр файла', true, true, 'file-view');

        $this->_baseParamsCount = 2;
    }

    public function init()
    {
        if (!parent::init()) {
            return false;
        }

        $file = LPMFile::loadByUid($this->getParam(1));
        if (!$file || $file->deleted || !$file->isHtml()) {
            return $this->notFound();
        }

        $issue = $file->loadViewableIssue($this->_engine->getUser()->getID());
        if (!$issue) {
            return $this->notFound();
        }

        $this->_title = $file->origName;

        $this->addTmplVar('file', $file);
        $this->addTmplVar('issue', $issue);

        return $this;
    }

    /**
     * Про недоступный файл не сообщаем, есть он или нет: это раскрывало бы
     * наличие файла тому, у кого нет доступа к задаче.
     * @return false
     */
    private function notFound()
    {
        $this->_engine->addNextError('Файл не найден');

        return false;
    }
}
