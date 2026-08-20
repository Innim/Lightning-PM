<?php
/**
 * Базовый класс миграции схемы БД.
 *
 * Миграция — это файл `<метка времени>_<имя>.php` в каталоге `migrations`,
 * возвращающий анонимный класс-наследник:
 *
 * ```php
 * return new class extends DbMigration {
 *     public function up()
 *     {
 *         $this->exec("ALTER TABLE `{$this->t(LPMTables::PROJECTS)}`
 *             ADD `aiSummary` tinyint(1) NOT NULL DEFAULT '0'");
 *     }
 *
 *     public function down()
 *     {
 *         $this->exec("ALTER TABLE `{$this->t(LPMTables::PROJECTS)}` DROP `aiSummary`");
 *     }
 * };
 * ```
 *
 * Миграции применяются в порядке возрастания имени файла, поэтому имя
 * начинается с метки времени. Заготовку создаёт `migrate create <имя>`.
 *
 * `down()` необязателен: без него миграция считается необратимой и откат
 * завершится ошибкой. Транзакции не используются — MySQL выполняет DDL вне
 * транзакции, поэтому упавшая посередине миграция оставляет частично
 * изменённую схему, и её нужно чинить вручную. По той же причине миграцию
 * стоит писать так, чтобы повторный запуск после исправления был безопасен.
 *
 * @see DbMigrator
 */
abstract class DbMigration
{
    /**
     * Соединение с БД.
     * @var DBConnect
     */
    private $_db;

    /**
     * Каталог, в котором лежит файл миграции.
     * @var string
     */
    private $_dir;

    /**
     * Передаёт миграции окружение, в котором она выполняется.
     *
     * Вызывается DbMigrator сразу после загрузки файла миграции. Конструктора
     * у миграции нет намеренно: файл возвращает анонимный класс без аргументов,
     * и обязательные параметры конструктора сделали бы такую запись
     * невозможной.
     *
     * @param DBConnect $db Соединение с БД.
     * @param string $dir Каталог с файлом миграции — относительно него
     * разрешаются имена в execFile().
     */
    public function bind($db, $dir)
    {
        $this->_db = $db;
        $this->_dir = rtrim($dir, '/');
    }

    /**
     * Применяет миграцию.
     * @throws DbMigrationException Если запрос не удалось выполнить.
     */
    abstract public function up();

    /**
     * Отменяет миграцию.
     *
     * По умолчанию миграция необратима. Обратимой её делает переопределение
     * этого метода запросами, возвращающими схему в исходное состояние.
     *
     * @throws DbMigrationException Если откат не поддерживается или запрос
     * не удалось выполнить.
     */
    public function down()
    {
        throw new DbMigrationException('миграция не поддерживает откат');
    }

    /**
     * Имя таблицы с префиксом, принятым в этой установке.
     * @param string $table Имя таблицы без префикса — константа LPMTables.
     * @return string
     */
    protected function t($table)
    {
        return $this->_db->prefix . $table;
    }

    /**
     * Существует ли таблица в текущей базе.
     * @param string $table Имя таблицы с префиксом — см. t().
     * @return bool
     */
    protected function tableExists($table)
    {
        return DbMigrator::tableExists($this->_db, $table);
    }

    /**
     * Существует ли колонка в таблице текущей базы.
     * @param string $table Имя таблицы с префиксом — см. t().
     * @param string $column Имя колонки.
     * @return bool
     */
    protected function columnExists($table, $column)
    {
        return DbMigrator::columnExists($this->_db, $table, $column);
    }

    /**
     * Выполняет один SQL-запрос.
     * @param string $sql Запрос; пустая строка игнорируется.
     * @throws DbMigrationException Если запрос завершился ошибкой.
     */
    protected function exec($sql)
    {
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }

        $res = $this->_db->query($sql);
        if ($res === false) {
            throw new DbMigrationException(
                sprintf('%s (%s)', $this->_db->error, self::shortSql($sql))
            );
        }

        if ($res instanceof \mysqli_result) {
            $res->free();
        }
    }

    /**
     * Выполняет набор запросов из SQL-файла, лежащего рядом с миграцией.
     *
     * Файл целиком передаётся серверу MySQL — клиентского разбора SQL нет,
     * поэтому кавычки, комментарии и точки с запятой внутри строк
     * обрабатываются корректно. Запросы выполняются по порядку, до первой
     * ошибки; номер сломавшегося запроса попадает в текст исключения.
     *
     * Ограничение: `DELIMITER` — директива клиента mysql, а не сервера,
     * поэтому хранимые процедуры и триггеры так выполнить нельзя.
     *
     * @param string $fileName Имя файла в каталоге миграции.
     * @throws DbMigrationException Если файл недоступен или запрос завершился
     * ошибкой.
     */
    protected function execFile($fileName)
    {
        $path = $this->_dir . '/' . $fileName;
        if (!is_file($path)) {
            throw new DbMigrationException('файл не найден: ' . $fileName);
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new DbMigrationException('не удалось прочитать файл: ' . $fileName);
        }

        $this->execMulti($sql, $fileName);
    }

    /**
     * Короткое представление запроса для сообщения об ошибке.
     * @param string $sql
     * @return string
     */
    private static function shortSql($sql)
    {
        $sql = preg_replace('/\s+/u', ' ', trim($sql));
        return mb_strlen($sql, 'UTF-8') > 120
            ? mb_substr($sql, 0, 120, 'UTF-8') . '...'
            : $sql;
    }

    /**
     * Выполняет набор запросов и разбирает результаты по одному.
     *
     * Используются процедурные вызовы `mysqli_*`: они обращаются
     * непосредственно к соединению, минуя `DBConnect::multi_query()`, который
     * молча пропускает ошибки всех запросов, кроме первого.
     *
     * @param string $sql Набор запросов, разделённых `;`.
     * @param string $source Имя файла для сообщения об ошибке.
     * @throws DbMigrationException Если один из запросов завершился ошибкой.
     */
    private function execMulti($sql, $source)
    {
        $db = $this->_db;

        if (!mysqli_multi_query($db, $sql)) {
            throw new DbMigrationException(
                sprintf('%s, запрос 1: %s', $source, mysqli_error($db))
            );
        }

        $index = 1;
        do {
            $res = mysqli_store_result($db);
            if ($res instanceof \mysqli_result) {
                mysqli_free_result($res);
            }

            if (mysqli_errno($db)) {
                $this->failMulti($source, $index);
            }

            $index++;
        } while (mysqli_more_results($db) && mysqli_next_result($db));

        // mysqli_next_result() возвращает false и когда результатов больше нет,
        // и когда очередной запрос упал, — различает эти случаи только errno.
        if (mysqli_errno($db)) {
            $this->failMulti($source, $index);
        }
    }

    /**
     * Дочитывает оставшиеся результаты и бросает исключение с ошибкой СУБД.
     *
     * Незавершённый набор результатов остаётся в соединении и ломает
     * следующий запрос ошибкой «Commands out of sync», поэтому его нужно
     * дочитать до конца даже на аварийном пути.
     *
     * @param string $source Имя файла.
     * @param int $index Номер запроса, вызвавшего ошибку.
     * @throws DbMigrationException Всегда.
     */
    private function failMulti($source, $index)
    {
        $db = $this->_db;
        $error = mysqli_error($db);

        while (mysqli_more_results($db) && @mysqli_next_result($db)) {
            $res = mysqli_store_result($db);
            if ($res instanceof \mysqli_result) {
                mysqli_free_result($res);
            }
        }

        throw new DbMigrationException(sprintf('%s, запрос %d: %s', $source, $index, $error));
    }
}
