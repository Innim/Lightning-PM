<?php
/**
 * Интеграция со Slack.
 * 
 * Для работы интеграции требуется приложение со следующими scope:
 * - incoming-webhook
 * - users.profile:read
 *
 * Историю канала интеграция не читает: метку ветки обсуждения задачи она
 * хранит у себя (см. SlackIssueThread). Право на саму отправку по-прежнему
 * определяется типом токена: user-токен пишет только в каналы, где состоит
 * его владелец, а приложению, чтобы писать в публичный канал, в котором оно
 * не состоит, нужен scope chat:write.public.
 */
class SlackIntegration
{
    private static $_instance;
    /**
     * @return SlackIntegration
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            $token = defined('SLACK_TOKEN') ? SLACK_TOKEN : '';
            $notificationEnabled = $token !== ''
                && (!defined('SLACK_NOTIFICATION_ENABLED') || SLACK_NOTIFICATION_ENABLED);
            self::$_instance = new SlackIntegration($token, $notificationEnabled);
        }

        return self::$_instance;
    }

    /** Сообщение журнала о неудачной отправке оповещения. */
    private const MSG_POST_FAILED = 'Не удалось отправить сообщение в Slack';

    /** Сообщение журнала о неудачной отправке оповещения в ветку. */
    private const MSG_THREAD_POST_FAILED = 'Не удалось отправить сообщение в ветку Slack';

    private $_token;

    private $_client;
    private $_notificationEnabled = true;

    public function __construct($token, $notificationEnabled)
    {
        $this->_token = $token;
        $this->_notificationEnabled = $notificationEnabled;
    }

    /**
     * Настроена ли интеграция, т.е. задан ли токен доступа.
     * Пока интеграция не настроена, обращения к Slack API не выполняются.
     * @return bool
     */
    public function isConfigured()
    {
        return $this->_token !== '';
    }

    public function notifyIssueForTest(Issue $issue)
    {
        if (!$this->_notificationEnabled) return;
        
        $text = $this->getIssuePrefix($issue) . '_"' . $issue->name . '"_ - в *тестирование*';
        $text = $this->addMentionsByUsers($text, $issue->getTesters());

        $this->postMessageForIssue($issue, $text, [[
            'fallback' => $issue->getName(),
            'title' => $issue->getName(),
            'text' => $issue->getShortDesc(false),
            'title_link' => $issue->getConstURL()
        ]]);
    }

    public function notifyIssueCompleted(Issue $issue)
    {
        if (!$this->_notificationEnabled) return;

        $text = $this->getIssuePrefix($issue) . $issue->getConstURL() . ' - *завершена*';
        $text = $this->addMentionsByUsers($text, $issue->getMembers());

        $this->postMessageForIssue($issue, $text);
    }

    public function notifyCommentTesterToMember(Issue $issue, Comment $comment)
    {
        if (!$this->_notificationEnabled) return;

        $this->postMessageForIssueComment(
            $issue,
            $comment,
            $issue->getMembers(),
            'Тестировщик оставил комментарий'
        );
    }

    public function notifyCommentMemberToTester(Issue $issue, $comment)
    {
        if (!$this->_notificationEnabled) return;

        $this->postMessageForIssueComment(
            $issue,
            $comment,
            $issue->getTesters(),
            'Исполнитель оставил комментарий'
        );
    }

    /**
     * Оповещает упомянутых в комментарии пользователей.
     *
     * @param User[] $mentionedUsers Упомянутые пользователи.
     */
    public function notifyCommentMentioned(Issue $issue, Comment $comment, array $mentionedUsers)
    {
        if (!$this->_notificationEnabled) return;

        $slackUsers = array_filter($mentionedUsers, function ($user) {
            return !empty($user->slackName);
        });
        if (empty($slackUsers)) return;

        $this->postMessageForIssueComment(
            $issue,
            $comment,
            $slackUsers,
            'Вас упомянули в комментарии'
        );
    }

    public function notifyMRMergedToTester(Issue $issue, GitlabMergeRequest $mr)
    {
        if (!$this->_notificationEnabled) return;

        $mrTitle = 'MR !' . $mr->internalId;
        $text = $this->getIssuePrefix($issue) . $issue->getConstURL() .
            ' - *' . $mrTitle . ' влит*';
        $text = $this->addMentionsByUsers($text, $issue->getTesters());

        $this->postMessageForIssue($issue, $text, [[
            'fallback'   => $issue->getName(),
            'title'      => $mrTitle,
            'title_link' => $mr->url
        ]]);
    }

