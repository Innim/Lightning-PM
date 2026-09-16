<?php
/**
 * Первый шаг перевода дат на абсолютное время.
 *
 * Колонки `datetime` хранят не момент, а показание часов в той зоне, в которой
 * приложение работало во время записи: меняется настройка зоны — «меняется»
 * и всё, что уже записано. Момент хранит тип `timestamp`: значение уходит
 * в базу в UTC, а наружу отдаётся в зоне соединения.
 *
 * Шаг аддитивный: рядом с каждой такой колонкой заводится колонка-близнец
 * с суффиксом `Utc`, и в неё переносится текущее содержимое. Чтение остаётся
 * на старых колонках — их переключение и удаление старых колонок делают
 * следующие релизы.
 *
 * Перенос сохраняет показание часов: значение читается в зоне приложения,
 * поэтому в интерфейсе даты выглядят ровно так же, как до миграции.
 * Реконструкции истории нет — значения, записанные в другие эпохи (до 2016
 * зона записи была другой), переносятся как есть.
 *
 * Не переводятся:
 * - `issues.completeDate` и `issue_content_snapshots.completeDate` — это
 *   календарные даты («завершить к 15 июля»), а не моменты; сдвиг зоны
 *   превратил бы дату в предыдущую;
 * - `issues.startDate` — колонка мертва, писателя нет;
 * - `db_migrations.appliedAt` — журнал самих миграций;
 * - `files.created`, `file_links.created`, `images.date`, `projects.lastUpdate`
 *   — уже `timestamp`, то есть уже абсолютны.
 */
