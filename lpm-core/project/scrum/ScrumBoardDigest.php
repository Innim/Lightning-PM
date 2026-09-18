<?php
/**
 * Отпечаток состояния Scrum доски проекта.
 *
 * Короткая строка, которая меняется при любом изменении того, что доска
 * показывает, и не меняется, пока доска та же. Автообновление доски
 * ({@see ProjectService::refreshScrumBoard()}) спрашивает отпечаток на каждом
 * тике и тянет разметку, только когда он разошёлся с тем, что уже у клиента.
 *
 * Отпечаток не зависит от пользователя: разметка доски персональна (свои задачи,
 * задачи на проверку), но данные, из которых она строится, общие для проекта.
 *
 * ВАЖНО: набор источников должен совпадать с тем, что читает
 * `scrum-board-table.html`. Забытый источник - это изменение, которое молча
 * не доедет до открытой доски. Источники не обязательно в базе: то, что стикер
 * считает от текущего времени, тоже должно входить в отпечаток.
 */
class ScrumBoardDigest extends LPMBaseObject
{
    /**
     * Считает отпечаток доски проекта.
     * @param  int $projectId Идентификатор проекта.
     * @return string Отпечаток; пустая строка, если такого проекта нет.
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function load($projectId)
    {
        $projectId = (int)$projectId;

        $sources = [
            // Состав доски и колонки, плюс всё, что лежит в самой задаче:
            // название с тегами, приоритет, SP, статус, дата завершения.
            // Поля хэшируются, а не только дата правки: у неё точность
            // в секунду, и две правки в одну секунду по дате неразличимы
            self::sourceSqlHash(
                "CONCAT_WS('~', COUNT(*),"
                    . " IFNULL(BIT_XOR(CRC32(CONCAT_WS('~', `s`.`issueId`, `s`.`state`))), 0),"
                    . " IFNULL(MAX(`i`.`modifiedDate`), 0),"
                    . " IFNULL(BIT_XOR(CRC32(CONCAT_WS('~',"
                    . " `i`.`id`, `i`.`idInProject`, `i`.`name`, `i`.`hours`, `i`.`priority`,"
                    . " `i`.`status`, IFNULL(`i`.`completeDate`, ''), `i`.`type`))), 0))",
                $projectId
            ),
            // Исполнители и тестировщики задач. В стикере стоит их имя,
            // поэтому переименование участника - тоже изменение доски
            self::sourceSqlHash(
                "CONCAT_WS('~', COUNT(*),"
                    . " IFNULL(BIT_XOR(CRC32(CONCAT_WS('~',"
                    . " `m`.`instanceId`, `m`.`instanceType`, `m`.`userId`,"
                    . " `u`.`nick`, `u`.`firstName`, `u`.`lastName`))), 0))",
                $projectId,
                [
                    [
                        'INNER JOIN' => LPMTables::MEMBERS,
                        'AS'         => 'm',
                        'ON'         => ['`m`.`instanceId`' => self::col('s.issueId')],
                    ],
                    [
                        'INNER JOIN' => LPMTables::USERS,
                        'AS'         => 'u',
                        'ON'         => ['`u`.`userId`' => self::col('m.userId')],
                    ],
                ],
                ['`m`.`instanceType`' => [
                    LPMInstanceTypes::ISSUE,
                    LPMInstanceTypes::ISSUE_FOR_TEST,
                ]]
            ),
            // Отметки о проверке: ими задаётся подстатус задачи в тесте
            self::sourceSqlHash(
                "CONCAT_WS('~', COUNT(*), IFNULL(MAX(`c`.`date`), 0),"
                    . " IFNULL(MAX(`c`.`editDate`), 0), IFNULL(SUM(`c`.`deleted`), 0))",
                $projectId,
                [[
                    'INNER JOIN' => LPMTables::COMMENTS,
                    'AS'         => 'c',
                    'ON'         => ['`c`.`instanceId`' => self::col('s.issueId')],
                ]],
                ['`c`.`instanceType`' => LPMInstanceTypes::ISSUE]
            ),
            // События задачи: ими задаётся отметка «взята в тестирование»
            self::sourceSqlHash(
                "CONCAT_WS('~', COUNT(*), IFNULL(MAX(`e`.`date`), 0))",
                $projectId,
                [[
                    'INNER JOIN' => LPMTables::ISSUE_EVENT,
                    'AS'         => 'e',
                    'ON'         => ['`e`.`issueId`' => self::col('s.issueId')],
                ]]
            ),
            // Состояние merge request: от него зависит отметка о влитых правках
            self::sourceSqlHash(
                "CONCAT_WS('~', COUNT(*),"
                    . " IFNULL(BIT_XOR(CRC32(CONCAT_WS('~', `mr`.`issueId`, `mr`.`state`))), 0))",
                $projectId,
                [[
                    'INNER JOIN' => LPMTables::ISSUE_MR,
                    'AS'         => 'mr',
                    'ON'         => ['`mr`.`issueId`' => self::col('s.issueId')],
                ]]
            ),
            // Сводное состояние сборок. Статус хэшируется, а не только дата:
            // у неё точность в секунду, и смена статуса в ту же секунду
            // по одной дате была бы неотличима
            self::sourceSqlHash(
                "CONCAT_WS('~', COUNT(*), IFNULL(MAX(`pl`.`updatedAt`), 0),"
                    . " IFNULL(BIT_XOR(CRC32(CONCAT_WS('~',"
                    . " `pl`.`issueId`, `pl`.`pipelineId`, `pl`.`status`))), 0))",
                $projectId,
                [[
                    'INNER JOIN' => LPMTables::ISSUE_PIPELINE,
                    'AS'         => 'pl',
                    'ON'         => ['`pl`.`issueId`' => self::col('s.issueId')],
                ]]
            ),
            // Цели спринта: они выводятся над доской и меняются без стикеров
            [
                'SELECT' => 'IFNULL(BIT_XOR(CRC32(`t`.`content`)), 0)',
                'FROM'   => LPMTables::INSTANCE_TARGETS,
                'AS'     => 't',
                'WHERE'  => [
                    '`t`.`instanceType`' => LPMInstanceTypes::PROJECT,
                    '`t`.`instanceId`'   => $projectId,
                ],
            ],
            // Снимки доски: по ним считается номер текущего спринта
            [
                'SELECT' => "CONCAT_WS('~', COUNT(*), IFNULL(MAX(`sl`.`id`), 0))",
                'FROM'   => LPMTables::SCRUM_SNAPSHOT_LIST,
                'AS'     => 'sl',
                'WHERE'  => ['`sl`.`pid`' => $projectId],
            ],
        ];

        $select = [];
        foreach ($sources as $source) {
            $select[] = '(' . self::buildQuery($source) . ')';
        }
        // Настройки самого проекта: от них зависит, что и как доска показывает
        $select[] = '`p`.`lastUpdate`';

        $res = self::loadFromDV2([
            'SELECT' => implode(', ', $select),
            'FROM'   => LPMTables::PROJECTS,
            'AS'     => 'p',
            'WHERE'  => ['`p`.`id`' => $projectId],
            'LIMIT'  => 1,
        ]);

        $row = $res->fetch_row();
        if (empty($row)) {
            return '';
        }

        // Не из базы: уровень срока выполнения считается от начала текущего дня
        // ({@see Issue::daysTillComplete()}), поэтому в полночь меняется вид
        // стикера, а не данные. Без этой части доска, открытая с вечера,
        // до утра красила бы просроченный срок как ещё не сгоревший.
        $row[] = DateTimeUtils::dayStart();

        return md5(implode('|', $row));
    }

    /**
     * Описание подзапроса-агрегата по одному источнику данных доски.
     *
     * Все источники привязаны к задачам, стоящим на доске, поэтому область
     * выборки у них общая: стикер в колонке доски и неудалённая задача
     * нужного проекта.
     *
     * @param  string $select    Выражение с агрегатами; колонка должна быть
     *                           ровно одна - подзапрос скалярный.
     * @param  int    $projectId Идентификатор проекта.
     * @param  array  $joins     Присоединения источника к стикеру.
     * @param  array  $where     Дополнительные условия выборки источника.
     * @return array Описание запроса для конструктора.
     */
    private static function sourceSqlHash($select, $projectId, array $joins = [], array $where = [])
    {
        return [
            'SELECT' => $select,
            'FROM'   => LPMTables::SCRUM_STICKER,
            'AS'     => 's',
            'JOINS'  => array_merge([[
                'INNER JOIN' => LPMTables::ISSUES,
                'AS'         => 'i',
                'ON'         => ['`i`.`id`' => self::col('s.issueId')],
            ]], $joins),
            'WHERE'  => array_merge([
                '`i`.`projectId`' => $projectId,
                '`i`.`deleted`'   => 0,
                '`s`.`state`'     => ScrumStickerState::getActiveStates(),
            ], $where),
        ];
    }
}
