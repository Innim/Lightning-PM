<?php
/**
 * Запись журнала миграций схемы БД — одна на файл миграции.
 *
 * Журнал ведётся по именам файлов, а не по номеру версии: миграции из разных
 * веток попадают в общую историю в произвольном порядке, и применить нужно
 * все недостающие, а не те, что новее последней применённой.
 *
 * Запись создаётся до выполнения миграции со статусом STATUS_RUNNING и
 * обновляется после. Поэтому прерванный запуск (таймаут, обрыв соединения)
 * остаётся видимым в журнале, а не пропадает бесследно.
 */
class DbMigrationLog extends LPMBaseObject
{
    /** Миграция выполняется прямо сейчас либо запуск был прерван */
    const STATUS_RUNNING = 'running';

    /** Миграция применена успешно */
    const STATUS_DONE = 'done';

    /** Миграция завершилась ошибкой; схема может быть изменена частично */
    const STATUS_FAILED = 'failed';

    /**
     * Создаёт таблицу журнала, если её ещё нет.
     *
     * Журнал не может быть заведён миграцией — он нужен раньше, чем станет
     * известно, какие миграции применены, — поэтому создаётся запросом.
     *
     * @param DBConnect $db Соединение с БД.
     * @throws DbMigrationException Если таблицу не удалось создать.
     */
    public static function createTable($db)
    {
        $table = $db->prefix . LPMTables::DB_MIGRATIONS;
        $sql = "CREATE TABLE IF NOT EXISTS `" . $table . "` (
            `name` varchar(190) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
                COMMENT 'Имя файла миграции без расширения',
            `checksum` char(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
                COMMENT 'Контрольная сумма файла миграции на момент применения',
            `status` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
                COMMENT 'Состояние: running, done, failed',
            `baseline` tinyint(1) NOT NULL DEFAULT '0'
                COMMENT 'Отмечена применённой без выполнения',
            `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
                COMMENT 'Ошибка последней неудачной попытки',
            `execTime` int NOT NULL DEFAULT '0'
                COMMENT 'Длительность выполнения, мс',
            `userId` int NOT NULL DEFAULT '0'
                COMMENT 'Кто запустил миграцию (0 — CLI)',
            `appliedAt` datetime NOT NULL
                COMMENT 'Дата последней попытки применения',
            PRIMARY KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Журнал миграций схемы БД'";

        if ($db->query($sql) === false) {
            throw new DbMigrationException('не удалось создать журнал миграций: ' . $db->error);
        }
    }

    /**
     * Загружает весь журнал.
     * @return DbMigrationLog[] Записи, ключ — имя миграции.
     */
    public static function loadAll()
    {
        $list = self::loadAndParseV2([
            'SELECT' => '*',
            'FROM' => LPMTables::DB_MIGRATIONS,
            'ORDER BY' => '`name` ASC',
        ], __CLASS__);

        $result = [];
        foreach ($list as $item) {
            $result[$item->name] = $item;
        }

        return $result;
    }

    /**
     * Отмечает начало применения миграции.
     *
     * Повторный запуск после неудачи перезаписывает прежнюю запись, поэтому
     * в журнале остаётся результат последней попытки.
     *
     * @param string $name Имя миграции.
     * @param string $checksum Контрольная сумма файла миграции.
     * @param int $userId Кто запустил (0 — CLI).
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    public static function start($name, $checksum, $userId = 0)
    {
        self::buildAndSaveToDbV2([
            'INSERT' => [
                'name' => (string)$name,
                'checksum' => (string)$checksum,
                'status' => self::STATUS_RUNNING,
                'baseline' => 0,
                'error' => '',
                'execTime' => 0,
                'userId' => (int)$userId,
                'appliedAt' => DateTimeUtils::mysqlDate(),
            ],
            'INTO' => LPMTables::DB_MIGRATIONS,
            'ODKU' => ['checksum', 'status', 'baseline', 'error', 'execTime', 'userId', 'appliedAt'],
        ]);
    }

    /**
     * Отмечает успешное применение миграции.
     * @param string $name Имя миграции.
     * @param int $execTime Длительность выполнения, мс.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    public static function finish($name, $execTime)
    {
        self::updateStatus($name, self::STATUS_DONE, '', $execTime);
    }

    /**
     * Отмечает неудачное применение миграции.
     * @param string $name Имя миграции.
     * @param string $error Текст ошибки.
     * @param int $execTime Длительность до ошибки, мс.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    public static function fail($name, $error, $execTime)
    {
        self::updateStatus($name, self::STATUS_FAILED, $error, $execTime);
    }

    /**
     * Отмечает миграцию применённой, не выполняя её.
     *
     * Нужно для установок, где изменения схемы уже сделаны — вручную или
     * дампом, — чтобы миграция не применилась повторно.
     *
     * @param string $name Имя миграции.
     * @param string $checksum Контрольная сумма файла миграции.
     * @param int $userId Кто отметил (0 — CLI).
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    public static function markBaseline($name, $checksum, $userId = 0)
    {
        self::buildAndSaveToDbV2([
            'INSERT' => [
                'name' => (string)$name,
                'checksum' => (string)$checksum,
                'status' => self::STATUS_DONE,
                'baseline' => 1,
                'error' => '',
                'execTime' => 0,
                'userId' => (int)$userId,
                'appliedAt' => DateTimeUtils::mysqlDate(),
            ],
            'INTO' => LPMTables::DB_MIGRATIONS,
            'ODKU' => ['checksum', 'status', 'baseline', 'error', 'execTime', 'userId', 'appliedAt'],
        ]);
    }

    /**
     * Удаляет запись о миграции — после успешного отката.
     * @param string $name Имя миграции.
     * @throws \GMFramework\ProviderSaveException Если не удалось изменить данные.
     */
    public static function remove($name)
    {
        self::buildAndSaveToDbV2([
            'DELETE' => LPMTables::DB_MIGRATIONS,
            'WHERE' => ['name' => (string)$name],
        ]);
    }

    /**
     * @param string $name Имя миграции.
     * @param string $status Новое состояние.
     * @param string $error Текст ошибки.
     * @param int $execTime Длительность выполнения, мс.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    private static function updateStatus($name, $status, $error, $execTime)
    {
        self::buildAndSaveToDbV2([
            'UPDATE' => LPMTables::DB_MIGRATIONS,
            'SET' => [
                'status' => $status,
                'error' => (string)$error,
                'execTime' => (int)$execTime,
                'appliedAt' => DateTimeUtils::mysqlDate(),
            ],
            'WHERE' => ['name' => (string)$name],
        ]);
    }

    /**
     * Имя файла миграции без расширения.
     * @var string
     */
    public $name = '';

    /**
     * Контрольная сумма файла миграции на момент применения.
     * @var string
     */
    public $checksum = '';

    /**
     * Состояние миграции — одна из констант STATUS_*.
     * @var string
     */
    public $status = '';

    /**
     * Миграция отмечена применённой без выполнения.
     * @var bool
     */
    public $baseline = false;

    /**
     * Ошибка последней неудачной попытки.
     * @var string
     */
    public $error = '';

    /**
     * Длительность выполнения, мс.
     * @var int
     */
    public $execTime = 0;

    /**
     * Кто запустил миграцию (0 — CLI).
     * @var int
     */
    public $userId = 0;

    /**
     * Дата последней попытки применения.
     * @var float
     */
    public $appliedAt = 0;

    public function __construct($raw = null)
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('execTime', 'userId');
        $this->_typeConverter->addBoolVars('baseline');
        $this->addDateTimeFields('appliedAt');

        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }

    /**
     * Применена ли миграция успешно.
     * @return bool
     */
    public function isApplied()
    {
        return $this->status === self::STATUS_DONE;
    }

    /**
     * Дата последней попытки применения в формате для показа.
     * @return string
     */
    public function getAppliedAtStr()
    {
        return self::getDateTimeStr($this->appliedAt);
    }
}
