<?php
/**
 * Ветки обсуждения задач в каналах Slack: по одной записи на пару
 * «задача - канал» с меткой времени сообщения, открывшего ветку.
 *
 * Ключ включает канал, потому что канал оповещений проекта можно сменить:
 * в новом канале у задачи начинается своя ветка, а метка прежней остаётся
 * и снова используется, если канал вернут обратно.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::SLACK_ISSUE_THREAD);

        if (!$this->tableExists($table)) {
            $this->exec("CREATE TABLE `{$table}` (
                `issueId` int NOT NULL COMMENT 'идентификатор задачи',
                `channel` varchar(255) NOT NULL COMMENT 'идентификатор канала Slack',
                `threadTs` varchar(32) NOT NULL COMMENT 'метка времени сообщения, открывшего ветку',
                PRIMARY KEY (`issueId`,`channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci "
                . "COMMENT='ветки обсуждения задач в каналах Slack'");
        }
    }

    public function down()
    {
        $table = $this->t(LPMTables::SLACK_ISSUE_THREAD);

        if ($this->tableExists($table)) {
            $this->exec("DROP TABLE `{$table}`");
        }
    }
};
