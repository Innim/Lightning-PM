<?php
/**
 * Настройка вебхуков Lightning PM в репозиториях GitLab.
 *
 * Заводит и обновляет ровно один хук на репозиторий - тот, что подписан на
 * триггер Pipeline events. Влития и пуши (Merge request events, Push events)
 * приносит другой хук, системный хук инстанса; он заводится вручную и этим
 * классом не управляется - полная инструкция по нему в `GitlabExternalApi`.
 *
 * Системные хуки события пайплайнов не отдают вообще - потому для них и нужен
 * отдельный хук на репозитории. Наборы триггеров у двух хуков не пересекаются
 * намеренно: иначе одно событие приходило бы дважды, двумя разными хуками.
 *
 * Свой хук определяется по адресу: тот, чей url совпадает с `getHookUrl()`.
 * Хуки с другими адресами не читаются как свои и не изменяются.
 */
class GitlabWebhookManager
{
    /**
     * Набор событий, на которые подписывается хук таска: только Pipeline events.
     *
     * Перечислены и выключенные флаги: GitLab заменяет настройки хука целиком,
     * поэтому непереданный флаг стал бы `false` молча.
     */
    const HOOK_EVENTS = [
        'push_events' => false,
        'merge_requests_events' => false,
        'pipeline_events' => true,
    ];

    /** Причина отказа при локальном адресе хука - в формулировке для администратора. */
    const LOCAL_URL_REFUSAL = 'адрес хука виден только с этой машины,'
        . ' настройка перезаписала бы рабочий хук боевого репозитория';

    /**
     * Включена ли автоматическая настройка хука при создании ветки.
     *
     * По умолчанию выключена: адрес хука берется из `SITE_URL`, поэтому
     * включенный флаг на дев-стенде перепишет хуки в тех же самых боевых
     * репозиториях на адрес, до которого GitLab не достучится.
     *
     * @return bool
     */
    public static function isAutoSetupEnabled()
    {
        return defined('GITLAB_AUTO_SETUP_WEBHOOKS') && GITLAB_AUTO_SETUP_WEBHOOKS;
    }

    /**
     * Адрес, на который GitLab должен слать события.
     * @return string
     */
    public static function getHookUrl()
    {
        return Link::getUrl(LightningEngine::API_PATH, [GitlabExternalApi::UID]) . '/';
    }

    /**
     * Виден ли адрес хука только внутри этой машины.
     *
     * @return bool
     */
    public static function isHookUrlLocal()
    {
        // Репозитории у дев-стендов общие с боевыми, поэтому локальный адрес
        // в хуке обрывает доставку событий всем
        $host = strtolower((string)parse_url(self::getHookUrl(), PHP_URL_HOST));

        return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1'
            || substr($host, -6) === '.local';
    }

    /**
     * Настраивает хук на репозитории, если автонастройка включена.
     *
     * Вызывается по ходу пользовательского сценария (создание ветки), поэтому
     * не бросает исключений и ничего не возвращает: неудавшаяся настройка хука
     * уходит в лог, а сценарий продолжается.
     *
     * С локальным адресом хука не делает ничего, даже при включенном флаге:
     * репозитории у стендов общие с боевыми, и запись перевела бы боевой хук
     * на адрес, до которого GitLab не достучится.
     *
     * @param GitlabIntegration $gitlab       Настроенная интеграция.
     * @param int|string        $repositoryId Идентификатор проекта на GitLab.
     */
    public static function autoSetupForRepository(GitlabIntegration $gitlab, $repositoryId)
    {
        if (!self::isAutoSetupEnabled()) {
            return;
        }

        if (self::isHookUrlLocal()) {
            LPMLog::warning(
                'Автонастройка вебхука пропущена: ' . self::LOCAL_URL_REFUSAL,
                LPMLog::CH_GITLAB,
                ['repositoryId' => $repositoryId]
            );
            return;
        }

        try {
            $result = (new self($gitlab))->ensureForRepository($repositoryId);
        } catch (Exception $e) {
            LPMLog::exception($e, LPMLog::CH_GITLAB, [
                'context' => 'Автонастройка вебхука',
                'repositoryId' => $repositoryId,
            ]);
            return;
        }

        if (!$result->isOk()) {
            LPMLog::error(
                'Не удалось настроить вебхук: ' . $result->message,
                LPMLog::CH_GITLAB,
                ['repositoryId' => $repositoryId]
            );
        }
    }

    /**
     * @var GitlabIntegration
     */
    private $_gitlab;

