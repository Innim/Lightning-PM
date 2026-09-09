<?php
/**
 * Настройка пользователя: по какой его роли в задаче отбирается личная scrum доска.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::USERS_PREF);
        if ($this->columnExists($table, 'myBoardRole')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `myBoardRole` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT 'роль в задаче, по которой отбирается личная scrum доска'
            AFTER `showFreeIssuesOnBoard`");
    }

    public function down()
    {
        $table = $this->t(LPMTables::USERS_PREF);
        if (!$this->columnExists($table, 'myBoardRole')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `myBoardRole`");
    }
};
