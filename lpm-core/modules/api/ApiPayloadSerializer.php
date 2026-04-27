<?php

class ApiPayloadSerializer
{
    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function issue(Issue $issue)
    {
        $obj = $issue->getClientObject();
        unset($obj->formattedDesc);

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
            $item = $file->getClientObject();
            $item->requiresAuthentication = true;
            $obj->files[] = $item;
        }

        $obj->linked = [];
        foreach ($issue->getLinkedIssues() as $linked) {
            $obj->linked[] = $linked->getClientObject();
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

        return [
            'id' => $comment->id,
            'text' => $comment->text,
            'createdAt' => date('c', $comment->date),
            'author' => [
                'id' => $comment->author->getID(),
                'name' => $comment->author->getName(),
                'nick' => $comment->author->nick,
            ],
            'type' => $type,
            'meta' => $meta,
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
        return [
            'id' => $project->id,
            'name' => $project->name,
            'path' => $project->path,
            'url' => $project->url,
            'lastActivity' => $project->lastActivity,
        ];
    }
}
