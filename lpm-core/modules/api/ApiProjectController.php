<?php

class ApiProjectController extends ApiControllerBase
{
    /** Значения фильтра по статусу задачи. */
    const ISSUE_STATUSES = [
        'inWork' => Issue::STATUS_IN_WORK,
        'test' => Issue::STATUS_WAIT,
        'completed' => Issue::STATUS_COMPLETED,
    ];

    /** Значения фильтра по типу задачи. */
    const ISSUE_TYPES = [
        'develop' => Issue::TYPE_DEVELOP,
        'bug' => Issue::TYPE_BUG,
        'support' => Issue::TYPE_SUPPORT,
    ];

    const ISSUES_DEFAULT_LIMIT = 50;
    const ISSUES_MAX_LIMIT = 200;

    public function dispatch(array $path)
    {
        $method = $this->request()->getMethod();

        if ($method === 'GET' && count($path) === 0) {
            return $this->listProjects();
        }

        if ($method !== 'GET' || count($path) < 2) {
            return ApiResponse::error('Route not found', 404);
        }

        $project = $this->loadProject($path[0]);
        if (!$project) {
            return ApiResponse::error('Project not found', 404);
        }

        if (count($path) === 2 && $path[1] === 'issues') {
            return $this->listIssues($project);
        }

        if (count($path) === 2 && $path[1] === 'labels') {
            return $this->listLabels($project);
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

    private function listProjects()
    {
        $isArchive = filter_var($this->request()->getQuery('archive', false), FILTER_VALIDATE_BOOLEAN);

        $result = [];
        foreach (Project::getAvailList($isArchive) as $project) {
            $result[] = $this->serializer()->project($project);
        }

        return ApiResponse::success([
            'projects' => $result,
        ]);
    }

    private function listIssues(Project $project)
    {
        $statuses = $this->parseEnumFilter('status', self::ISSUE_STATUSES);
        if ($statuses === false) {
            return ApiResponse::error('Invalid status filter', 400);
        }

        $types = $this->parseEnumFilter('type', self::ISSUE_TYPES);
        if ($types === false) {
            return ApiResponse::error('Invalid type filter', 400);
        }

        $filters = [
            'statuses' => $statuses,
            'types' => $types,
            'labels' => $this->parseListFilter('label'),
            'search' => trim((string)$this->request()->getQuery('search', '')),
        ];

        $limit = (int)$this->request()->getQuery('limit', self::ISSUES_DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = self::ISSUES_DEFAULT_LIMIT;
        }
        $limit = min($limit, self::ISSUES_MAX_LIMIT);
        $offset = max(0, (int)$this->request()->getQuery('offset', 0));

        $issues = [];
        foreach (Issue::loadListByProjectFiltered($project->id, $filters, $limit, $offset) as $issue) {
            $issues[] = $this->serializer()->issueBrief($issue);
        }

        return ApiResponse::success([
            'project' => $this->serializer()->project($project),
            'issues' => $issues,
            'paging' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => Issue::countListByProjectFiltered($project->id, $filters),
            ],
        ]);
    }

    private function listLabels(Project $project)
    {
        $labels = [];
        foreach ($project->getLabels() as $label) {
            $labels[] = $this->serializer()->label($label);
        }

        return ApiResponse::success([
            'project' => $this->serializer()->project($project),
            'labels' => $labels,
        ]);
    }

    /**
     * Разбирает фильтр запроса с перечислением значений через запятую.
     *
     * Значение задаётся именем (`bug`) или числовым кодом (`1`); `all` и пустое
     * значение означают отсутствие фильтра.
     * @param  string $name   Имя параметра запроса.
     * @param  array  $values Допустимые значения [имя => код].
     * @return array|false Список кодов или false, если значение не распознано.
     */
    private function parseEnumFilter($name, array $values)
    {
        $result = [];
        foreach ($this->parseListFilter($name) as $item) {
            if (array_key_exists($item, $values)) {
                $result[] = $values[$item];
            } elseif (ctype_digit($item) && in_array((int)$item, $values, true)) {
                $result[] = (int)$item;
            } elseif ($item !== 'all') {
                return false;
            }
        }

        return $result;
    }

    /**
     * Разбирает фильтр запроса со списком строковых значений через запятую.
     * @param  string $name Имя параметра запроса.
     * @return array<string> Список непустых значений.
     */
    private function parseListFilter($name)
    {
        $raw = trim((string)$this->request()->getQuery($name, ''));
        if ($raw === '') {
            return [];
        }

        $result = [];
        foreach (explode(',', $raw) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return $result;
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
