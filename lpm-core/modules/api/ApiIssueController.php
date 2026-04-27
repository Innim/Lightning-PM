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
