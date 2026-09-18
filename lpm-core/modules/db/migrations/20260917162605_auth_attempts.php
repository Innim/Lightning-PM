<?php
/**
 * Журнал неудачных попыток входа и запросов восстановления пароля —
 * основа ограничения на число попыток (см. AuthThrottle).
 *
 * Одна строка на попытку: счётчик за окно считается запросом, поэтому
 * параллельные попытки не теряются, как терялся бы инкремент счётчика
 * в одной строке.
 *
 * Адрес и учётная запись хранятся не как есть, а хэшем: длина под индекс
 * одинаковая, и в базе не копится список перебираемых чужих email'ов.
 */
return new class extends DbMigration {
    public function up()
    {
        $table = $this->t(LPMTables::AUTH_ATTEMPTS);

        if (!$this->tableExists($table)) {
            // Время — unix time: таблица техническая, её значения нигде
            // не показываются, а абсолютный момент не зависит от настройки
            // часовой зоны приложения.
            $this->exec("CREATE TABLE `{$table}` (
                `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор записи',
                `action` tinyint unsigned NOT NULL COMMENT 'Что считаем: 1 — вход, 2 — восстановление пароля',
                `scope` tinyint unsigned NOT NULL COMMENT 'По чему считаем: 1 — адрес, 2 — адрес и учётная запись, 3 — учётная запись',
                `scopeKey` char(64) NOT NULL COMMENT 'sha256 ключа счётчика',
                `date` int unsigned NOT NULL COMMENT 'Время попытки, unix time',
                PRIMARY KEY (`id`),
                KEY `action_scope_scopeKey_date` (`action`,`scope`,`scopeKey`,`date`),
                KEY `date` (`date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci "
                . "COMMENT='Неудачные попытки входа и запросы восстановления пароля'");
        }
    }

    public function down()
    {
        $table = $this->t(LPMTables::AUTH_ATTEMPTS);

        if ($this->tableExists($table)) {
            $this->exec("DROP TABLE `{$table}`");
        }
    }
};
