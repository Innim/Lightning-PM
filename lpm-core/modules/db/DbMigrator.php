<?php
/**
 * Применение миграций схемы БД.
 *
 * Миграции лежат в каталоге `migrations` и применяются в порядке возрастания
 * имени файла. Учёт ведётся по именам файлов в журнале DbMigrationLog:
 * применяются все миграции, которых в журнале нет, независимо от того, новее
 * они последней применённой или старше — иначе миграции, попавшие в общую
 * ветку из разных веток разработки, терялись бы.
 *
 * Одновременный запуск исключён блокировкой на стороне СУБД: параллельные
 * деплой и запуск из интерфейса администратора не пересекутся.
 *
 * @see DbMigration Формат файла миграции.
 */
class DbMigrator
{
    /**
     * Миграция, содержащая схему на момент перехода на миграции.
     *
     * На установке, существовавшей до этого перехода, схема уже создана
     * дампом, поэтому все миграции по эту включительно при первом запуске
     * помечаются применёнными без выполнения. Всё, что новее, применяется
     * обычным порядком — обновление через несколько версий не пропустит
     * миграции, вышедшие после перехода.
     */
    const BASELINE = '00000000000000_initial_schema';

    /**
     * Существует ли таблица в текущей базе.
     * @param DBConnect $db Соединение с БД.
     * @param string $table Имя таблицы с префиксом.
     * @return bool
     */
    public static function tableExists($db, $table)
    {
        $sql = "SELECT 1 FROM `information_schema`.`TABLES`
            WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '"
            . $db->escape_string($table) . "' LIMIT 1";

        $res = $db->query($sql);
        if ($res === false) {
            return false;
        }

        $exists = $res->num_rows > 0;
        $res->free();

        return $exists;
    }

    /**
     * Существует ли колонка в таблице текущей базы.
     * @param DBConnect $db Соединение с БД.
     * @param string $table Имя таблицы с префиксом.
     * @param string $column Имя колонки.
     * @return bool
     */
    public static function columnExists($db, $table, $column)
    {
        $sql = "SELECT 1 FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '"
            . $db->escape_string($table) . "' AND `COLUMN_NAME` = '"
            . $db->escape_string($column) . "' LIMIT 1";

        $res = $db->query($sql);
        if ($res === false) {
            return false;
        }

        $exists = $res->num_rows > 0;
        $res->free();

        return $exists;
    }

    /**
     * Соединение с БД.
     * @var DBConnect
     */
    private $_db;

    /**
     * Пользователь, от имени которого выполняются миграции (0 — CLI).
     * @var int
     */
    private $_userId;

    /**
     * Захвачена ли блокировка текущим экземпляром.
     * @var bool
     */
    private $_locked = false;

    /**
     * @param int $userId Пользователь, запустивший миграции (0 — запуск из CLI).
     */
    public function __construct($userId = 0)
    {
        $this->_db = LPMGlobals::getInstance()->getDBConnect();
        $this->_userId = (int)$userId;
    }

    /**
     * Каталог с файлами миграций.
     * @return string
     */
    public function getMigrationsDir()
    {
        return __DIR__ . '/migrations';
    }

    /**
     * Состояние всех миграций в порядке применения.
     *
     * Ничего не изменяет: если журнала ещё нет, все миграции считаются
     * неприменёнными.
     *
     * @return DbMigrationState[]
     */
    public function getStatus()
    {
        $logs = $this->loadLogs();

        $states = [];
        foreach ($this->findFiles() as $path) {
            $name = basename($path, '.php');
            $states[] = new DbMigrationState(
                $name,
                $path,
                $this->getChecksum($path),
                isset($logs[$name]) ? $logs[$name] : null
            );
        }

        return $states;
    }

    /**
     * Миграции, о которых есть запись в журнале, но нет файла.
     *
     * Признак того, что файл удалён или переименован уже после применения:
     * откатить такую миграцию нечем.
     *
     * @return string[] Имена миграций.
     */
    public function getOrphanNames()
    {
        $names = [];
        foreach ($this->findFiles() as $path) {
            $names[basename($path, '.php')] = true;
        }

        $result = [];
        foreach ($this->loadLogs() as $name => $log) {
            if (!isset($names[$name])) {
                $result[] = $name;
            }
        }

        return $result;
    }

