<?php
/**
 * Уникальность номера спринта внутри проекта.
 *
 * Номер снимка доски выдаётся как «последний плюс один», поэтому два
 * одновременных закрытия спринта могли выбрать один и тот же номер — и
 * развести историю спринта по двум снимкам. Индекс отклоняет второе такое
 * сохранение.
 *
 * Снимки, уже разошедшиеся по номерам до этой миграции, не чинятся
 * автоматически: какой из двух снимков описывает настоящий спринт, по данным
 * не понять, а склейка или удаление необратимы. Миграция такую базу
 * останавливает с внятным сообщением.
 */
return new class extends DbMigration {
    /**
     * Имя уникального индекса на пару «проект + номер спринта».
     */
    const INDEX = 'pid_idInProject';

    public function up()
    {
        $table = $this->t(LPMTables::SCRUM_SNAPSHOT_LIST);
        if ($this->indexExists($table, self::INDEX)) {
            return;
        }

        $duplicates = $this->findDuplicates($table);
        if (!empty($duplicates)) {
            throw new DbMigrationException(
                'в архиве спринтов есть снимки с одинаковым номером в проекте,'
                . ' индекс не создать — разберите их вручную (проект: номер —'
                . ' сколько снимков): ' . implode(', ', $duplicates)
            );
        }

        $this->exec("ALTER TABLE `{$table}` " .
            "ADD UNIQUE KEY `" . self::INDEX . "` (`pid`, `idInProject`)");
    }

    public function down()
    {
        $table = $this->t(LPMTables::SCRUM_SNAPSHOT_LIST);
        if (!$this->indexExists($table, self::INDEX)) {
            return;
        }

        $this->exec("ALTER TABLE `{$table}` DROP INDEX `" . self::INDEX . "`");
    }

    /**
     * Пары «проект + номер спринта», встречающиеся в архиве больше одного раза.
     * @param string $table Имя таблицы архива спринтов с префиксом.
     * @return array<string> Описания дублей для сообщения об ошибке.
     * @throws DbMigrationException Если запрос не удалось выполнить.
     */
    private function findDuplicates($table)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $sql = "SELECT `pid`, `idInProject`, COUNT(*) AS `cnt` FROM `{$table}`"
            . " GROUP BY `pid`, `idInProject` HAVING `cnt` > 1 ORDER BY `pid`, `idInProject`";

        $result = $db->query($sql);
        if ($result === false) {
            throw new DbMigrationException('не удалось проверить архив спринтов: ' . $db->error);
        }

        $duplicates = [];
        while ($row = $result->fetch_assoc()) {
            $duplicates[] = $row['pid'] . ': #' . $row['idInProject'] . ' — ' . $row['cnt'];
        }
        $result->free();

        return $duplicates;
    }
};
