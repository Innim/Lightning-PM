<?php
/**
 * Отметки о посещении проектов пользователями: по одной записи
 * на пару «пользователь — проект», хранящей время последнего захода.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::PROJECT_VISITS);
        if (!$this->tableExists($table)) {
            $this->exec("CREATE TABLE `{$table}` (
                `userId` bigint NOT NULL COMMENT 'Идентификатор пользователя',
                `projectId` bigint NOT NULL COMMENT 'Идентификатор проекта',
                `visitDate` datetime(3) NOT NULL COMMENT 'Дата последнего открытия страницы проекта',
                PRIMARY KEY (`userId`,`projectId`),
                KEY `userId_visitDate` (`userId`,`visitDate`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Посещения проектов пользователями'");
        }
    }

    public function down()
    {
        $table = $this->t(LPMTables::PROJECT_VISITS);
        if ($this->tableExists($table)) {
            $this->exec("DROP TABLE `{$table}`");
        }
    }
};
