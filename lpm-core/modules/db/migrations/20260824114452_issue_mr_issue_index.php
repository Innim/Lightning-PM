<?php
/**
 * Индекс по задаче для MR: состояние MR запрашивается для каждой задачи в тесте.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::ISSUE_MR);
        if ($this->indexExists($table, 'issueId_state')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` ADD KEY `issueId_state` (`issueId`, `state`)");
    }

    public function down()
    {
        $table = $this->t(LPMTables::ISSUE_MR);
        if (!$this->indexExists($table, 'issueId_state')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP KEY `issueId_state`");
    }
};
