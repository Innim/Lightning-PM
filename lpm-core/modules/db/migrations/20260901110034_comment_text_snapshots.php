<?php
/**
 * История слепков текста комментариев и отметка о последней правке
 * в самом комментарии: отредактированный текст остаётся восстановимым,
 * а под комментарием видно, кто и когда его правил.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::COMMENT_TEXT_SNAPSHOTS);
        if (!$this->tableExists($table)) {
            $this->exec("CREATE TABLE `{$table}` (
                `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор слепка',
                `commentId` bigint NOT NULL COMMENT 'Идентификатор комментария',
                `text` text NOT NULL COMMENT 'Текст комментария',
                `editorId` bigint NOT NULL DEFAULT '0' COMMENT 'Пользователь, сохранивший слепок, 0 - неизвестен',
                `createdAt` datetime NOT NULL COMMENT 'Дата фиксации слепка',
                PRIMARY KEY (`id`),
                KEY `commentId_id` (`commentId`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='История слепков текста комментариев'");
        }

        $comments = $this->t(LPMTables::COMMENTS);
        if (!$this->columnExists($comments, 'editorId')) {
            $this->exec("ALTER TABLE `{$comments}`
                ADD `editorId` bigint NOT NULL DEFAULT '0'
                COMMENT 'Пользователь, правивший текст последним, 0 - правок не было' AFTER `text`");
        }
        if (!$this->columnExists($comments, 'editDate')) {
            $this->exec("ALTER TABLE `{$comments}`
                ADD `editDate` datetime DEFAULT NULL
                COMMENT 'Дата последней правки текста, NULL - правок не было' AFTER `editorId`");
        }
    }

    public function down()
    {
        $comments = $this->t(LPMTables::COMMENTS);
        if ($this->columnExists($comments, 'editDate')) {
            $this->exec("ALTER TABLE `{$comments}` DROP `editDate`");
        }
        if ($this->columnExists($comments, 'editorId')) {
            $this->exec("ALTER TABLE `{$comments}` DROP `editorId`");
        }

        $table = $this->t(LPMTables::COMMENT_TEXT_SNAPSHOTS);
        if ($this->tableExists($table)) {
            $this->exec("DROP TABLE `{$table}`");
        }
    }
};
