<?php
/**
 * Состояние сборки в терминах задачи.
 *
 * Статусов пайплайна у GitLab больше десятка (см. GitlabPipeline::STATUS_*),
 * но для задачи важен один вопрос: можно ли её брать в тест. Поэтому статусы
 * сворачиваются в три состояния, и в них же выражается сводное состояние
 * задачи по всем её сборкам.
 */
class IssuePipelineStatus
{
    /**
     * Сборка прошла.
     */
    const SUCCESS = 'success';

    /**
     * Сборка ещё идёт или ждёт запуска.
     */
    const RUNNING = 'running';

    /**
     * Сборка не прошла.
     */
    const FAILED = 'failed';

    /**
     * Статусы GitLab, при которых сборка считается не пройденной.
     *
     * Отменённая сборка попадает сюда же: её результата нет, значит брать
     * задачу в тест по ней нельзя.
     */
    private static $failedStatuses = [
        GitlabPipeline::STATUS_FAILED,
        GitlabPipeline::STATUS_CANCELED,
    ];

    /**
     * Статусы GitLab, при которых сборка считается пройденной.
     *
     * Пропущенная сборка считается пройденной: ни одна джоба не запускалась,
     * значит ничего и не сломалось.
     */
    private static $successStatuses = [
        GitlabPipeline::STATUS_SUCCESS,
        GitlabPipeline::STATUS_SKIPPED,
    ];

    /**
     * Сворачивает статус пайплайна GitLab в состояние сборки.
     *
     * @param  string $gitlabStatus Статус пайплайна (см. GitlabPipeline::STATUS_*).
     * @return string|null Одна из констант класса или null, если статус пустой.
     */
    public static function fromGitlabStatus($gitlabStatus)
    {
        if (empty($gitlabStatus)) {
            return null;
        }

        if (in_array($gitlabStatus, self::$failedStatuses)) {
            return self::FAILED;
        }

        if (in_array($gitlabStatus, self::$successStatuses)) {
            return self::SUCCESS;
        }

        // Всё остальное - ожидание, подготовка, ручной запуск и сама сборка:
        // результата ещё нет
        return self::RUNNING;
    }

    /**
     * Определяет, завершена ли сборка с таким статусом.
     *
     * Сборка, ждущая ручного запуска, завершённой не считается: её результата
     * ещё нет.
     *
     * @param  string $gitlabStatus Статус пайплайна (см. GitlabPipeline::STATUS_*).
     * @return bool
     */
    public static function isFinal($gitlabStatus)
    {
        $state = self::fromGitlabStatus($gitlabStatus);

        return $state !== null && $state !== self::RUNNING;
    }

    /**
     * Сводит состояния нескольких сборок в одно.
     *
     * Провал важнее идущей сборки, идущая сборка важнее успеха: сводный
     * успех получается, только когда успешны все сборки.
     *
     * @param  array<string> $statuses Статусы пайплайнов GitLab.
     * @return string|null Одна из констант класса или null, если ни по одной
     * сборке состояние неизвестно.
     */
    public static function summary(array $statuses)
    {
        $res = null;
        foreach ($statuses as $gitlabStatus) {
            $state = self::fromGitlabStatus($gitlabStatus);
            if ($state === null) {
                continue;
            }

            if ($state === self::FAILED) {
                return self::FAILED;
            }

            if ($state === self::RUNNING || $res === null) {
                $res = $state;
            }
        }

        return $res;
    }
}
