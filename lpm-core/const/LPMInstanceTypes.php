<?php

/**
 * Возможные идентификаторы instance типов для таблицы members и подобных.
 * @created 17.11.2017
 * @version 1.0
 * @copyright (c) Innim LLC 2017
 * @author ChessMax (www.chessmax.ru)
 */
class LPMInstanceTypes {
    /**
     * Instance тип задачи.
     * @var int
     */
    const ISSUE = 1;

    /**
     * Instance тип проекта.
     * @var int
     */
    const PROJECT = 2;

    /**
     * Instance тип для пользователя задачи в снепшоте.
     * @var int
     */
    const SNAPSHOT_ISSUE_MEMBERS = 3;

    /**
     * Instance задача для теста.
     */
    const ISSUE_FOR_TEST = 4;

    /**
     * Instance тестар задачи в снепште.
     */
    const SNAPSHOT_ISSUE_FOR_TEST = 5;
}
?>