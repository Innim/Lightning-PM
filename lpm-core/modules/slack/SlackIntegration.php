<?php
/**
 * Интеграция со Slack.
 * 
 * Для работы интеграции требуется бот-токен приложения Slack со scope:
 * - chat:write - отправка оповещений
 * - chat:write.public - отправка в публичные каналы, в которые приложение
 *   не добавлено
 * - users.profile:read - аватары пользователей
 *
 * В приватный канал приложение писать не может, пока его туда не пригласили:
 * chat:write.public на приватные каналы не распространяется, и scope, который
 * бы это менял, у Slack нет.
 *
 * Как выпустить токен и куда его прописать - docs/slack-integration.md
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

    /**
     * Статус проверки канала по коду ошибки, которым ответил Slack.
     *
     * @param  String $errorCode Код ошибки Slack (поле `error` ответа).
     * @return String Одна из констант CHECK_*, кроме CHECK_OK.
     */
    public static function checkStatusByErrorCode($errorCode)
    {
        switch ($errorCode) {
            // Приватный канал, в который приложение не приглашено, неотличим
            // для него от несуществующего: Slack отвечает channel_not_found.
            case 'not_in_channel':
            case 'channel_not_found':
                return self::CHECK_NOT_INVITED;
            case 'invalid_auth':
            case 'not_authed':
            case 'token_revoked':
            case 'token_expired':
            case 'account_inactive':
            case 'missing_scope':
            case 'no_permission':
                return self::CHECK_AUTH_FAILED;
            default:
                return self::CHECK_FAILED;
        }
    }

    /**
     * Проверка канала: сообщение доставлено.
     */
    const CHECK_OK = 'ok';

    /**
     * Проверка канала: сообщение доставлено, но автоматические оповещения
     * на этой установке выключены - канал настроен верно, а сообщений
     * по задачам в нём не будет.
     */
    const CHECK_OK_NOTIFICATION_OFF = 'okNotificationOff';

    /**
     * Проверка канала: приложения нет в канале либо канал ему не виден.
     */
    const CHECK_NOT_INVITED = 'notInvited';

    /**
     * Проверка канала: Slack не принял токен приложения.
     */
    const CHECK_AUTH_FAILED = 'authFailed';

    /**
     * Проверка канала: интеграция не настроена, токена нет.
     */
    const CHECK_NOT_CONFIGURED = 'notConfigured';

    /**
     * Проверка канала: прочий отказ Slack или сбой связи.
     */
    const CHECK_FAILED = 'failed';

    /**
     * Сообщение журнала о неудачной отправке оповещения.
     */
    private const MSG_POST_FAILED = 'Не удалось отправить сообщение в Slack';

    /**
     * Сообщение журнала о неудачной отправке оповещения в ветку.
     */
    private const MSG_THREAD_POST_FAILED = 'Не удалось отправить сообщение в ветку Slack';

    /**
     * Сообщение журнала о неудачной проверке канала.
     */
    private const MSG_CHECK_FAILED = 'Не удалось отправить проверочное сообщение в Slack';

    /**
     * Сообщение журнала о неудачной попытке узнать имя приложения.
     */
    private const MSG_AUTH_TEST_FAILED = 'Не удалось получить имя приложения Slack';

    /**
     * Предельное время обращения к Slack API, с.
     *
     * Без него отказавший Slack задерживал бы действие пользователя. Значение
     * должно оставаться заметно меньше таймаута фонового инвокера в
     * lpm-scripts/lightning.js: иначе браузер бросит ожидание подсказки
     * раньше, чем сервер успеет ответить, чем именно Slack не устроил.
     */
    private const REQUEST_TIMEOUT = 5;

    /**
     * Начало ключа кэша с именем приложения в Slack.
     */
    private const BOT_NAME_CACHE_PREFIX = 'slack_bot_name-';

    private $_token;

    private $_client;
    private $_notificationEnabled = true;

    /**
     * Имя приложения в Slack в пределах запроса: строка, null - если узнать
     * не удалось, false - ещё не запрашивали.
     * @var String|null|false
     */
    private $_botName = false;

    /**
     * @param String      $token Бот-токен приложения Slack.
     * @param bool        $notificationEnabled Включены ли оповещения.
     * @param object|null $client Готовый клиент Slack API; если не задан,
     *                            создаётся по токену.
     */
    public function __construct($token, $notificationEnabled, $client = null)
    {
        $this->_token = $token;
        $this->_notificationEnabled = $notificationEnabled;
        $this->_client = $client;
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

    /**
     * Включена ли отправка оповещений в Slack на этой установке.
     * @return bool
     */
    public function isNotificationEnabled()
    {
        return $this->_notificationEnabled;
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
     * Имя приложения в Slack - то, что подставляют в команду `/invite`.
     *
     * Имя нужно модератору проекта: пока приложение не приглашено в приватный
     * канал, оповещения туда не дойдут. Значение кэшируется - оно одно на всю
     * установку и меняется только при переименовании приложения в Slack.
     *
     * @return String|null Имя без символа «@» или null, если интеграция
     *                     не настроена либо Slack не ответил.
     */
    public function getBotName()
    {
        if ($this->_botName !== false) {
            return $this->_botName;
        }

        if (!$this->isConfigured()) {
            return $this->_botName = null;
        }

        $cache = $this->cache();
        $cacheKey = $this->getBotNameCacheKey();
        if ($cache !== null && ($cached = $cache->get($cacheKey))) {
            return $this->_botName = $cached;
        }

        try {
            $res = $this->getClient()->authTest();
            $name = $res === null ? null : $res->getUser();
        } catch (\Throwable $e) {
            $this->logError(self::MSG_AUTH_TEST_FAILED, $e);
            $name = null;
        }

        if (empty($name)) {
            return $this->_botName = null;
        }

        if ($cache !== null) {
            $cache->set($cacheKey, $name, CacheController::DAY);
        }

        return $this->_botName = $name;
    }

    /**
     * Отправляет в канал проверочное сообщение и сообщает, чем это кончилось.
     *
     * Сообщение уходит отдельно от веток задач, и его метка нигде не
     * запоминается: проверка не должна становиться началом обсуждения задачи.
     *
     * Выключатель оповещений проверку не останавливает: её запускает человек
     * прямо сейчас, а выключатель глушит автоматическую отправку по задачам.
     * Это позволяет настроить и проверить канал заранее, до включения
     * оповещений; о том, что они выключены, говорит статус ответа.
     *
     * @param  String $channel Идентификатор канала Slack.
     * @param  String $text Текст проверочного сообщения.
     * @return String Одна из констант CHECK_*.
     */
    public function checkChannel($channel, $text)
    {
        if (!$this->isConfigured()) {
            return self::CHECK_NOT_CONFIGURED;
        }

        try {
            $this->getClient()->chatPostMessage(['channel' => $channel, 'text' => $text]);

            return $this->_notificationEnabled
                ? self::CHECK_OK
                : self::CHECK_OK_NOTIFICATION_OFF;
        } catch (\JoliCode\Slack\Exception\SlackErrorResponse $e) {
            $this->logError(self::MSG_CHECK_FAILED, $e, $channel);

            return self::checkStatusByErrorCode($e->getErrorCode());
        } catch (\Throwable $e) {
            $this->logError(self::MSG_CHECK_FAILED, $e, $channel);

            return self::CHECK_FAILED;
        }
    }

    /**
     * @return JoliCode\Slack\Client
     */
    private function getClient()
    {
        if ($this->_client == null) {
            $httpClient = new \Symfony\Component\HttpClient\Psr18Client(
                \Symfony\Component\HttpClient\HttpClient::create([
                    'timeout' => self::REQUEST_TIMEOUT,
                    'max_duration' => self::REQUEST_TIMEOUT,
                ])
            );
            $client = JoliCode\Slack\ClientFactory::create($this->_token, $httpClient);
            $this->_client = $client;
        }

        return $this->_client;
    }

    /**
     * Ключ кэша с именем приложения: в него входит токен, поэтому смена
     * приложения или рабочего пространства не оставляет в кэше чужое имя.
     */
    private function getBotNameCacheKey()
    {
        return self::BOT_NAME_CACHE_PREFIX . md5($this->_token);
    }

    /**
     * Кэш приложения.
     *
     * @return CacheController|null null, если приложение не инициализировано
     *                              либо кэш выключен.
     */
    private function cache()
    {
        $engine = LightningEngine::getInstance();
        if ($engine === null) {
            return null;
        }

        $cache = $engine->cache();

        return $cache->isEnabled() ? $cache : null;
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
     * @param String      $message Что не удалось сделать.
     * @param \Throwable  $e Ошибка, с которой завершилось обращение.
     * @param String|null $channel Идентификатор канала, если обращение было
     *                             связано с каналом.
     */
    private function logError($message, \Throwable $e, $channel = null)
    {
        $error = $e instanceof \JoliCode\Slack\Exception\SlackErrorResponse
            ? $e->getErrorCode()
            : get_class($e) . ': ' . $e->getMessage();

        $context = [];
        if ($channel !== null) {
            $context['channel'] = $channel;
        }
        $context['error'] = $error;

        LPMLog::error($message, LPMLog::CH_SLACK, $context);
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
