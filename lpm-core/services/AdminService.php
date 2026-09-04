<?php
require_once __DIR__ . '/../init.inc.php';

/**
 * Сервис, предоставляющий API для администрирования.
 * 
 * Только пользователи с ролью `User::ROLE_ADMIN`
 * могут делать запросы к этому сервису.
 */
class AdminService extends LPMBaseService
{
    /**
     * Инициирует сброс кэша.
     * @return
     */
    public function flushCache()
    {
        $cache = $this->cache();
        $cache->flush();

        return $this->answer();
    }

    /**
     * Сохраняет настройки приложения.
     *
     * Принимает ассоциативный массив `имя_опции => значение`.
     * Неизвестные опции отклоняются с ошибкой.
     *
     * Текст оформления задач, совпадающий с умолчанием, сохраняется
     * как пустая настройка — то есть остаётся умолчанием.
     *
     * @param array $data
     * @return
     */
    public function saveSettings($data)
    {
        $data = (array)$data;

        $values = [];
        foreach ($data as $field => $value) {
            switch ($field) {
                case 'allowRegistration':
                case 'newIssueView':
                    $values[$field] = (bool)$value;
                    break;
                case 'title':
                case 'subtitle':
                case 'fromName':
                case 'emailSubscript':
                    $values[$field] = trim((string)$value);
                    break;
                case 'issueDescTemplate':
                case 'issueGuidelines':
                    // Переводы строк значимы: это многострочный текст,
                    // а не однострочная настройка.
                    $text = str_replace("\r\n", "\n", (string)$value);
                    if (mb_strlen($text) > ISSUE_GUIDELINES_MAX_LENGTH) {
                        // Обе настройки делят предел длины, но в ошибке должна быть
                        // названа та, которую пользователь превысил.
                        $tooLong = [
                            'issueDescTemplate' => 'Шаблон описания задачи не должен быть длиннее ',
                            'issueGuidelines' => 'Правила оформления задачи не должны быть длиннее ',
                        ];
                        return $this->error($tooLong[$field]
                            . ISSUE_GUIDELINES_MAX_LENGTH . ' символов');
                    }
                    // Текст, совпадающий с умолчанием, хранить не нужно: настройка
                    // остаётся пустой, и умолчание продолжает обновляться с версией.
                    $values[$field] = LPMOptions::isIssueTextDefault($field, $text) ? '' : $text;
                    break;
                case 'fromEmail':
                    $email = trim((string)$value);
                    if ($email !== '' && !Validation::checkEmail($email)) {
                        return $this->error('Некорректный email отправителя');
                    }
                    $values[$field] = $email;
                    break;
                case 'cookieExpire':
                    $days = (int)$value;
                    if ($days < 1) {
                        return $this->error('Срок действия сессии должен быть не менее 1 дня');
                    }
                    $values[$field] = $days;
                    break;
                default:
                    return $this->error('Недопустимая настройка: ' . $field);
            }
        }

        if (empty($values)) {
            return $this->error('Не переданы настройки для сохранения');
        }

        try {
            LPMOptions::save($values);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Применяет неприменённые миграции схемы БД.
     *
     * Выполнение прекращается на первой ошибке; применённые до неё миграции
     * остаются применёнными.
     *
     * @return
     */
    public function applyDbMigrations()
    {
        // Изменение схемы может идти минутами — лимит времени PHP тут не помощник.
        set_time_limit(0);

        try {
            $report = (new DbMigrator($this->getUserId()))->apply();
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        foreach ($report['results'] as $result) {
            if (!$result['ok']) {
                return $this->error(
                    'Не удалось применить ' . $result['name'] . ': ' . $result['error']
                );
            }
        }

        $this->add2Answer('applied', count($report['results']));
        $this->add2Answer('baseline', count($report['baseline']));

        return $this->answer();
    }

    /**
     * Настраивает вебхуки Lightning PM во всех репозиториях,
     * с которыми таск уже работал.
     *
     * Повторный вызов не создает дублей: хук с нашим адресом обновляется.
     * Неудача на одном репозитории не останавливает остальные - в ответе
     * возвращается список проблемных репозиториев с причинами.
     *
     * Отказывает целиком, если адрес хука локальный: репозитории у стендов
     * общие с боевыми.
     *
     * @return stdClass Ответ с полями `hookUrl`, `total`, `succeeded`
     * и `failed` (список `{repositoryId, message}`).
     */
    public function setupGitlabWebhooks()
    {
        // Обход десятков репозиториев - это столько же запросов к GitLab,
        // в лимит времени по умолчанию это не укладывается
        set_time_limit(0);

        $gitlab = LightningEngine::getInstance()->gitlab();
        if (!$gitlab->isAvailable()) {
            return $this->error('Интеграция с GitLab не настроена');
        }

        if (GitlabWebhookManager::isHookUrlLocal()) {
            return $this->error('Настройка недоступна: ' . GitlabWebhookManager::LOCAL_URL_REFUSAL);
        }

        try {
            $repositoryIds = IssueBranch::loadUsedRepositoryIds();
            $results = (new GitlabWebhookManager($gitlab))->setupForRepositories($repositoryIds);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        $failed = [];
        $succeeded = 0;
        foreach ($results as $result) {
            if ($result->isOk()) {
                $succeeded++;
            } else {
                $failed[] = [
                    'repositoryId' => $result->repositoryId,
                    'message' => $result->message,
                ];
            }
        }

        $this->add2Answer('hookUrl', GitlabWebhookManager::getHookUrl());
        $this->add2Answer('total', count($results));
        $this->add2Answer('succeeded', $succeeded);
        $this->add2Answer('failed', $failed);

        return $this->answer();
    }

    public function beforeFilter($calledFunc)
    {
        return parent::beforeFilter($calledFunc) && $this->checkRole(User::ROLE_ADMIN);
    }
}
