<?php
/**
 * Страница отказа: показывается вместо запрошенной, когда открыть её нельзя.
 *
 * Отдаётся по тому же адресу, по которому шёл пользователь, поэтому страницу
 * можно перезагрузить и переслать ссылкой, а «назад» ведёт туда, откуда он
 * пришёл. Причина отказа задаёт и код ответа, и то, что увидит пользователь:
 * 404 говорит, что такой записи нет, 403 - что она есть, но недоступна ему.
 */
class NotAvailablePage extends LPMPage
{
    const UID = 'not-available';

    /**
     * Сообщение, когда причина отказа неизвестна.
     */
    const DEFAULT_MESSAGE = 'Такой страницы нет';

    /**
     * Что делать, когда записи нет.
     */
    const NOT_FOUND_HINT = 'Проверьте ссылку: возможно, в адресе опечатка.';

    /**
     * Что делать, когда доступа к записи нет.
     */
    const NO_ACCESS_HINT = 'Попросить доступ можно у того, кто ведёт проект.';

    /**
     * @param LPMException|null $reason Причина отказа: её сообщение увидит
     *      пользователь, от её типа зависит подсказка. Без причины
     *      показывается общее сообщение о ненайденной странице.
     */
    public function __construct(?LPMException $reason = null)
    {
        parent::__construct(self::UID, 'Страница недоступна', false, true, 'not-available');

        $message = $reason === null ? '' : $reason->getLocalizedMessage();

        $this->addTmplVar('message', $message === '' ? self::DEFAULT_MESSAGE : $message);
        $this->addTmplVar('hint', $reason instanceof ForbiddenException
            ? self::NO_ACCESS_HINT
            : self::NOT_FOUND_HINT);
    }
}
