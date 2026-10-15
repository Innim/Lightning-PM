<?php
/**
 * Типы событий журнала задачи.
 *
 * Журнал общий на все события задачи, поэтому новое событие добавляется
 * значением этого перечисления, а не отдельной колонкой или таблицей.
 * @see IssueEvent
 */
class IssueEventType
{
    /**
     * Задачу взяли в тестирование.
     */
    const TAKEN_FOR_TESTING = 'taken_for_testing';

    /**
     * С задачи сняли отметку о взятии в тестирование.
     */
    const RELEASED_FROM_TESTING = 'released_from_testing';

    /**
     * Merge request по задаче влит.
     *
     * Записывается один раз, в тот момент, когда влитие пришло от GitLab:
     * именно им задача обычно и отправляется в тест, а в ленте этот момент
     * ничем не отмечается. В данных события - идентификатор merge request'а
     * на GitLab ({@see GitlabMergeRequest::$id}).
     */
    const MR_MERGED = 'mr_merged';
}
