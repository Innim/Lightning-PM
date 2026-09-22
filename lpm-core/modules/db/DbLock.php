<?php
/**
 * Именованная блокировка на стороне MySQL.
 *
 * Нужна там, где параллельные запросы должны выполнять операцию по очереди,
 * а транзакции для этого мало: блокировка не привязана к таблице и поэтому
 * работает и для MyISAM-таблиц, у которых нет строчных блокировок.
 *
 * Блокировку держит соединение, поэтому оборвавшийся запрос освобождает её
 * сам. Повторный захват того же имени тем же соединением не ждёт, но требует
 * столько же освобождений.
 *
 * Имя блокировки принадлежит серверу MySQL, а не базе, поэтому две установки
 * на одном сервере различаются по базе и префиксу таблиц - иначе они ждали бы
 * друг друга.
 *
 * Запросы здесь написаны сырым SQL в обход конструктора V2: `GET_LOCK` и
 * `RELEASE_LOCK` - вызовы функций сервера, а не выборка из таблицы, и
 * конструктор их не выражает.
 */
class DbLock
{
    /**
     * Захватывает блокировку, дожидаясь освобождения не дольше $timeout.
     * @param string $name Имя блокировки, уникальное в рамках установки.
     * @param int $timeout Сколько секунд ждать освобождения.
     * @return bool `false`, если за отведённое время блокировку взять не удалось.
     * @throws DBException Если запрос к базе завершился ошибкой.
     */
    public static function acquire($name, $timeout = 10)
    {
        $db = self::getDB();
        $sql = 'SELECT GET_LOCK(\'' . $db->real_escape_string(self::key($name)) . '\', ' .
            (int)$timeout . ') AS `locked`';

        if (!$query = $db->query($sql)) {
            throw new DBException($db, 'Ошибка при захвате блокировки ' . $name);
        }

        $row = $query->fetch_assoc();
        $query->free();

        // NULL - ошибка на стороне MySQL, 0 - истекло время ожидания
        return !empty($row) && (int)$row['locked'] === 1;
    }

    /**
     * Освобождает ранее захваченную блокировку.
     *
     * Освобождение чужой или уже отпущенной блокировки ошибкой не считается.
     * @param string $name Имя блокировки.
     */
    public static function release($name)
    {
        $db = self::getDB();
        $db->query('DO RELEASE_LOCK(\'' . $db->real_escape_string(self::key($name)) . '\')');
    }

    /**
     * Полное имя блокировки на сервере MySQL.
     *
     * Имя сворачивается в хеш: MySQL отвергает имена длиннее 64 символов,
     * а длину исходного имени задаёт вызывающий код.
     * @param string $name Имя блокировки в рамках установки.
     * @return string
     */
    private static function key($name)
    {
        return 'lpm_lock_' . md5(DB_NAME . self::getDB()->prefix . ':' . $name);
    }

    /**
     * @return DBConnect
     */
    private static function getDB()
    {
        return LPMGlobals::getInstance()->getDBConnect();
    }
}
