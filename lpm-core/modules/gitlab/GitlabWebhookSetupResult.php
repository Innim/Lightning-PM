<?php
/**
 * Результат настройки вебхука Lightning PM на одном репозитории GitLab.
 */
class GitlabWebhookSetupResult
{
    /** Хук был создан заново. */
    const STATUS_CREATED = 'created';
    /** Хук с нашим адресом уже был на репозитории и приведен к нужному виду. */
    const STATUS_UPDATED = 'updated';
    /** Настроить хук не удалось, причина - в `$message`. */
    const STATUS_FAILED = 'failed';

    /**
     * Идентификатор репозитория на GitLab.
     * @var int
     */
    public $repositoryId;

    /**
     * Чем закончилась настройка - одна из констант `STATUS_*`.
     * @var string
     */
    public $status;

    /**
     * Причина неудачи в формулировке для администратора.
     *
     * Пустая строка, если настройка прошла успешно.
     * @var string
     */
    public $message;

    public function __construct($repositoryId, $status, $message = '')
    {
        $this->repositoryId = (int)$repositoryId;
        $this->status = $status;
        $this->message = (string)$message;
    }

    /**
     * Удалось ли настроить хук.
     * @return bool
     */
    public function isOk()
    {
        return $this->status !== self::STATUS_FAILED;
    }
}
