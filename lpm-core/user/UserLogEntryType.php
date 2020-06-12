<?php
/**
 * Тип действия в логе.
 */
class UserLogEntryType extends \GMFramework\Enum
{
    /**
     * Добавление задачи.
     */
    const ADD_ISSUE = 0;
    /**
     * Изменение задачи.
     */
    const EDIT_ISSUE = 1;
    /**
     * Удаление задачи.
     */
    const DELETE_ISSUE = 2;
    /**
     * Добавление комментария.
     */
    const ADD_COMMENT = 3;
    /**
     * Изменение комментария.
     */
    const EDIT_COMMENT = 4; // TODO: если будет такой функционал
    /**
     * Удаление комментария.
     */
    const DELETE_COMMENT = 5;
    // TODO: добавить лог, перечисленный ниже
    /**
     * Добавление проекта.
     */
    // const ADD_PROJECT = 6;
    /**
     * Изменение проекта.
     */
    // const EDIT_PROJECT = 7;
    /**
     * Удаление проекта.
     */
    // const DELETE_PROJECT = 8;
    /**
     * Изменение пользователя.
     */
    // const EDIT_USER = 9;
    /**
     * Удаление пользователя.
     */
    // const DELETE_USER = 10;
    /**
     * Добавление стикера на доске.
     */
    // const ADD_SCRUM_STICKER = 11;
    /**
     * Изменение стикера на доске.
     */
    // const EDIT_SCRUM_STICKER = 12;
    /**
     * Удаление стикера на доске.
     */
    // const DELETE_SCRUM_STICKER = 13;
}