    /**
     * Что сделает apply(), ничего не изменяя.
     * @return array `baseline` — миграции, которые будут отмечены применёнными
     * без выполнения, `pending` — которые будут применены.
     */
    public function preview()
    {
        $states = $this->getStatus();
        $baseline = $this->getBaselineCandidates($states);

        $pending = [];
        foreach ($states as $state) {
            if ($state->isPending() && !in_array($state->name, $baseline, true)) {
                $pending[] = $state->name;
            }
        }

        return ['baseline' => $baseline, 'pending' => $pending];
    }

    /**
     * Применяет все неприменённые миграции.
     *
     * Выполнение прекращается на первой ошибке: следующие миграции могут
     * зависеть от изменений упавшей, и применять их по сломанной схеме
     * опаснее, чем остановиться.
     *
     * @return array `baseline` — имена миграций, отмеченных применёнными без
     * выполнения; `results` — по записи на каждую выполненную миграцию:
     * `name`, `ok`, `error`, `execTime`.
     * @throws DbMigrationException Если не удалось получить блокировку или
     * создать журнал.
     */
    public function apply()
    {
        $this->lock();

        try {
            DbMigrationLog::createTable($this->_db);

            $baseline = $this->applyBaseline();

            $results = [];
            foreach ($this->getStatus() as $state) {
                if (!$state->isPending()) {
                    continue;
                }

                $result = $this->runUp($state);
                $results[] = $result;

                if (!$result['ok']) {
                    break;
                }
            }

            return ['baseline' => $baseline, 'results' => $results];
        } finally {
            $this->unlock();
        }
    }

    /**
     * Откатывает последние применённые миграции.
     *
     * @param int $steps Сколько миграций откатить, начиная с последней.
     * @return array По записи на каждую миграцию: `name`, `ok`, `error`,
     * `execTime`.
     * @throws DbMigrationException Если не удалось получить блокировку.
     */
    public function rollback($steps = 1)
    {
        $steps = max(1, (int)$steps);

        $this->lock();

        try {
            $applied = [];
            foreach ($this->getStatus() as $state) {
                if ($state->isApplied()) {
                    $applied[] = $state;
                }
            }

            $results = [];
            foreach (array_slice(array_reverse($applied), 0, $steps) as $state) {
                $result = $this->runDown($state);
                $results[] = $result;

                if (!$result['ok']) {
                    break;
                }
            }

            return $results;
        } finally {
            $this->unlock();
        }
    }

    /**
     * Отмечает все неприменённые миграции применёнными, не выполняя их.
     *
     * Нужно, когда изменения схемы уже сделаны в обход миграций — например,
     * применены вручную из SQL-файла.
     *
     * @return string[] Имена отмеченных миграций.
     * @throws DbMigrationException Если не удалось получить блокировку или
     * создать журнал.
     */
    public function baseline()
    {
        $this->lock();

        try {
            DbMigrationLog::createTable($this->_db);

            $names = [];
            foreach ($this->getStatus() as $state) {
                if ($state->isApplied()) {
                    continue;
                }

                DbMigrationLog::markBaseline($state->name, $state->checksum, $this->_userId);
                $names[] = $state->name;
            }

            if (!empty($names)) {
                LPMLog::info(
                    'Миграции отмечены применёнными без выполнения',
                    LPMLog::CH_DB,
                    ['names' => $names, 'userId' => $this->_userId]
                );
            }

            return $names;
        } finally {
            $this->unlock();
        }
    }

