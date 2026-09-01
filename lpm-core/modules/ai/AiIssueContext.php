<?php
/**
 * Данные задачи, одинаково описываемые во всех запросах к ИИ.
 *
 * Билдеры ({@see IssueSummaryBuilder}, {@see IssueTestChecklistBuilder})
 * передают модели одни и те же характеристики задачи, и расходиться в том,
 * как они названы, им нельзя. Общая часть собирается здесь, а всё, что зависит
 * от назначения артефакта, остаётся за билдером.
 *
 * Пример использования:
 * <code>
 * $lines[] = AiIssueContext::statusLine($issue, self::SUBSTATUS_NOTES);
 * </code>
 */
class AiIssueContext
{
    /**
     * Собирает строку запроса со статусом задачи.
     *
     * Статус называется так же, как его видит пользователь: если у задачи есть
     * уточнение статуса, в строку идёт оно, иначе основной статус.
     *
     * Результат зависит только от переданных данных: строка входит в текст
     * запроса, по которому считается sourceHash кэшируемых артефактов.
     *
     * @param Issue $issue Задача.
     * @param array $notes Пояснения к уточнению статуса по его коду
     * (@see IssueSubstatus); за формулировки отвечает вызывающий билдер.
     * Уточнение, которого нет в наборе, идёт без пояснения.
     * @return string Строка вида `Статус: Прошла тестирование (пояснение)`.
     */
    public static function statusLine(Issue $issue, array $notes = [])
    {
        $substatus = $issue->getSubstatus();
        $line = 'Статус: ' . IssueViewHelper::statusLabel($issue->status, $substatus);

        $note = isset($notes[$substatus]) ? $notes[$substatus] : '';

        return $note === '' ? $line : $line . ' (' . $note . ')';
    }
}
