<?php
/**
 * Уникальность номера задачи внутри проекта.
 *
 * Номер выдаётся как «последний плюс один», поэтому два одновременных создания
 * задачи в одном проекте могут выбрать один и тот же номер — и получить две
 * задачи с одним адресом. Индекс отклоняет второе такое сохранение,
 * а Issue::createNew() берёт номер заново.
 */
return new class extends DbMigration {
    /**
     * Имя уникального индекса на пару «проект + номер задачи».
     */
    const INDEX = 'projectId_idInProject';

    public function up()
    {
        $table = $this->t(LPMTables::ISSUES);
        if ($this->indexExists($table, self::INDEX)) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` " .
            "ADD UNIQUE KEY `" . self::INDEX . "` (`projectId`, `idInProject`)");
    }

    public function down()
    {
        $table = $this->t(LPMTables::ISSUES);
        if (!$this->indexExists($table, self::INDEX)) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP INDEX `" . self::INDEX . "`");
    }
};