    /**
     * Создаёт заготовку миграции.
     * @param string $slug Имя миграции: латиница, цифры и `_`.
     * @return string Полный путь к созданному файлу.
     * @throws DbMigrationException Если имя некорректно или файл не создан.
     */
    public function create($slug)
    {
        $slug = strtolower(trim((string)$slug));
        $slug = str_replace(['-', ' '], '_', $slug);

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $slug)) {
            throw new DbMigrationException(
                'имя миграции должно начинаться с латинской буквы и состоять'
                . ' из латиницы, цифр и подчёркиваний'
            );
        }

        $name = date('YmdHis') . '_' . $slug;
        $path = $this->getMigrationsDir() . '/' . $name . '.php';

        if (file_exists($path)) {
            throw new DbMigrationException('миграция уже существует: ' . $name);
        }

        if (file_put_contents($path, $this->getStubContent()) === false) {
            throw new DbMigrationException('не удалось создать файл: ' . $path);
        }

        return $path;
    }

    /**
     * Файлы миграций в порядке применения.
     * @return string[] Полные пути.
     */
    private function findFiles()
    {
        $files = glob($this->getMigrationsDir() . '/*.php');
        if ($files === false) {
            return [];
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Контрольная сумма миграции.
     *
     * Считается по файлу миграции вместе с его спутниками — файлами с тем же
     * именем и другим расширением, которые миграция выполняет через
     * execFile(). Иначе правка SQL-файла осталась бы незамеченной, хотя
     * применённая схема разошлась бы с тем, что лежит в репозитории.
     *
     * @param string $path Полный путь к файлу миграции.
     * @return string
     */
    private function getChecksum($path)
    {
        $files = glob(dirname($path) . '/' . basename($path, '.php') . '.*');
        if ($files === false) {
            return '';
        }

        sort($files, SORT_STRING);

        $parts = '';
        foreach ($files as $file) {
            $hash = md5_file($file);
            $parts .= $hash === false ? '' : $hash;
        }

        return $parts === '' ? '' : md5($parts);
    }

    /**
     * Журнал миграций.
     * @return DbMigrationLog[] Записи, ключ — имя миграции; пусто, если
     * журнала ещё нет.
     */
    private function loadLogs()
    {
        if (!self::tableExists($this->_db, $this->_db->prefix . LPMTables::DB_MIGRATIONS)) {
            return [];
        }

        return DbMigrationLog::loadAll();
    }

    /**
     * Миграции, которые нужно отметить применёнными без выполнения.
     * @param DbMigrationState[] $states Состояние всех миграций.
     * @return string[] Имена миграций.
     */
    private function getBaselineCandidates(array $states)
    {
        // Журнал уже ведётся — все решения приняты в предыдущих запусках.
        foreach ($states as $state) {
            if ($state->log !== null) {
                return [];
            }
        }

        // Пустая база: схему целиком создаст первая же миграция.
        if (!self::tableExists($this->_db, $this->_db->prefix . LPMTables::ISSUES)) {
            return [];
        }

        $names = [];
        foreach ($states as $state) {
            if (strcmp($state->name, self::BASELINE) <= 0) {
                $names[] = $state->name;
            }
        }

        return $names;
    }

    /**
     * Отмечает применёнными миграции, вошедшие в схему до перехода на миграции.
     * @return string[] Имена отмеченных миграций.
     */
    private function applyBaseline()
    {
        $states = $this->getStatus();
        $names = $this->getBaselineCandidates($states);
        if (empty($names)) {
            return [];
        }

        foreach ($states as $state) {
            if (in_array($state->name, $names, true)) {
                DbMigrationLog::markBaseline($state->name, $state->checksum, $this->_userId);
            }
        }

        LPMLog::info(
            'Схема существующей установки принята за исходную',
            LPMLog::CH_DB,
            ['names' => $names]
        );

        return $names;
    }

    /**
     * Применяет одну миграцию, записывая результат в журнал.
     * @param DbMigrationState $state Состояние миграции.
     * @return array `name`, `ok`, `error`, `execTime`.
     */
    private function runUp(DbMigrationState $state)
    {
        DbMigrationLog::start($state->name, $state->checksum, $this->_userId);

        $start = microtime(true);
        try {
            $this->loadMigration($state)->up();
        } catch (\Throwable $e) {
            $execTime = $this->getElapsed($start);
            DbMigrationLog::fail($state->name, $e->getMessage(), $execTime);
            LPMLog::error(
                'Ошибка применения миграции ' . $state->name,
                LPMLog::CH_DB,
                ['error' => $e->getMessage(), 'execTime' => $execTime]
            );

            return $this->result($state->name, false, $e->getMessage(), $execTime);
        }

        $execTime = $this->getElapsed($start);
        DbMigrationLog::finish($state->name, $execTime);
        LPMLog::info(
            'Применена миграция ' . $state->name,
            LPMLog::CH_DB,
            ['execTime' => $execTime, 'userId' => $this->_userId]
        );

        return $this->result($state->name, true, '', $execTime);
    }

    /**
     * Откатывает одну миграцию, удаляя её из журнала.
     * @param DbMigrationState $state Состояние миграции.
     * @return array `name`, `ok`, `error`, `execTime`.
     */
    private function runDown(DbMigrationState $state)
    {
        if ($state->isBaseline()) {
            return $this->result(
                $state->name,
                false,
                'миграция отмечена применённой без выполнения — откатывать нечего',
                0
            );
        }

        $start = microtime(true);
        try {
            $this->loadMigration($state)->down();
        } catch (\Throwable $e) {
            $execTime = $this->getElapsed($start);
            LPMLog::error(
                'Ошибка отката миграции ' . $state->name,
                LPMLog::CH_DB,
                ['error' => $e->getMessage(), 'execTime' => $execTime]
            );

            return $this->result($state->name, false, $e->getMessage(), $execTime);
        }

        $execTime = $this->getElapsed($start);
        DbMigrationLog::remove($state->name);
        LPMLog::info(
            'Откачена миграция ' . $state->name,
            LPMLog::CH_DB,
            ['execTime' => $execTime, 'userId' => $this->_userId]
        );

        return $this->result($state->name, true, '', $execTime);
    }

    /**
     * Загружает миграцию из файла.
     * @param DbMigrationState $state Состояние миграции.
     * @return DbMigration
     * @throws DbMigrationException Если файл не возвращает миграцию.
     */
    private function loadMigration(DbMigrationState $state)
    {
        $migration = require $state->path;

        if (!($migration instanceof DbMigration)) {
            throw new DbMigrationException(
                'файл миграции должен возвращать наследника DbMigration'
                . ' (return new class extends DbMigration {...};)'
            );
        }

        $migration->bind($this->_db, dirname($state->path));

        return $migration;
    }

    /**
     * Захватывает блокировку на время работы с миграциями.
     *
     * Блокировка живёт в СУБД и привязана к соединению, поэтому освобождается
     * сама, если процесс завершится аварийно.
     *
     * @throws DbMigrationException Если блокировку удерживает другой процесс.
     */
    private function lock()
    {
        $res = $this->_db->query("SELECT GET_LOCK('" . $this->getLockName() . "', 0) AS `locked`");
        $row = $res === false ? null : $res->fetch_assoc();
        if ($res !== false) {
            $res->free();
        }

        if ($row === null || (int)$row['locked'] !== 1) {
            throw new DbMigrationException(
                'миграции уже выполняются другим процессом — попробуйте позже'
            );
        }

        $this->_locked = true;
    }

    /**
     * Освобождает блокировку.
     */
    private function unlock()
    {
        if (!$this->_locked) {
            return;
        }

        $res = $this->_db->query("SELECT RELEASE_LOCK('" . $this->getLockName() . "')");
        if ($res !== false) {
            $res->free();
        }

        $this->_locked = false;
    }

    /**
     * Имя блокировки — своё для каждой базы на сервере.
     * @return string
     */
    private function getLockName()
    {
        return 'lpm_migrate_' . md5(DB_NAME . $this->_db->prefix);
    }

    /**
     * Время, прошедшее с отметки, в миллисекундах.
     * @param float $start Отметка microtime(true).
     * @return int
     */
    private function getElapsed($start)
    {
        return (int)round((microtime(true) - $start) * 1000);
    }

    /**
     * @param string $name Имя миграции.
     * @param bool $ok Успешно ли выполнена.
     * @param string $error Текст ошибки.
     * @param int $execTime Длительность выполнения, мс.
     * @return array
     */
    private function result($name, $ok, $error, $execTime)
    {
        return ['name' => $name, 'ok' => $ok, 'error' => $error, 'execTime' => $execTime];
    }

    /**
     * Содержимое заготовки миграции.
     * @return string
     */
    private function getStubContent()
    {
        return <<<'PHP'
<?php
/**
 * TODO: что меняет миграция и зачем.
 */
return new class extends DbMigration {
    public function up()
    {
        $this->exec("");
    }

    public function down()
    {
        $this->exec("");
    }
};

PHP;
    }
}
