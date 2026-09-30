<?php
/**
 * Пересчёт счётчика комментариев задач без служебных записей ленты.
 *
 * Счётчик — хранимый агрегат: он обновляется только при добавлении или
 * удалении комментария, поэтому без разового пересчёта задачи, в которых
 * больше не пишут, навсегда остались бы с прежним, завышенным числом.
 */
return new class extends DbMigration {
    public function up()
    {
        $this->exec($this->recountSql(IssueComment::getAutoCommentTypes()));
    }

    public function down()
    {
        $this->exec($this->recountSql([]));
    }

    /**
     * Запрос, проставляющий каждому счётчику число комментариев его задачи.
     *
     * @param  string[] $skipTypes Типы записей, которые не считаются;
     *                             пустой список — считать все комментарии.
     * @return string SQL запрос.
     * @throws DbMigrationException Если тип не похож на имя типа.
     */
    private function recountSql(array $skipTypes)
    {
        $counters = $this->t(LPMTables::ISSUE_COUNTERS);
        $comments = $this->t(LPMTables::COMMENTS);
        $issueType = (int)LPMInstanceTypes::ISSUE;

        $join = '';
        $skip = '';
        if (!empty($skipTypes)) {
            foreach ($skipTypes as $type) {
                // Значения приходят константами кода, но подставляются в запрос
                // строкой — экранировать здесь нечем, поэтому вид проверяется
                if (!preg_match('/^[a-z_]+$/', $type)) {
                    throw new DbMigrationException('неожиданный тип комментария: ' . $type);
                }
            }

            $join = "LEFT JOIN `{$this->t(LPMTables::ISSUE_COMMENT)}` `ic` " .
                        "ON `ic`.`commentId` = `c`.`id`";
            $skip = "AND (`ic`.`commentId` IS NULL " .
                        "OR `ic`.`type` NOT IN ('" . implode("', '", $skipTypes) . "'))";
        }

        // Счётчики без единого подходящего комментария обнуляются, поэтому
        // соединение внешнее: иначе повторный запуск оставил бы им старое число
        return "UPDATE `{$counters}` `ctr`
                   LEFT JOIN (
                        SELECT `c`.`instanceId` AS `issueId`, COUNT(*) AS `cnt`
                          FROM `{$comments}` `c`
                          {$join}
                         WHERE `c`.`instanceType` = {$issueType}
                           AND `c`.`deleted` = 0
                           {$skip}
                         GROUP BY `c`.`instanceId`
                   ) `src` ON `src`.`issueId` = `ctr`.`issueId`
                    SET `ctr`.`commentsCount` = COALESCE(`src`.`cnt`, 0)";
    }
};
