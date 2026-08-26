<?php
/**
 * Журнал событий задачи: кто и когда что с ней сделал.
 *
 * Общая таблица под любые события задачи - новое событие добавляется
 * значением `type`, а не новой колонкой. Первыми в неё пишутся взятие
 * задачи в тестирование и снятие этой отметки.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::ISSUE_EVENT);
        if ($this->tableExists($table)) {
            return;
        }

        $this->exec("CREATE TABLE `{$table}` (
            `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'ID события',
            `issueId` bigint NOT NULL COMMENT 'ID задачи',
            `type` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
                COMMENT 'Тип события',
            `authorId` bigint NOT NULL COMMENT 'ID пользователя, совершившего событие',
            `date` datetime NOT NULL COMMENT 'Дата и время события',
            `data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL
                COMMENT 'Дополнительные данные события',
            PRIMARY KEY (`id`),
            KEY `issueId_type_date` (`issueId`,`type`,`date`),
            KEY `issueId_date` (`issueId`,`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci
          COMMENT='Журнал событий задачи.'");
    }

    public function down()
    {
        $table = $this->t(LPMTables::ISSUE_EVENT);
        if (!$this->tableExists($table)) {
            return;
        }

        $this->exec("DROP TABLE `{$table}`");
    }
};
