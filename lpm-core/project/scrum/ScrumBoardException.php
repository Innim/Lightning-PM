<?php
/**
 * Нарушение правил работы со скрам-доской: положение задачи на доске
 * нельзя изменить так, как просит вызывающий код.
 *
 * Это ошибка запроса, а не сбой: код статуса - 400.
 */
class ScrumBoardException extends LPMException
{
    protected $statusCode = 400;
    protected $localizedMessage = 'Некорректная операция со скрам-доской';
}
