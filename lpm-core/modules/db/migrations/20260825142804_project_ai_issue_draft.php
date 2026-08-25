<?php
/**
 * Признак того, что в проекте доступен черновик задачи от ИИ.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if ($this->columnExists($table, 'aiIssueDraft')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}`
            ADD `aiIssueDraft` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT 'В проекте доступен черновик задачи от ИИ' AFTER `aiTestChecklist`");
    }

    public function down()
    {
        $table = $this->t(LPMTables::PROJECTS);
        if (!$this->columnExists($table, 'aiIssueDraft')) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP `aiIssueDraft`");
    }
};
