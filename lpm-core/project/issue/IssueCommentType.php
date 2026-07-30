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
     * Отмеченный баг ({@see REQUEST_CHANGES}), помеченный как решённый
     * без внесения правок.
     *
     * Такой комментарий больше не удерживает за задачей статус наличия бага,
     * но сохраняет своё описание для истории.
     */
    const REQUEST_CHANGES_RESOLVED = 'request_changes_resolved';

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