    public function __construct(GitlabIntegration $gitlab)
    {
        $this->_gitlab = $gitlab;
    }

    /**
     * Настраивает хук на каждом из указанных репозиториев.
     *
     * Неудача на одном репозитории не прекращает обработку остальных.
     *
     * @param  array<int> $repositoryIds Идентификаторы проектов на GitLab.
     * @return array<GitlabWebhookSetupResult> Результаты в порядке входного списка.
     */
    public function setupForRepositories(array $repositoryIds)
    {
        $results = [];
        foreach ($repositoryIds as $repositoryId) {
            $results[] = $this->ensureForRepository($repositoryId);
        }

        return $results;
    }

    /**
     * Приводит хук таска на репозитории к нужному виду.
     *
     * Если хука с нашим адресом еще нет - создает, если есть - обновляет,
     * так что повторный вызов не плодит дубли. Секретный токен перезаписывается
     * всегда: GitLab не отдает его обратно, и сверить нечего.
     *
     * Пишет в GitLab без оглядки на окружение - проверку `isHookUrlLocal()`
     * делает вызывающий, как и проверку `isAutoSetupEnabled()`.
     *
     * @param  int|string $repositoryId Идентификатор проекта на GitLab.
     * @return GitlabWebhookSetupResult
     */
    public function ensureForRepository($repositoryId)
    {
        if (!$this->_gitlab->isAvailable()) {
            return $this->fail($repositoryId, 'интеграция с GitLab не настроена');
        }

        // Хук без секрета бесполезен: обработчик отклоняет вызовы,
        // пока GITLAB_HOOK_TOKEN не задан
        $token = defined('GITLAB_HOOK_TOKEN') ? (string)GITLAB_HOOK_TOKEN : '';
        if ($token === '') {
            return $this->fail($repositoryId, 'не задан GITLAB_HOOK_TOKEN');
        }

        $url = self::getHookUrl();

        try {
            $existing = $this->findOwnHook($repositoryId, $url);
            $params = self::HOOK_EVENTS;
            $params['token'] = $token;

            if (empty($existing)) {
                $this->_gitlab->sudoAddProjectHook($repositoryId, $url, $params);
                $status = GitlabWebhookSetupResult::STATUS_CREATED;
            } else {
                $params['url'] = $url;
                $this->_gitlab->sudoUpdateProjectHook($repositoryId, $existing['id'], $params);
                $status = GitlabWebhookSetupResult::STATUS_UPDATED;
            }
        } catch (Exception $e) {
            LPMLog::exception($e, LPMLog::CH_GITLAB, [
                'context' => 'Не удалось настроить вебхук',
                'repositoryId' => $repositoryId,
            ]);

            return $this->fail($repositoryId, $this->describeError($e));
        }

        return new GitlabWebhookSetupResult($repositoryId, $status);
    }

    /**
     * Возвращает хук таска среди хуков репозитория.
     *
     * @param  int|string $repositoryId Идентификатор проекта на GitLab.
     * @param  string     $url          Наш адрес хука.
     * @return array|null Данные хука или null, если его еще нет.
     * @throws Exception Если список хуков получить не удалось.
     */
    private function findOwnHook($repositoryId, $url)
    {
        $hooks = $this->_gitlab->sudoGetProjectHooks($repositoryId);
        if (empty($hooks)) {
            return null;
        }

        // Хук мог быть заведен руками без завершающего слэша - для GitLab
        // это тот же адрес, и вторым хуком его дублировать не надо
        $ownUrl = rtrim($url, '/');
        foreach ($hooks as $hook) {
            if (isset($hook['url']) && rtrim($hook['url'], '/') === $ownUrl) {
                return $hook;
            }
        }

        return null;
    }

    /**
     * Переводит ошибку GitLab в формулировку для администратора.
     * @param  Exception $e
     * @return string
     */
    private function describeError(Exception $e)
    {
        switch ((int)$e->getCode()) {
            case 401:
                return 'GitLab не принял токен интеграции - он недействителен или истёк';
            case 403:
                return 'недостаточно прав: управление вебхуками требует роли'
                    . ' Maintainer или Owner на репозитории';
            case 404:
                return 'репозиторий не найден или недоступен';
            default:
                return $e->getMessage();
        }
    }

    /**
     * @return GitlabWebhookSetupResult
     */
    private function fail($repositoryId, $message)
    {
        return new GitlabWebhookSetupResult(
            $repositoryId,
            GitlabWebhookSetupResult::STATUS_FAILED,
            $message
        );
    }
}
