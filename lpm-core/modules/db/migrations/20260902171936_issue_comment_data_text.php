<?php
/**
 * Данные комментария о влитии веток хранят по каждой ветке ещё и коммит,
 * которым она попала в целевую ветку, и в 255 символов перестают помещаться.
 */
return new class extends DbMigration {
    public function up()
    {
        $this->exec("ALTER TABLE `{$this->t(LPMTables::ISSUE_COMMENT)}`
            MODIFY `data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
            COMMENT 'Данные комментария'");
    }

    public function down()
    {
        // Откат обрежет данные тех комментариев, что уже не помещаются
        // в 255 символов, поэтому длинные значения сначала очищаются:
        // комментарий без данных приложение читать умеет.
        $this->exec("UPDATE `{$this->t(LPMTables::ISSUE_COMMENT)}`
            SET `data` = '' WHERE LENGTH(`data`) > 255");
        $this->exec("ALTER TABLE `{$this->t(LPMTables::ISSUE_COMMENT)}`
            MODIFY `data` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
            COMMENT 'Данные комментария'");
    }
};
