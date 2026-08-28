<?php
/**
 * История слепков содержимого задач: каждая правка содержимого сохраняет
 * его новое состояние отдельной строкой, поэтому затёртый текст задачи
 * остаётся восстановимым.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::ISSUE_CONTENT_SNAPSHOTS);
        if ($this->tableExists($table)) {
            return;
        }

        $this->exec("CREATE TABLE `{$table}` (
            `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор слепка',
            `issueId` bigint NOT NULL COMMENT 'Идентификатор задачи',
            `name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Название задачи',
            `desc` text NOT NULL COMMENT 'Описание задачи',
            `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Тип задачи',
            `hours` decimal(10,1) NOT NULL DEFAULT '0.0' COMMENT 'Оценка: нормочасы или SP',
            `completeDate` datetime DEFAULT NULL COMMENT 'Плановая дата завершения',
            `editorId` bigint NOT NULL DEFAULT '0' COMMENT 'Пользователь, сохранивший слепок, 0 - неизвестен',
            `createdAt` datetime NOT NULL COMMENT 'Дата фиксации слепка',
            PRIMARY KEY (`id`),
            KEY `issueId_id` (`issueId`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='История слепков содержимого задач'");
    }

    public function down()
    {
        $table = $this->t(LPMTables::ISSUE_CONTENT_SNAPSHOTS);
        if (!$this->tableExists($table)) {
            return;
        }

        $this->exec("DROP TABLE `{$table}`");
    }
};
