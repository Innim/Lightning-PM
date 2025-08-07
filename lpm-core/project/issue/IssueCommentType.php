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

    /**
     * Комментарий с MR.
     *
     * Так отмечается любой комментарий, который содержит MR
     * и не подходит ни в какой другой тип.
     */
    const MERGE_REQUEST = 'merge_request';

    /**
     * Комментарий о создании ветки.
     */
    const CREATE_BRANCH = 'create_branch';

    /**
     * Комментарий о том, что ветка влита.
     */
    const BRANCH_MERGED = 'branch_merged';
}
