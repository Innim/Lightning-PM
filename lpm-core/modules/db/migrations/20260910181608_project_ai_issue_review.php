<?php
/**
 * Признак того, что в проекте доступна проверка постановки задачи от ИИ.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if ($this->columnExists($table, 'aiIssueReview')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `aiIssueReview` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT 'В проекте доступна проверка постановки задачи от ИИ' AFTER `aiIssueDraft`");
    }

    public function down()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if (!$this->columnExists($table, 'aiIssueReview')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `aiIssueReview`");
    }
};
