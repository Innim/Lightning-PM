<?php

/**
 * Возможные идентификаторы instance типов для таблицы members и подобных.
 * @created 17.11.2017
 * @version 1.0
 * @copyright (c) Innim LLC 2017
 * @author ChessMax (www.chessmax.ru)
 */
class LPMInstanceTypes
{
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
     * Instance тип для пользователя задачи в снимке.
     * @var int
     */
    const SNAPSHOT_ISSUE_MEMBERS = 3;

    /**
     * Instance задача для теста.
     */
    const ISSUE_FOR_TEST = 4;

    /**
     * Instance тестер задачи в снимке.
     */
    const SNAPSHOT_ISSUE_FOR_TEST = 5;

    /**
     * Instance тестер для проекта
     */
    const TESTER_FOR_PROJECT = 6;

    /**
     * Задача, для которой нужен мастер.
     */
    const ISSUE_FOR_MASTER = 7;

    /**
     * Instance snapshot
     */
    const SNAPSHOT = 8;

    /**
     * Проект, для которого назначен специализированный мастер.
     *
     * Это мастер, назначаемый по тегу.
     */
    const PROJECT_FOR_SPEC_MASTER = 7;
}
