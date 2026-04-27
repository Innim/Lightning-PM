<?php

class ApiProjectController extends ApiControllerBase
{
    public function dispatch(array $path)
    {
        $method = $this->request()->getMethod();
        if ($method !== 'GET' || count($path) < 2) {
            return ApiResponse::error('Route not found', 404);
        }

        $project = $this->loadProject($path[0]);
        if (!$project) {
            return ApiResponse::error('Project not found', 404);
        }

        if (count($path) === 2 && $path[1] === 'repositories') {
            return ApiResponse::success([
                'project' => $this->serializer()->project($project),
                'repositories' => $this->loadRepositories($project),
            ]);
        }

        if (count($path) === 4 && $path[1] === 'repositories' && $path[3] === 'branches') {
            return ApiResponse::success([
                'project' => $this->serializer()->project($project),
                'branches' => $this->loadBranches($project, (int)$path[2]),
            ]);
        }

        return ApiResponse::error('Route not found', 404);
    }

    private function loadRepositories(Project $project)
    {
        $client = $this->requireGitlab($project);
        $list = $client->getProjects($project->gitlabGroupId);
        $result = [];
        $loadedProjectIds = [];

        if (is_array($list)) {
            foreach ($list as $item) {
                $loadedProjectIds[] = $item->id;
                $result[] = $this->serializer()->repository($item);
            }
        }

        foreach ($project->getGitlabProjectIds() as $gitlabProjectId) {
            if (in_array($gitlabProjectId, $loadedProjectIds)) {
                continue;
            }

            $repo = $client->getProject($gitlabProjectId);
            if ($repo) {
                $result[] = $this->serializer()->repository($repo);
            }
        }

        return $result;
    }

    private function loadBranches(Project $project, $repositoryId)
    {
        $client = $this->requireGitlab($project);
        $list = $client->getBranches($repositoryId);
        $result = [];

        if (!is_array($list)) {
            return $result;
        }

        foreach ($list as $branch) {
            $result[] = [
                'name' => $branch->name,
                'url' => $branch->url,
                'lastCommit' => empty($branch->commit) ? null : [
                    'id' => $branch->commit->id,
                    'title' => $branch->commit->title,
                ],
            ];
        }

        return $result;
    }
}
