<?php

class ApiPayloadSerializer
{
    /**
     * Состояние стикера по имени колонки доски.
     * @param  string $key Имя колонки.
     * @return int|null Состояние стикера или null, если такой колонки нет.
     */
    public static function boardColumnState($key)
    {
        foreach (self::BOARD_COLUMNS as $state => $column) {
            if ($column['key'] === $key) {
                return $state;
            }
        }

        return null;
    }

    /**
     * Имена всех колонок доски в порядке их отображения.
     * @return array<string>
     */
    public static function boardColumnKeys()
    {
        $keys = [];
        foreach (self::BOARD_COLUMNS as $column) {
            $keys[] = $column['key'];
        }

        return $keys;
    }

    /**
     * Момент времени в формате ISO-8601 с часовым поясом.
     * @param  float|int $timestamp Unix-время; 0 означает, что значения нет.
     * @return string|null Дата со временем или null, если значения нет.
     */
    public static function dateTime($timestamp)
    {
        return empty($timestamp) ? null : date('c', (int)$timestamp);
    }

    /**
     * Календарная дата в формате ISO-8601 (`YYYY-MM-DD`), без времени.
     *
     * Для полей, у которых время не имеет смысла: в таком же виде API
     * принимает их на вход.
     * @param  float|int $timestamp Unix-время; 0 означает, что значения нет.
     * @return string|null Дата или null, если значения нет.
     */
    public static function date($timestamp)
    {
        return empty($timestamp) ? null : date('Y-m-d', (int)$timestamp);
    }

    /**
     * Машиночитаемый ключ подстатуса задачи.
     * @param  int $substatus Подстатус задачи.
     * @return string|null Ключ или null, если у задачи нет подстатуса.
     * @see IssueSubstatus
     */
    public static function substatusKey($substatus)
    {
        return isset(self::SUBSTATUSES[$substatus]) ? self::SUBSTATUSES[$substatus] : null;
    }

    /**
     * Колонки скрам-доски в порядке их отображения:
     * состояние стикера => машиночитаемый ключ и название колонки.
     *
     * Ключи - единственные имена колонок, которые понимает и отдаёт API.
     * @see ScrumStickerState
     */
    const BOARD_COLUMNS = [
        ScrumStickerState::TODO => ['key' => 'todo', 'name' => 'TO DO'],
        ScrumStickerState::IN_PROGRESS => ['key' => 'inProgress', 'name' => 'В работе'],
        ScrumStickerState::TESTING => ['key' => 'testing', 'name' => 'Тестируется'],
        ScrumStickerState::DONE => ['key' => 'done', 'name' => 'Готово'],
    ];

    /**
     * Подстатусы задачи: код подстатуса => машиночитаемый ключ.
     *
     * Ключи - единственные имена подстатусов, которые отдаёт API.
     * Отсутствующий в наборе код (IssueSubstatus::NONE) отдаётся как null.
     * @see IssueSubstatus
     */
    const SUBSTATUSES = [
        IssueSubstatus::BACKLOG => 'backlog',
        IssueSubstatus::TODO => 'todo',
        IssueSubstatus::IN_PROGRESS => 'inProgress',
        IssueSubstatus::UNDER_TESTING => 'underTesting',
        IssueSubstatus::PASS_TEST => 'passedTest',
    ];

    /** Значение `hoursUnit`: оценка задачи в story points (скрам-проект). */
    const HOURS_UNIT_STORY_POINTS = 'storyPoints';

    /** Значение `hoursUnit`: оценка задачи в часах (проект без скрам-доски). */
    const HOURS_UNIT_HOURS = 'hours';

    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Полное представление задачи.
     *
     * Приоритет отдаётся в отображаемой шкале (1..100), как в интерфейсе.
     * @return array
     */
    public function issue(Issue $issue)
    {
        $obj = $this->issueObject($issue);

        $obj['members'] = [];
        foreach ($issue->getMembers() as $member) {
            $obj['members'][] = $this->member($member);
        }

        $obj['testers'] = [];
        foreach ($issue->getTesters() as $tester) {
            $obj['testers'][] = $this->user($tester);
        }

        $obj['masters'] = [];
        foreach ($issue->getMasters() as $master) {
            $obj['masters'][] = $this->user($master);
        }

        $obj['images'] = [];
        foreach ($issue->getImages() as $image) {
            $obj['images'][] = [
                'imgId' => $image->imgId,
                'source' => $image->getSource(),
                'preview' => $image->getPreview(),
            ];
        }

        $obj['files'] = [];
        foreach ($issue->getFiles() as $file) {
            $obj['files'][] = $this->file($file);
        }

        $obj['linked'] = [];
        foreach ($issue->getLinkedIssues() as $linked) {
            $obj['linked'][] = $this->issueObject($linked);
        }

        $obj['project'] = $this->project($issue->getProject());

        $obj['comments'] = [];
        foreach (Comment::getListByInstance(LPMInstanceTypes::ISSUE, $issue->id) as $comment) {
            $comment->issue = $issue;
            $obj['comments'][] = $this->comment($comment);
        }

        $obj['actions'] = [
            'comment' => $this->baseUrl . '/issues/' . $issue->id . '/comments',
            'createBranch' => $this->baseUrl . '/issues/' . $issue->id . '/branches',
            'repositories' => $this->baseUrl . '/projects/' . $issue->projectId . '/repositories',
            'board' => $this->baseUrl . '/issues/' . $issue->id . '/board',
        ];

        return $obj;
    }

