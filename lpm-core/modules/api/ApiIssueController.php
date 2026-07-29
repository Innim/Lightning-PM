<?php

class ApiIssueController extends ApiControllerBase
{
    public function dispatch(array $path)
    {
        $method = $this->request()->getMethod();

        if ($method === 'GET' && $path === ['resolve']) {
            $issue = $this->loadIssueByUrl($this->request()->getQuery('url'));
            if (!$issue) {
                return ApiResponse::error('Issue not found', 404);
            }

            return ApiResponse::success([
                'issue' => $this->serializer()->issue($issue),
            ]);
        }

        if ($method === 'POST' && count($path) === 0) {
            return $this->createIssue();
        }

        if (count($path) < 1) {
            return ApiResponse::error('Route not found', 404);
        }

        $issue = $this->loadIssueById($path[0]);
        if (!$issue) {
            return ApiResponse::error('Issue not found', 404);
        }

        if ($method === 'GET' && count($path) === 1) {
            return ApiResponse::success([
                'issue' => $this->serializer()->issue($issue),
            ]);
        }

        if ($method === 'POST' && count($path) === 2 && $path[1] === 'comments') {
            return $this->createComment($issue);
        }

        if ($method === 'POST' && count($path) === 2 && $path[1] === 'branches') {
            return $this->createBranch($issue);
        }

        return ApiResponse::error('Route not found', 404);
    }

    private function createIssue()
    {
        $projectId = $this->request()->getBody('projectId');
        if ($projectId === null || $projectId === '') {
            return ApiResponse::error('projectId is required', 400);
        }

        $project = $this->loadProject($projectId);
        if (!$project) {
            return ApiResponse::error('Project not found', 404);
        }

        $name = trim((string)$this->request()->getBody('name'));
        if ($name === '') {
            return ApiResponse::error('Issue name is required', 400);
        }

        $desc = (string)$this->request()->getBody('desc', '');
        if (mb_strlen($desc) > Issue::DESC_MAX_LEN) {
            return ApiResponse::error('Description is too long, max ' . Issue::DESC_MAX_LEN . ' characters', 400);
        }

        $type = (int)$this->request()->getBody('type', Issue::TYPE_DEVELOP);
        if (!in_array($type, [Issue::TYPE_BUG, Issue::TYPE_DEVELOP, Issue::TYPE_SUPPORT], true)) {
            return ApiResponse::error('Invalid issue type', 400);
        }

        $priority = min(99, max(0, (int)$this->request()->getBody('priority', Issue::DEFAULT_PRIORITY)));
        $hours = Issue::parseStoryPoints($this->request()->getBody('hours', 0));
        if (!Issue::isValidStoryPoints($hours)) {
            return ApiResponse::error('Invalid hours, expected a non-negative integer or 0.5', 400);
        }

        $completeDate = Issue::parseCompleteDate($this->request()->getBody('completeDate'));
        if ($completeDate === false) {
            return ApiResponse::error('Invalid completeDate, expected format YYYY-MM-DD', 400);
        }

        $user = $this->user();
        $issueId = Issue::createNew($project, $name, $desc, $type, $priority, $hours, $completeDate, $user->getID());
        if (!$issueId) {
            throw new Exception('Failed to create issue');
        }

        $issue = Issue::load($issueId);
        if (!$issue) {
            throw new Exception('Failed to load created issue');
        }

        Project::updateIssuesCount($project->id);

        UserLogEntry::create(
            $user->getID(),
            DateTimeUtils::$currentDate,
            UserLogEntryType::ADD_ISSUE,
            $issue->id
        );

        Issue::notifyAdded($issue, $user);

        return ApiResponse::success([
            'issue' => $this->serializer()->issue($issue),
        ], 201);
    }

    private function createComment(Issue $issue)
    {
        $text = trim((string)$this->request()->getBody('text'));
        if ($text === '') {
            return ApiResponse::error('Comment text is required', 400);
        }

        $type = $this->request()->getBody('requestChanges') ? IssueCommentType::REQUEST_CHANGES : null;
        $comment = $this->engine()->comments()->postComment($this->user(), $issue, $text, false, false, $type);

        return ApiResponse::success([
            'comment' => $this->serializer()->comment($comment),
        ], 201);
    }

    private function createBranch(Issue $issue)
    {
        $branchName = trim((string)$this->request()->getBody('name'));
        $repositoryId = (int)$this->request()->getBody('repositoryId');
        $parentBranch = trim((string)$this->request()->getBody('parentBranch', 'develop'));

        if ($repositoryId <= 0 || !$this->validateBranchName($branchName) || !$this->validateBranchName($parentBranch)) {
            return ApiResponse::error('Invalid branch creation arguments', 400);
        }

        return ApiResponse::success($this->createBranchPayload($issue, $branchName, $repositoryId, $parentBranch), 201);
    }

    private function createBranchPayload(Issue $issue, $branchName, $gitlabProjectId, $parentBranch)
    {
        $project = $issue->getProject();
        $client = $this->requireGitlab($project);
        $user = $this->user();
        $userId = $user->getID();

        $finalBranchName = 'feature/' . $branchName;
        $gitlabProject = $client->getProject($gitlabProjectId);
        if (!$gitlabProject) {
            throw new Exception('Repository not found');
        }

        $branch = $client->createBranch($gitlabProjectId, $parentBranch, $finalBranchName);
        if (!$branch) {
            throw new Exception('Branch creation failed');
        }

        $commentText = $branch->name;
        if ($parentBranch !== 'develop') {
            $commentText = $parentBranch . ' -> ' . $commentText;
        }
        $commentText = '*' . $gitlabProject->name . '*: `' . $commentText . '`';

        $comment = $this->engine()->comments()->postComment(
            $user,
            $issue,
            $commentText,
            true,
            false,
            IssueCommentType::CREATE_BRANCH,
            IssueCommentCreateBranchData::serialize($gitlabProjectId, $finalBranchName)
        );

        IssueBranch::create($issue->id, $gitlabProjectId, $finalBranchName, $userId, $branch->commit->id);

        if ($issue->status == Issue::STATUS_IN_WORK) {
            if (!$issue->isMember($userId)) {
                if (!Member::saveIssueMembers($issue->id, [$userId])) {
                    throw new Exception('Failed to assign issue member');
                }

                $member = Member::loadByIssue($issue->id, $userId);
                if ($member) {
                    $issue->addMember($member);
                }

                UserLogEntry::issueEdit($userId, $issue->id, 'Add member by create branch via api');
            }

            $sticker = ScrumSticker::load($issue->id);
            if (!empty($sticker) && $sticker->state == ScrumStickerState::TODO) {
                if (!ScrumSticker::updateStickerState($issue->id, ScrumStickerState::IN_PROGRESS)) {
                    throw new Exception('Failed to move scrum sticker');
                }
            }
        }

        return [
            'branch' => [
                'name' => $branch->name,
                'url' => $branch->url,
                'parentBranch' => $parentBranch,
                'repository' => $this->serializer()->repository($gitlabProject),
            ],
            'comment' => $this->serializer()->comment($comment),
            'issue' => $this->serializer()->issue($issue),
        ];
    }
}
