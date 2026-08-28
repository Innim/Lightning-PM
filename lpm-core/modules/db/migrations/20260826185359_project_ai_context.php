<?php
/**
 * Контекст предметной области проекта, подмешиваемый в запросы к ИИ.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if ($this->columnExists($table, 'aiContext')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `aiContext` text CHARACTER SET utf8mb4 NULL DEFAULT NULL
            COMMENT 'Контекст предметной области проекта для запросов к ИИ' AFTER `aiIssueDraft`");
    }

    public function down()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if (!$this->columnExists($table, 'aiContext')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `aiContext`");
    }
};
