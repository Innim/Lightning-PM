<?php
/**
 * Ветка обсуждения задачи в канале Slack.
 *
 * Все оповещения по одной задаче собираются в Slack в одну ветку. Веткой
 * владеет метка времени (`ts`) её первого сообщения - её и храним, получив
 * в ответе на отправку этого сообщения.
 *
 * Ключ - пара «задача - канал», а не одна задача: канал оповещений проекта
 * можно сменить, и в новом канале у задачи начнётся своя ветка, а метка
 * прежней останется и снова пригодится, если канал вернут обратно.
 */
class SlackIssueThread extends LPMBaseObject
{
    /**
     * Метка ветки задачи в канале.
     *
     * @param  int    $issueId Идентификатор задачи.
     * @param  String $channel Идентификатор канала Slack.
     * @return String|null Метка времени первого сообщения по задаче в этом
     *                     канале или null, если его ещё не было.
     * @throws \GMFramework\ProviderLoadException При ошибке выборки.
     */
    public static function loadTs($issueId, $channel)
    {
        $row = self::buildAndExecuteSingle([
            'SELECT' => 'threadTs',
            'FROM'   => LPMTables::SLACK_ISSUE_THREAD,
            'WHERE'  => [
                'issueId' => (int)$issueId,
                'channel' => (string)$channel,
            ],
            'LIMIT'  => 1,
        ]);

        if (empty($row) || empty($row['threadTs'])) {
            return null;
        }

        return $row['threadTs'];
    }

    /**
     * Запоминает метку ветки задачи в канале, заменяя прежнюю, если она была:
     * ветка задачи в канале всегда одна - последняя начатая.
     *
     * @param  int    $issueId  Идентификатор задачи.
     * @param  String $channel  Идентификатор канала Slack.
     * @param  String $threadTs Метка времени сообщения, открывающего ветку.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить.
     */
    public static function saveTs($issueId, $channel, $threadTs)
    {
        self::buildAndSaveToDbV2([
            'INSERT' => [
                'issueId'  => (int)$issueId,
                'channel'  => (string)$channel,
                'threadTs' => (string)$threadTs,
            ],
            'INTO'   => LPMTables::SLACK_ISSUE_THREAD,
            'ODKU'   => ['threadTs'],
        ]);
    }
}
