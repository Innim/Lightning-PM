<?php
/**
 * Состояния сборок, запущенных влитием merge request'ов задач:
 * по одной записи на пару «задача — merge request».
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::ISSUE_PIPELINE);
        if (!$this->tableExists($table)) {
            $this->exec("CREATE TABLE `{$table}` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `issueId` bigint NOT NULL COMMENT 'Идентификатор задачи',
                `mrId` bigint NOT NULL COMMENT 'Идентификатор merge request на GitLab',
                `repositoryId` bigint NOT NULL COMMENT 'Идентификатор репозитория на GitLab',
                `branch` varchar(255) NOT NULL COMMENT 'Ветка merge request',
                `ref` varchar(255) NOT NULL COMMENT 'Ветка, в которую влит merge request',
                `sha` varchar(64) NOT NULL COMMENT 'Коммит, для которого запущена сборка',
                `pipelineId` bigint NOT NULL DEFAULT '0' COMMENT 'Идентификатор пайплайна на GitLab, 0 - неизвестен',
                `status` varchar(32) NOT NULL DEFAULT '' COMMENT 'Статус пайплайна на GitLab, пустой - неизвестен',
                `url` varchar(512) NOT NULL DEFAULT '' COMMENT 'Ссылка на пайплайн',
                `finishedAt` bigint NOT NULL DEFAULT '0' COMMENT 'Время завершения сборки, unixtime, 0 - не завершена',
                `updatedAt` datetime NOT NULL COMMENT 'Когда состояние записано',
                PRIMARY KEY (`id`),
                UNIQUE KEY `issueId_mrId` (`issueId`,`mrId`),
                KEY `repositoryId_ref_sha` (`repositoryId`,`ref`,`sha`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Состояния сборок по влитым merge request задач'");
        }
    }

    public function down()
    {
        $table = $this->t(LPMTables::ISSUE_PIPELINE);
        if ($this->tableExists($table)) {
            $this->exec("DROP TABLE `{$table}`");
        }
    }
};
