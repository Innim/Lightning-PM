<?php
/**
 * Внешнее API для обработки hook событий от GitLab.
 *
 * Для подключения надо вручную добавить нужный хук в Admin Area > System Hooks.
 *
 * URL: https://task_url/api/gitlab/
 * Secret Token: То, что передается в конструктор этого класса присоздании.
 * Trigger: Merge request events.
 *
 * // TODO: сделать автоматическое создание хука
 */
class GitlabExternalApi extends ExternalApi
{
    const UID = 'gitlab';

    const FIELD_OBJECT_KIND = 'object_kind';
    const FIELD_OBJECT_ATTRIBUTES = 'object_attributes';
    const FIELD_USER = 'user';
    const EVENT_TYPE_MR = 'merge_request';

    private $_token;

    public function __construct(LightningEngine $engine, $token)
    {
        parent::__construct($engine, self::UID);

        $this->_token = $token;
    }

    public function run($input)
    {
        try {
            if (!$this->checkToken()) {
                throw new Exception('Token validation failed');
            }

            $data = json_decode($input, true);
            if (!$data) {
                throw new Exception("Can't parse input");
            }

            if (empty($data['event_type'])) {
                throw new Exception("Can't find event_type field");
            }

            $event = $data['event_type'];
            switch ($event) {
                case self::EVENT_TYPE_MR:
                    return $this->onMREvent($data);
                default:
                    throw new Exception("Unhandled event " . $event);
            }
        } catch (Exception $e) {
            return $this->onException($e);
        }
    }

    private function checkToken()
    {
        if (empty($this->_token)) {
            return true;
        }

        return !empty($_SERVER['HTTP_X_GITLAB_TOKEN']) &&
            $_SERVER['HTTP_X_GITLAB_TOKEN'] == $this->_token;
    }

    private function onException(Exception $e)
    {
        // TODO: лог
        // TODO: формат ошибки
        return $e->getMessage();
    }

    private function onMREvent($data)
    {
        if (!isset($data[self::FIELD_OBJECT_KIND], $data[self::FIELD_OBJECT_ATTRIBUTES])) {
            throw new Exception("Invalid data: there is no object data");
        }

        if ($data[self::FIELD_OBJECT_KIND] != 'merge_request') {
            throw new Exception("Invalid object kind: " . $data[self::FIELD_OBJECT_KIND]);
        }

        // $this->log(json_encode($data));
        $mr = new GitlabMergeRequest($data[self::FIELD_OBJECT_ATTRIBUTES]);

        // Если MR был влит, то возможно надо оповестить тестировщика
        if ($mr->isMerged()) {
            // Загружаем задачи по MR
            $issueIds = IssueMR::loadIssueIdsForOpenedMr($mr->id);
            if (!empty($issueIds)) {
                $slack = SlackIntegration::getInstance();
                foreach ($issueIds as $issueId) {
                    $issue = Issue::load($issueId);
                    if (empty($issueId)) {
                        continue;
                    }

                    if ($issue->status == Issue::STATUS_WAIT) {
                        // Если задача в тесте - оповещаем тестера что MR влит
                        // (это MR c правками)
                        $testers = $issue->getTesters();
                        if (!empty($testers)) {
                            $slack->notifyMRMergedToTester($issue, $mr);
                        }
                    } elseif ($issue->status == Issue::STATUS_IN_WORK) {
                        // Если задача в работе, то вполне возможно надо перевесить ее в тест,
                        // но предварительно надо убедиться, что все MR задачи влиты
                        if (!IssueMR::existOpenedMrForIssue($issueId, $mr->id)) {
                            // Перевешиваем задачу в тест
                            // TODO: может перевешивать только то, что сейчас на доске в работе?
                            Issue::setStatus($issue, Issue::STATUS_WAIT, null);
                        }
                    }
                }
            }
        }

        // Обновляем статус MR
        IssueMR::updateState($mr->id, $mr->state);
    }

    private function getUser($data)
    {
        $userData = $data[self::FIELD_USER];
        if (!empty($userData) && !empty($userData['email'])) {
            $user = User::loadByEmail($userData['email']);

            if ($user != null && !empty($user->gitlabToken)) {
                return $user;
            }
        }

        return null;
    }

    private function log($message)
    {
        $fileName = DateTimeUtils::mysqlDate(null, false) . '-' .
            DateTimeUtils::date('H-i-s') . '.log';
        file_put_contents(LOGS_PATH . '/api/gitlab/' . $fileName, $message);
    }
}
