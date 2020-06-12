<?php
/**
 * Интеграция со Slack.
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
            // TODO: проверка на пустоту и существование?
            self::$_instance = new SlackIntegration(SLACK_TOKEN);
        }

        return self::$_instance;
    }

    private $_token;

    private $_client;
    private $_loop;

    public function __construct($token)
    {
        $this->_token = $token;
    }

    public function notifyIssueForTest(Issue $issue)
    {
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
        $text = $this->getIssuePrefix($issue) . $issue->getConstURL() . ' - *завершена*';
        $text = $this->addMentionsByUsers($text, $issue->getMembers());

        $this->postMessageForIssue($issue, $text);
    }

    public function notifyCommentTesterToMember(Issue $issue, Comment $comment)
    {
        $this->postMessageForIssueComment(
            $issue,
            $comment,
            $issue->getMembers(),
            'Тестировщик оставил комментарий'
        );
    }

    public function notifyCommentMemberToTester(Issue $issue, $comment)
    {
        $this->postMessageForIssueComment(
            $issue,
            $comment,
            $issue->getTesters(),
            'Исполнитель оставил комментарий'
        );
    }

    public function notifyMRMergedToTester(Issue $issue, GitlabMergeRequest $mr)
    {
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
        $project = $issue->getProject();
        $master = $project->getMaster();

        $text = $this->getIssuePrefix($issue) . $issue->getConstURL() . ' - *прошла тестирование*';
        $text = $this->addMentionsByUsers($text, $master !== null ? [$master] : null);

        $this->postMessageForIssue($issue, $text);
    }

    private function getClient()
    {
        if ($this->_client == null) {
            $loop = \React\EventLoop\Factory::create();
            $client = new \Slack\ApiClient($loop);
            $client->setToken($this->_token);

            $this->_loop = $loop;
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
            'title' => $comment->author->getShortName() . ' написал:',
            'text' => $comment->getCleanText(),
            'title_link' => $commentUrl
        ]]);
    }

    private function postMessageForIssue(Issue $issue, $text, $attachments = null)
    {
        $project = $issue->getProject();
        if (!($channel = $this->getChannelByProject($project))) {
            return;
        }

        // Ищем сообщение, которое будет как базовое для ветки
        $prefix = $this->getIssuePrefix($issue);
        $client = $this->getClient();
        $client->apiCall('groups.history', ['channel' => $channel, 'count' => 50])->then(function (\Slack\Payload $res) use ($client, $channel, $text, $attachments, $prefix) {
            $data = $res->getData();
            // echo '<pre>groups.history: '; var_dump($data); echo '</pre>';
            $threadTs = null;
            if (!empty($data['ok'])) {
                foreach ($data['messages'] as $message) {
                    if (mb_strpos($message['text'], $prefix) !== false) {
                        $threadTs = isset($message['thread_ts']) ?
                            $message['thread_ts'] : $message['ts'];
                        break;
                    }
                }
            }
            // else
            // {
            // 	// TODO: обработка ошибки
            // }

            $this->postMessage($channel, $text, $attachments, $threadTs);
        });

        $this->_loop->run();
    }

    private function postMessage($channel, $text, $attachments = null, $threadTs = null)
    {
        $client = $this->getClient();
        $args = ['channel' => $channel, 'text' => $text];
        if (!empty($attachments)) {
            $args['attachments'] = json_encode($attachments);
        }
        if (!empty($threadTs)) {
            $args['thread_ts'] = $threadTs;
        }
        $client->apiCall('chat.postMessage', $args)->then(function (\Slack\Payload $res) {
            $data = $res->getData();
            if (empty($data['ok'])) {
                // TODO: обработка ошибки
            }
            // echo '<pre>'; var_dump($data); echo '</pre>';
        });

        $this->_loop->run();
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