    /**
     * Имя колонки доски, в которой сейчас находится задача.
     * @param  Issue $issue Задача.
     * @return string|null Имя колонки или null, если задачи нет на доске.
     */
    public function boardColumn(Issue $issue)
    {
        $sticker = $issue->getSticker();
        if (empty($sticker) || !$sticker->isOnBoard()) {
            return null;
        }

        return isset(self::BOARD_COLUMNS[$sticker->state])
            ? self::BOARD_COLUMNS[$sticker->state]['key']
            : null;
    }

    /**
     * Краткое представление задачи для списков.
     *
     * Не содержит описания, комментариев и вложений - их отдаёт запрос самой задачи.
     * Приоритет отдаётся в отображаемой шкале (1..100), как в интерфейсе.
     * @return array
     */
    public function issueBrief(Issue $issue)
    {
        return [
            'id' => $issue->id,
            'idInProject' => $issue->idInProject,
            'name' => $issue->getName(),
            'url' => $issue->getConstURL(),
            'type' => $issue->type,
            'status' => $issue->status,
            'substatus' => self::substatusKey($issue->getSubstatus()),
            'priority' => Issue::getPriorityDisplayValueBy($issue->priority),
            'hours' => $issue->hours,
            'hoursUnit' => $issue->projectScrum ? self::HOURS_UNIT_STORY_POINTS : self::HOURS_UNIT_HOURS,
            'labels' => $issue->getLabelNames(),
            'commentsCount' => $issue->commentsCount,
            'isOnBoard' => $issue->isOnBoard(),
            'boardColumn' => $this->boardColumn($issue),
            'createDate' => self::dateTime($issue->createDate),
            'modifiedDate' => self::dateTime($issue->modifiedDate),
            'completeDate' => self::date($issue->completeDate),
            'completedDate' => self::dateTime($issue->completedDate),
            'author' => $this->user($issue->author),
        ];
    }

    /**
     * Задача на скрам-доске: краткое представление задачи, дополненное
     * состоянием её стикера и датой добавления на доску.
     * @param ScrumSticker $sticker Стикер доски с загруженной задачей.
     * @return array
     */
    public function boardIssue(ScrumSticker $sticker)
    {
        $item = $this->issueBrief($sticker->getIssue());
        $item['stickerState'] = $sticker->state;
        $item['addedToBoard'] = self::dateTime($sticker->added);

        return $item;
    }

    /**
     * Метка (тег) задач с количеством использований.
     * @param array $label Данные метки, см. Project::getLabels().
     * @return array
     */
    public function label(array $label)
    {
        return [
            'id' => (int)$label['id'],
            'label' => $label['label'],
            'uses' => (int)$label['projectUses'],
            'totalUses' => (int)$label['countUses'],
            'isCommon' => (int)$label['projectId'] === 0,
        ];
    }

    /**
     * Пользователь: единая форма для автора задачи и комментария,
     * участников, тестировщиков, мастеров и текущего пользователя.
     * @return array
     */
    public function user(User $user)
    {
        return [
            'id' => $user->getID(),
            'name' => $user->getPlainName(),
            'nick' => $user->nick,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'avatarUrl' => $user->getAvatarUrl(),
            'url' => $user->getUrl(),
        ];
    }

    /**
     * Исполнитель задачи: пользователь и его доля оценки задачи.
     * @return array
     */
    public function member(Member $member)
    {
        $item = $this->user($member);
        $item['sp'] = isset($member->sp) ? (float)$member->sp : 0;

        return $item;
    }

    public function comment(Comment $comment)
    {
        $type = null;
        $meta = null;

        if (!empty($comment->issueComment)) {
            $type = $comment->issueComment->type;

            if ($comment->issueComment->isCreateBranch()) {
                $data = $comment->issueComment->getCreateBranchData();
                if ($data) {
                    $meta = [
                        'repositoryId' => $data->repositoryId,
                        'branchName' => $data->branchName,
                    ];
                }
            }
        }

        $files = [];
        foreach ($comment->getFiles() as $file) {
            $files[] = $this->file($file);
        }

        return [
            'id' => $comment->id,
            'text' => $comment->text,
            'createdAt' => self::dateTime($comment->date),
            'author' => $this->user($comment->author),
            'type' => $type,
            'meta' => $meta,
            'files' => $files,
            'url' => empty($comment->issue) ? null : $comment->getIssueCommentUrl($comment->issue),
        ];
    }

    public function project(Project $project)
    {
        return [
            'id' => $project->id,
            'uid' => $project->uid,
            'name' => $project->name,
            'url' => $project->getUrl(),
            'scrum' => (bool)$project->scrum,
        ];
    }

    public function repository(GitlabProject $project)
    {
        $lastActivity = $project->lastActivity;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'path' => $project->path,
            'url' => $project->url,
            'lastActivity' => $lastActivity->isUndefined()
                ? null
                : self::dateTime($lastActivity->getUnixtime()),
        ];
    }

    /**
     * Вложение задачи или комментария.
     * @return array
     */
    private function file(LPMFile $file)
    {
        return [
            'fileId' => $file->fileId,
            'uid' => $file->uid,
            'name' => $file->origName,
            'mimeType' => $file->mimeType,
            'size' => $file->size,
            'sizeFormatted' => FileSizeFormatter::format($file->size),
            'created' => self::dateTime($file->created),
            'url' => $file->getDownloadUrl(),
            'requiresAuthentication' => true,
        ];
    }

    /**
     * Поля задачи, общие для полного представления и для вложенных в него
     * связанных задач: краткое представление, дополненное описанием.
     * @return array
     */
    private function issueObject(Issue $issue)
    {
        $obj = $this->issueBrief($issue);
        $obj['desc'] = $issue->desc;

        if ($issue->isBaseLinked !== null) {
            $obj['isBaseLinked'] = $issue->isBaseLinked;
        }

        return $obj;
    }
}