    public function notifyIssuePassTest(Issue $issue)
    {
        if (!$this->_notificationEnabled) return;

        $project = $issue->getProject();
        $masters = $issue->getMasters();
        if (empty($masters)) {
            $projectMaster = $project->getMaster();
            if ($projectMaster != null) {
                $masters = [$projectMaster];
            }
        }

        $text = $this->getIssuePrefix($issue) . $issue->getConstURL() . ' - *прошла тестирование*';
        $text = $this->addMentionsByUsers($text, $masters);

        $this->postMessageForIssue($issue, $text);
    }

    /**
     * Получает информацию о профиле пользователя в Slack.
     * 
     * @param String $memberId Идентификатор участника в Slack. Хранится в User::$slackName. 
     *                         Здесь нужно передавать именно ID, имя не подходит.
     * @return JoliCode\Slack\Api\Model\ObjsUserProfile|null null, если интеграция не настроена.
     * @throws JoliCode\Slack\Exception\SlackErrorResponse В случае ошибки в ответ на запрос.
     */
    public function getProfile(string $memberId)
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $client = $this->getClient();
        $res = $client->usersProfileGet([
            'user' => $memberId
        ]);

        return $res->getProfile();
    }

    /**
     * @return JoliCode\Slack\Client
     */
    private function getClient()
    {
        if ($this->_client == null) {
            $client = JoliCode\Slack\ClientFactory::create($this->_token);
            $this->_client = $client;
        }

        return $this->_client;
    }

    private function postMessageForIssueComment(Issue $issue, Comment $comment, $mentionUsers, $title)
    {
        $commentUrl = $comment->getIssueCommentUrl($issue);
        $text = $this->getIssuePrefix($issue) . $issue->getConstURL() . ' - *' . $title . '*';
        $text = $this->addMentionsByUsers($text, $mentionUsers);

        $this->postMessageForIssue($issue, $text, [[
            'fallback' => $issue->getName(),
            //'title' => $issue->getName(),
            'title' => $comment->author->getPlainShortName() . ' написал:',
            'text' => $this->formatCommentTextForSlack($comment->getCleanText()),
            'title_link' => $commentUrl
        ]]);
    }

    /**
     * Приводит markdown-разметку текста комментария к виду, понятному Slack.
     *
     * Упоминания пользователей `[@имя](user:id)` заменяются на `@имя`,
     * а markdown-ссылки `[текст](url)` - на формат ссылок Slack `<url|текст>`.
     */
    private function formatCommentTextForSlack($text)
    {
        $text = preg_replace('/\[(@[^\]]*?)]\(user:[0-9]+\)/', '$1', $text);
        $text = preg_replace('/\[([^\]]*)]\(([^)\s]+)\)/', '<$2|$1>', $text);

        return $text;
    }

    /**
     * Отправляет оповещение по задаче в канал её проекта.
     *
     * Все оповещения по задаче собираются в одну ветку: первое сообщение
     * её открывает, метка ветки сохраняется и дальше используется напрямую.
     */
    private function postMessageForIssue(Issue $issue, $text, $attachments = null)
    {
        $project = $issue->getProject();
        if (!($channel = $this->getChannelByProject($project))) {
            return;
        }

        $issueId = (int)$issue->id;

        $args = ['channel' => $channel, 'text' => $text];
        if (!empty($attachments)) {
            $args['attachments'] = json_encode($attachments);
        }

        $threadTs = $this->loadThreadTs($issueId, $channel);

        try {
            $res = $this->sendMessage($args, $threadTs);
        } catch (\JoliCode\Slack\Exception\SlackErrorResponse $e) {
            if ($threadTs === null) {
                $this->logError(self::MSG_POST_FAILED, $e, $channel);

                return;
            }

            // Slack отказался писать в ветку - обычно потому, что сообщение,
            // которое её открывало, удалили. Отдельного кода ошибки для этого
            // в справочнике chat.postMessage нет, поэтому повторяем отправку
            // вне ветки при любом отказе, названном самим Slack. Сбои связи
            // сюда не попадают: там неизвестно, дошёл ли запрос.
            $this->logError(self::MSG_THREAD_POST_FAILED, $e, $channel);

            try {
                $res = $this->sendMessage($args, null);
            } catch (\Throwable $retryError) {
                $this->logError(self::MSG_POST_FAILED, $retryError, $channel);

                return;
            }

            // Вне ветки сообщение прошло - значит прежняя ветка непригодна,
            // и дальше задача ведётся от только что отправленного сообщения.
            $threadTs = null;
        } catch (\Throwable $e) {
            $this->logError(self::MSG_POST_FAILED, $e, $channel);

            return;
        }

        // Метку запоминаем только у сообщения, которое открыло ветку:
        // ответ в уже существующей ветке возвращает собственную метку.
        if ($threadTs === null) {
            $this->rememberThreadTs($issueId, $channel, $res);
        }
    }

    /**
     * Отправляет сообщение в канал.
     *
     * @param  array       $args Аргументы chat.postMessage.
     * @param  String|null $threadTs Метка ветки или null, чтобы отправить
     *                               сообщение отдельно, вне ветки.
     * @return JoliCode\Slack\Api\Model\ChatPostMessagePostResponse200 Ответ Slack.
     * @throws JoliCode\Slack\Exception\SlackErrorResponse В случае ошибки в ответ на запрос.
     */
    private function sendMessage(array $args, $threadTs)
    {
        if ($threadTs !== null) {
            $args['thread_ts'] = $threadTs;
        }

        return $this->getClient()->chatPostMessage($args);
    }

    /**
     * Метка ветки задачи в канале.
     *
     * @return String|null Метка или null, если ветки ещё нет либо
     *                     прочитать её не удалось.
     */
    private function loadThreadTs($issueId, $channel)
    {
        try {
            return SlackIssueThread::loadTs($issueId, $channel);
        } catch (\Throwable $e) {
            LPMLog::error('Не удалось прочитать метку ветки Slack', LPMLog::CH_SLACK, [
                'channel' => $channel,
                'issueId' => $issueId,
                'error' => get_class($e) . ': ' . $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Запоминает метку ветки, открытой отправленным сообщением.
     *
     * @param JoliCode\Slack\Api\Model\ChatPostMessagePostResponse200 $res Ответ Slack.
     */
    private function rememberThreadTs($issueId, $channel, $res)
    {
        $threadTs = $res === null ? null : $res->getTs();
        if (empty($threadTs)) {
            return;
        }

        try {
            SlackIssueThread::saveTs($issueId, $channel, $threadTs);
        } catch (\Throwable $e) {
            // Не запомнили метку - следующее оповещение просто начнёт новую
            // ветку. Прерывать из-за этого действие пользователя незачем.
            LPMLog::error('Не удалось сохранить метку ветки Slack', LPMLog::CH_SLACK, [
                'channel' => $channel,
                'issueId' => $issueId,
                'error' => get_class($e) . ': ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Записывает в журнал неудачное обращение к Slack API.
     *
     * @param String     $message Что не удалось сделать.
     * @param \Throwable $e Ошибка, с которой завершилось обращение.
     * @param String     $channel Идентификатор канала.
     */
    private function logError($message, \Throwable $e, $channel)
    {
        $error = $e instanceof \JoliCode\Slack\Exception\SlackErrorResponse
            ? $e->getErrorCode()
            : get_class($e) . ': ' . $e->getMessage();

        LPMLog::error($message, LPMLog::CH_SLACK, [
            'channel' => $channel,
            'error' => $error,
        ]);
    }

    private function getIssuePrefix(Issue $issue)
    {
        return 'Задача #' . $issue->idInProject . ' ';
    }

    private function getSlackNames($users)
    {
        $slackNames = [];
        if (!empty($users)) {
            foreach ($users as $user) {
                if (!empty($user->slackName)) {
                    $slackNames[] = $user->slackName;
                }
            }
        }

        return $slackNames;
    }

    private function addMentions($message, $slackNames)
    {
        if (!empty($slackNames)) {
            $message = "<@" . implode(">, <@", $slackNames) . "> " . $message;
        }

        return $message;
    }

    private function addMentionsByUsers($message, $users)
    {
        return $this->addMentions($message, $this->getSlackNames($users));
    }

    private function getChannelByProject(Project $project)
    {
        if (empty($project->slackNotifyChannel)) {
            return null;
        }

        return $project->slackNotifyChannel;
    }
}
