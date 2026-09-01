<?php
/**
 * Настройка пользователя: показывать ли на личной scrum доске свободные задачи.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::USERS_PREF);
        if ($this->columnExists($table, 'showFreeIssuesOnBoard')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `showFreeIssuesOnBoard` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT 'показывать свободные задачи на личной scrum доске'");
    }

    public function down()
    {
        $table = $this->t(LPMTables::USERS_PREF);
        if (!$this->columnExists($table, 'showFreeIssuesOnBoard')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `showFreeIssuesOnBoard`");
    }
};
