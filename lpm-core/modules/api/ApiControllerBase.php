<?php

abstract class ApiControllerBase
{
    private $engine;
    private $request;
    private $serializer;

    public function __construct(LightningEngine $engine, ApiRequest $request, ApiPayloadSerializer $serializer)
    {
        $this->engine = $engine;
        $this->request = $request;
        $this->serializer = $serializer;
    }

    protected function engine()
    {
        return $this->engine;
    }

    protected function request()
    {
        return $this->request;
    }

    protected function serializer()
    {
        return $this->serializer;
    }

    /**
     * @return User
     */
    protected function user()
    {
        $user = $this->engine->getUser();
        if (!$user) {
            throw new Exception('Authentication required');
        }
        return $user;
    }

    protected function loadIssueById($issueId)
    {
        $user = $this->user();
        $issue = Issue::load((int)$issueId);
        if (!$issue || !$issue->checkViewPermit($user->getID())) {
            return null;
        }

        $project = $issue->getProject();
        if (!$project || !$project->hasReadPermission($user)) {
            return null;
        }

        return $issue;
    }

    protected function loadIssueByUrl($url)
    {
        $user = $this->user();
        $url = trim((string)$url);
        if ($url === '') {
            throw new Exception('Issue URL is required');
        }

        $parts = parse_url($url);
        if (empty($parts['path'])) {
            return null;
        }

        $path = trim($parts['path'], '/');
        $sitePath = trim((string)parse_url(SITE_URL, PHP_URL_PATH), '/');
        if ($sitePath !== '' && strpos($path, $sitePath . '/') === 0) {
            $path = substr($path, strlen($sitePath) + 1);
        } elseif ($path === $sitePath) {
            $path = '';
        }

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        if (count($segments) < 4 || $segments[0] !== ProjectPage::UID || $segments[2] !== ProjectPage::PUID_ISSUE) {
            return null;
        }

        $project = Project::load($segments[1]);
        if (!$project || !$project->hasReadPermission($user)) {
            return null;
        }

        $issue = Issue::loadByIdInProject($project->id, (int)$segments[3]);
        if (!$issue || !$issue->checkViewPermit($user->getID())) {
            return null;
        }

        return $issue;
    }

    protected function loadProject($projectIdOrUid)
    {
        $user = $this->user();
        $project = ctype_digit((string)$projectIdOrUid)
            ? Project::loadById((int)$projectIdOrUid)
            : Project::load((string)$projectIdOrUid);

        if (!$project || !$project->hasReadPermission($user)) {
            return null;
        }

        return $project;
    }

    /**
     * @return GitlabIntegration
     */
    protected function requireGitlab(Project $project)
    {
        $client = GitlabIntegration::getInstance($this->user());
        if (!$project->isIntegratedWithGitlab() || !$client->isAvailableForUser()) {
            throw new Exception('GitLab integration is not available for this user');
        }

        return $client;
    }

    protected function validateBranchName($value)
    {
        return \GMFramework\Validation::checkStr($value, 255, 1, false, false, true, '\/\._-');
    }
}
