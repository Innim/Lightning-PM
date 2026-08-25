<?php
/**
 * Признак того, что для задач проекта доступен чек-лист тестирования от ИИ.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if ($this->columnExists($table, 'aiTestChecklist')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `aiTestChecklist` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT 'Для задач проекта доступен чек-лист тестирования от ИИ' AFTER `aiSummary`");
    }

    public function down()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if (!$this->columnExists($table, 'aiTestChecklist')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `aiTestChecklist`");
    }
};
