<?php
/**
 * Состояние одной миграции: файл на диске и запись о нём в журнале.
 *
 * Файл без записи — миграция ещё не применялась; запись без файла здесь не
 * появляется (журнал читается по списку файлов), такие записи показывает
 * DbMigrator::getOrphanNames().
 */
class DbMigrationState
{
    /**
     * Имя миграции — имя файла без расширения.
     * @var string
     */
    public $name = '';

    /**
     * Полный путь к файлу миграции.
     * @var string
     */
    public $path = '';

    /**
     * Контрольная сумма файла миграции.
     * @var string
     */
    public $checksum = '';

    /**
     * Запись журнала или null, если миграция ещё не применялась.
     * @var DbMigrationLog|null
     */
    public $log = null;

    /**
     * @param string $name Имя миграции.
     * @param string $path Полный путь к файлу.
     * @param string $checksum Контрольная сумма файла.
     * @param DbMigrationLog|null $log Запись журнала, если миграция применялась.
     */
    public function __construct($name, $path, $checksum, $log = null)
    {
        $this->name = $name;
        $this->path = $path;
        $this->checksum = $checksum;
        $this->log = $log;
    }

    /**
     * Применена ли миграция успешно.
     * @return bool
     */
    public function isApplied()
    {
        return $this->log !== null && $this->log->isApplied();
    }

    /**
     * Ожидает ли миграция применения — не применялась либо упала.
     * @return bool
     */
    public function isPending()
    {
        return !$this->isApplied();
    }

    /**
     * Завершилась ли последняя попытка ошибкой.
     * @return bool
     */
    public function isFailed()
    {
        return $this->log !== null && $this->log->status === DbMigrationLog::STATUS_FAILED;
    }

    /**
     * Прервалось ли применение миграции, не дойдя до результата.
     *
     * Признак того, что процесс был остановлен во время выполнения: по таймауту,
     * из-за обрыва соединения или падения PHP. Схема при этом изменена частично.
     *
     * @return bool
     */
    public function isInterrupted()
    {
        return $this->log !== null && $this->log->status === DbMigrationLog::STATUS_RUNNING;
    }

    /**
     * Отмечена ли миграция применённой без выполнения.
     * @return bool
     */
    public function isBaseline()
    {
        return $this->log !== null && $this->log->baseline;
    }

    /**
     * Изменился ли файл миграции после того, как она была применена.
     *
     * Само по себе это не ошибка — правка комментария меняет контрольную сумму
     * так же, как правка запроса, — но означает, что применённая схема могла
     * разойтись с тем, что написано в файле.
     *
     * @return bool
     */
    public function isChanged()
    {
        return $this->log !== null
            && $this->log->checksum !== ''
            && $this->log->checksum !== $this->checksum;
    }

    /**
     * Ошибка последней неудачной попытки.
     * @return string
     */
    public function getError()
    {
        return $this->log === null ? '' : $this->log->error;
    }

    /**
     * Состояние миграции для показа.
     *
     * Не включает признак изменившегося файла — он относится не к состоянию
     * миграции, а к её содержимому, см. isChanged().
     *
     * @return string
     */
    public function getStatusText()
    {
        if ($this->isInterrupted()) {
            return 'Применение прервано, схема изменена частично';
        }

        if ($this->isFailed()) {
            return 'Ошибка применения';
        }

        if (!$this->isApplied()) {
            return 'Не применена';
        }

        $text = 'Применена ' . $this->log->getAppliedAtStr();
        if ($this->isBaseline()) {
            $text .= ', без выполнения';
        }

        return $text;
    }
}
