<?php
/**
 * Признак того, что задачи проекта обязаны иметь хотя бы один тег.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if ($this->columnExists($table, 'requireLabels')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `requireLabels` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT 'Задачи проекта должны иметь теги' AFTER `aiSummary`");
    }

    public function down()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if (!$this->columnExists($table, 'requireLabels')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `requireLabels`");
    }
};
