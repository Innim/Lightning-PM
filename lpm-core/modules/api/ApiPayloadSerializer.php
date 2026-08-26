<?php

class ApiPayloadSerializer
{
    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Полное представление задачи.
     *
     * Приоритет отдаётся в отображаемой шкале (1..100), как в интерфейсе.
     * @return stdClass
     */
    public function issue(Issue $issue)
    {
        $obj = $this->issueObject($issue);

        $obj->members = [];
        foreach ($issue->getMembers() as $member) {
            $obj->members[] = $member->getClientObject();
        }

        $obj->testers = [];
        foreach ($issue->getTesters() as $tester) {
            $obj->testers[] = $tester->getClientObject();
        }

        $obj->masters = [];
        foreach ($issue->getMasters() as $master) {
            $obj->masters[] = $master->getClientObject();
        }

        $obj->images = [];
        foreach ($issue->getImages() as $image) {
            $obj->images[] = [
                'imgId' => $image->imgId,
                'source' => $image->getSource(),
                'preview' => $image->getPreview(),
            ];
        }

        $obj->files = [];
        foreach ($issue->getFiles() as $file) {
            $obj->files[] = $this->file($file);
        }

        $obj->linked = [];
        foreach ($issue->getLinkedIssues() as $linked) {
            $obj->linked[] = $this->issueObject($linked);
        }

        $obj->labels = $issue->getLabelNames();
        $obj->isOnBoard = $issue->isOnBoard();
        $obj->project = (object)$this->project($issue->getProject());

        $obj->comments = [];
        foreach (Comment::getListByInstance(LPMInstanceTypes::ISSUE, $issue->id) as $comment) {
            $comment->issue = $issue;
            $obj->comments[] = $this->comment($comment);
        }

        $obj->actions = (object)[
            'comment' => $this->baseUrl . '/issues/' . $issue->id . '/comments',
            'createBranch' => $this->baseUrl . '/issues/' . $issue->id . '/branches',
            'repositories' => $this->baseUrl . '/projects/' . $issue->projectId . '/repositories',
        ];

        return $obj;
    }

    /**
     * Поля задачи, общие для полного представления и для вложенных в него
     * связанных задач: клиентский объект без служебных полей веб-формы
     * и с приоритетом в отображаемой шкале.
     * @return stdClass
     */
    private function issueObject(Issue $issue)
    {
        $obj = $issue->getClientObject();
        unset($obj->formattedDesc);
        unset($obj->completeDateInput);
        $obj->priority = Issue::getPriorityDisplayValueBy($issue->priority);

        return $obj;
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
            'priority' => Issue::getPriorityDisplayValueBy($issue->priority),
            'hours' => $issue->hours,
            'labels' => $issue->getLabelNames(),
            'commentsCount' => $issue->commentsCount,
            'createDate' => $issue->createDate,
            'modifiedDate' => $issue->modifiedDate,
            'completeDate' => $issue->completeDate,
            'completedDate' => $issue->completedDate,
            'author' => [
                'id' => $issue->author->getID(),
                'name' => $issue->author->getPlainName(),
                'nick' => $issue->author->nick,
            ],
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
        $item['addedToBoard'] = $sticker->added;

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
            'createdAt' => date('c', $comment->date),
            'author' => [
                'id' => $comment->author->getID(),
                'name' => $comment->author->getPlainName(),
                'nick' => $comment->author->nick,
            ],
            'type' => $type,
            'meta' => $meta,
            'files' => $files,
            'url' => empty($comment->issue) ? null : $comment->getIssueCommentUrl($comment->issue),
        ];
    }

    /**
     * Вложение задачи или комментария.
     * @return stdClass
     */
    private function file(LPMFile $file)
    {
        $obj = $file->getClientObject();
        $obj->requiresAuthentication = true;
        return $obj;
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
        return [
            'id' => $project->id,
            'name' => $project->name,
            'path' => $project->path,
            'url' => $project->url,
            'lastActivity' => $project->lastActivity,
        ];
    }
}
