<?php
/**
 * Типы особых комментариев задачи.
 */
class IssueCommentType
{
    /**
     * Отметка о прохождении тестирования.
     */
    const PASS_TEST = 'pass_test';

    /**
     * Выявлены проблемы при тестировании - требуется
     * внести изменения.
     */
    const REQUEST_CHANGES = 'request_changes';
}