return new class extends DbMigration {
    /**
     * Колонки к переводу: таблица, старая колонка, новая колонка, её тип.
     *
     * Новая колонка всегда допускает NULL: у старых колонок «нет значения»
     * выражается и NULL, и нулевой датой, а нулевую дату `timestamp`
     * не принимает.
     *
     * DEFAULT задан явно у каждой: при выключенном
     * `explicit_defaults_for_timestamp` первая колонка `timestamp` в таблице
     * иначе получила бы автозаполнение `DEFAULT CURRENT_TIMESTAMP
     * ON UPDATE CURRENT_TIMESTAMP`, которого мы не просили.
     *
     * @return array<array{0:string,1:string,2:string,3:string}>
     */
    private static function columns()
    {
        $ts = 'timestamp NULL DEFAULT NULL';

        return [
            [LPMTables::AI_ISSUE_SUMMARY, 'createdAt', 'createdAtUtc', $ts],
            [LPMTables::API_KEYS, 'created', 'createdUtc', $ts],
            [LPMTables::COMMENT_TEXT_SNAPSHOTS, 'createdAt', 'createdAtUtc', $ts],
            [LPMTables::COMMENTS, 'date', 'dateUtc', $ts],
            [LPMTables::COMMENTS, 'editDate', 'editDateUtc', $ts],
            [LPMTables::FIXED_INSTANCE, 'dateFixed', 'dateFixedUtc', $ts],
            [LPMTables::ISSUE_BRANCH, 'date', 'dateUtc', $ts],
            [LPMTables::ISSUE_CONTENT_SNAPSHOTS, 'createdAt', 'createdAtUtc', $ts],
            [LPMTables::ISSUE_EVENT, 'date', 'dateUtc', $ts],
            [LPMTables::ISSUE_LINKED, 'created', 'createdUtc', $ts],
            [LPMTables::ISSUE_PIPELINE, 'updatedAt', 'updatedAtUtc', $ts],
            [LPMTables::ISSUES, 'createDate', 'createDateUtc', $ts],
            // Колонку заполняет сама СУБД при любом изменении строки — как и
            // старую `modifiedDate`, иначе дата изменения перестала бы
            // обновляться у задач, которые правят в обход этой миграции.
            [LPMTables::ISSUES, 'modifiedDate', 'modifiedDateUtc', $ts . ' ON UPDATE CURRENT_TIMESTAMP'],
            [LPMTables::ISSUES, 'completedDate', 'completedDateUtc', $ts],
            // Миллисекунды различают проекты, открытые в одну секунду:
            // без них список недавних проектов перемешивается.
            [LPMTables::PROJECT_VISITS, 'visitDate', 'visitDateUtc', 'timestamp(3) NULL DEFAULT NULL'],
            [LPMTables::PROJECTS, 'date', 'dateUtc', $ts],
            [LPMTables::RECOVERY_EMAILS, 'expDate', 'expDateUtc', $ts],
            [LPMTables::SCRUM_SNAPSHOT, 'added', 'addedUtc', $ts],
            [LPMTables::SCRUM_SNAPSHOT_LIST, 'started', 'startedUtc', $ts],
            [LPMTables::SCRUM_SNAPSHOT_LIST, 'created', 'createdUtc', $ts],
            [LPMTables::SCRUM_STICKER, 'added', 'addedUtc', $ts],
            [LPMTables::USER_AUTH, 'hasCreated', 'hasCreatedUtc', $ts],
            [LPMTables::USER_LOCKS, 'date', 'dateUtc', $ts],
            [LPMTables::USER_LOCKS, 'expired', 'expiredUtc', $ts],
            [LPMTables::USERS, 'lastVisit', 'lastVisitUtc', $ts],
            [LPMTables::USERS, 'regDate', 'regDateUtc', $ts],
            [LPMTables::USERS_LOG, 'date', 'dateUtc', $ts],
        ];
    }

    /**
     * Индексы-близнецы: таблица, имя индекса, колонки.
     *
     * Повторяют индексы по старым колонкам, чтобы переключение чтения
     * не требовало ещё одной перестройки таблиц.
     *
     * Индекса по `fixed_instance.dateFixed` здесь нет: там колонка входит
     * в первичный ключ, и его перестройка относится к релизу, который удаляет
     * старую колонку.
     *
     * @return array<array{0:string,1:string,2:string}>
     */
    private static function indexes()
    {
        return [
            [LPMTables::COMMENTS, 'instanceType_instanceId_dateUtc', '`instanceType`, `instanceId`, `dateUtc`'],
            [LPMTables::ISSUE_EVENT, 'issueId_dateUtc', '`issueId`, `dateUtc`'],
            [LPMTables::ISSUE_EVENT, 'issueId_type_dateUtc', '`issueId`, `type`, `dateUtc`'],
            [LPMTables::ISSUES, 'projectId_completedDateUtc', '`projectId`, `completedDateUtc`'],
            [LPMTables::PROJECT_VISITS, 'userId_visitDateUtc', '`userId`, `visitDateUtc`'],
        ];
    }

    /**
     * Колонки, которые СУБД обновляет сама при изменении строки.
     *
     * Перенос данных — это UPDATE, и он бы сбросил такую колонку на текущее
     * время во всей таблице. Чтобы этого не случилось, колонка получает
     * в том же UPDATE присваивание самой себе: заданное явно значение
     * отменяет автообновление.
     *
     * @return array<string,array<string>> Таблица => колонки.
     */
    private static function autoUpdatedColumns()
    {
        return [
            LPMTables::ISSUES => ['modifiedDate'],
            LPMTables::PROJECTS => ['lastUpdate'],
        ];
    }

    public function up()
    {
        // Перенос читает старые значения как показания часов в зоне
        // приложения. Зона задаётся явно: миграцию могут применить
        // соединением, которое её ещё не объявило.
        $this->exec("SET time_zone = '" . AppTimeZone::mysqlOffset() . "'");

        foreach (self::columns() as $column) {
            list($table, $old, $new, $definition) = $column;
            $name = $this->t($table);
            if (!$this->columnExists($name, $new)) {
                $this->exec("ALTER TABLE `{$name}` ADD `{$new}` {$definition} AFTER `{$old}`");
            }
        }

        foreach (self::indexes() as $index) {
            list($table, $name, $columns) = $index;
            $tableName = $this->t($table);
            if (!$this->indexExists($tableName, $name)) {
                $this->exec("ALTER TABLE `{$tableName}` ADD INDEX `{$name}` ({$columns})");
            }
        }

        foreach ($this->buildBackfillQueries() as $sql) {
            $this->exec($sql);
        }
    }

    public function down()
    {
        foreach (self::indexes() as $index) {
            list($table, $name) = $index;
            $tableName = $this->t($table);
            if ($this->indexExists($tableName, $name)) {
                $this->exec("ALTER TABLE `{$tableName}` DROP INDEX `{$name}`");
            }
        }

        foreach (self::columns() as $column) {
            list($table, , $new) = $column;
            $name = $this->t($table);
            if ($this->columnExists($name, $new)) {
                $this->exec("ALTER TABLE `{$name}` DROP `{$new}`");
            }
        }
    }

    /**
     * Запросы переноса данных — по одному на таблицу.
     *
     * Нулевая дата и NULL в старой колонке дают NULL в новой: `timestamp`
     * нулевую дату не принимает, а при строгом режиме молчаливое приведение
     * уронило бы запрос.
     *
     * Запросы идемпотентны: повторный прогон пересчитывает новые колонки
     * из тех же старых значений.
     *
     * @return array<string>
     */
    private function buildBackfillQueries()
    {
        $byTable = [];
        foreach (self::columns() as $column) {
            list($table, $old, $new) = $column;
            $byTable[$table][$new] = "`{$new}` = IF(`{$old}` > 0, `{$old}`, NULL)";
        }

        $autoUpdated = self::autoUpdatedColumns();
        $queries = [];

        foreach ($byTable as $table => $sets) {
            if (isset($autoUpdated[$table])) {
                foreach ($autoUpdated[$table] as $column) {
                    if (!isset($sets[$column])) {
                        $sets[$column] = "`{$column}` = `{$column}`";
                    }
                }
            }

            $queries[] = "UPDATE `{$this->t($table)}` SET " . implode(', ', $sets);
        }

        return $queries;
    }
};
