<?php
/**
 * Интеграция со Slack.
 */
class SlackIntegration {
	private static $_instance;
	/**
	 * @return SlackIntegration
	 */
	public static function getInstance() {
		if (self::$_instance === null) {
			// TODO: проверка на пустоту и существование?
			self::$_instance = new SlackIntegration(SLACK_TOKEN);
		}

		return self::$_instance;
	}

	private $_token;

	private $_client;
	private $_loop;

	function __construct($token) {
		$this->_token = $token;
	}

	public function notifyIssueForTest(Issue $issue) {
		if (!($channel = $this->getChannelByProject($issue->getProject())))
			return;

		$text = $this->getIssuePrefix($issue) . '_"' . $issue->name . '"_ - в *тестирование*';
		$text = $this->addMentionsByUsers($text, $issue->getTesters());

		$this->postMessage($channel, $text, [[
			'fallback' => $issue->getName(),
			'title' => $issue->getName(),
			'text' => $issue->getShortDesc(),
			'title_link' => $issue->getConstURL()
		]]);

		//priority -> цвет?
		//getImages -> image_url/thumb_url
		// TODO: прикрутить thread
		// $client = $this->getClient();
		// $message = $client->getMessageBuilder()->
		// 	setText($text)->
		// 	setChannel($channel)->
		// 	addAttachment((new AttachmentBuilder())->
		//     	setTitle($issue->getName())->
		//     	setText($issue->getShortDesc())->
		//     	setFallbackText($issue->getName())->
		//     	setColor('#BADA55')->
		//     	create()
		//     )->
		// 	create();

		// $client->postMessage($message);
	}

	public function notifyIssueCompleted(Issue $issue) {
		if (!($channel = $this->getChannelByProject($issue->getProject())))
			return;

		// TODO: постить в канал
		$text = $this->getIssuePrefix($issue) . ' ' . $issue->getConstURL() . ' - *завершена*';
		$text = $this->addMentionsByUsers($text, $issue->getMembers());

		$this->postMessage($channel, $text);
	}

	public function notifyIssuePassTest(Issue $issue) {
		$project = $issue->getProject();
		if (!($channel = $this->getChannelByProject($project)))
			return;

		$master = $project->getMaster();

		// TODO: постить в канал
		$text = $this->getIssuePrefix($issue) . ' ' . $issue->getConstURL() . ' - *прошла тестирование*';
		$text = $this->addMentionsByUsers($text, $master !== null ? [$master] : null);

		$this->postMessage($channel, $text);
	}

	private function getClient() {
		if ($this->_client == null) {
			$loop = \React\EventLoop\Factory::create();
			$client = new \Slack\ApiClient($loop);
			$client->setToken($this->_token);

			$this->_loop = $loop;
			$this->_client = $client;
		}

		return $this->_client;
	}

	private function postMessage($channel, $text, $attachments = null) {
		$client = $this->getClient();
		$args = ['channel' => $channel, 'text' => $text];
		if (!empty($attachments))
			$args['attachments'] = json_encode($attachments);
		$client->apiCall('chat.postMessage', $args)->then(function (\Slack\Payload $res) {
			$data = $res->getData();
			if (empty($data['ok']))
			{
				// TODO: обработка ошибки
			}
			// echo '<pre>'; var_dump($data); echo '</pre>';
		});

		$this->_loop->run();
	}

	private function getIssuePrefix(Issue $issue) {
		return 'Задача #' . $issue->idInProject . ' ';
	}

	private function getSlackNames($users) {
		$slackNames = [];
		if (!empty($users)) {
			foreach ($users as $user) {
				if (!empty($user->slackName))
					$slackNames[] = $user->slackName;
			}
		}

		return $slackNames;
	}

	private function addMentions($message, $slackNames) {
		if (!empty($slackNames))
			$message = "<@" . implode(">, <@", $slackNames) . "> " . $message;

		return $message;
	}

	private function addMentionsByUsers($message, $users) {
		return $this->addMentions($message, $this->getSlackNames($users));
	}

	private function getChannelByProject(Project $project) {
		if (empty($project->slackNotifyChannel))
			return null;

		return $project->slackNotifyChannel;
	}
}