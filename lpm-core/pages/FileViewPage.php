<?php
/**
 * Страница просмотра приложенного HTML-файла.
 *
 * Содержимое файла показывается во фрейме и загружается отдельным адресом
 * ({@see FileDownloadController}), который отдаёт его изолированным от сессии.
 * Сама страница показывает, что открыт приложенный файл, а не страница
 * приложения: содержимое отчёта — произвольная разметка от того, кто его
 * приложил, и доверять ей нельзя.
 *
 * Запись о файле и файл на диске живут порознь, поэтому наличие содержимого
 * страница проверяет сама: без проверки фрейм показал бы отказ приложения,
 * неотличимый от содержимого отчёта.
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

    /**
     * Готовит страницу к показу.
     * @return $this|false `false`, если открывать страницу некому:
     *      пользователь не авторизован. Потерянное содержимое отказом не
     *      считается: страница открывается и сообщает, что файла нет.
     * @throws NotFoundException Если записи о файле нет - вместо страницы
     *      будет показан отказ ({@see NotAvailablePage}).
     * @throws ForbiddenException Если файл есть, а доступа к нему нет.
     */
    public function init()
    {
        if (!parent::init()) {
            return false;
        }

        $file = LPMFile::loadByUid($this->getParam(1));
        if (!$file || $file->deleted || !$file->isHtml()) {
            throw NotFoundException::withMessage(
                FileDownloadController::NOT_FOUND_MESSAGE,
                'File not found'
            );
        }

        $userId = $this->_engine->getUser()->getID();
        $issue = $file->loadViewableIssue($userId);
        if (!$issue) {
            // Задач и комментариев у файла может уже не быть - тогда он не
            // закрыт от пользователя, а просто ни к чему не приложен.
            if ($file->checkViewPermit($userId) === null) {
                throw NotFoundException::withMessage(
                    FileDownloadController::NOT_FOUND_MESSAGE,
                    'File has no linked items'
                );
            }

            throw ForbiddenException::withMessage(
                FileDownloadController::NO_ACCESS_MESSAGE,
                'File is not available for the user'
            );
        }

        $this->_title = $file->origName;

        $this->addTmplVar('file', $file);
        $this->addTmplVar('issue', $issue);
        // Проверяем после проверки доступа: тому, кому файл не положен,
        // о судьбе его содержимого знать незачем.
        $this->addTmplVar('fileExists', is_file($file->getAbsolutePath()));

        return $this;
    }
}
