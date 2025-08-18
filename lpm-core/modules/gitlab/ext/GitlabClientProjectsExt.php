<?php

/**
 * Расширение для работы с проектами GitLab.
 * 
 * Добавляет недостающие методы API.
 */
class GitlabClientProjectsExt extends \Gitlab\Api\Projects
{
    /**
     * @param int|string $project_id
     * @param string     $commit_ref
     *
     * @return mixed
     */
    public function pipelineLatest($project_id, string $commit_ref)
    {
        $parameters = [];
        if (!empty($commit_ref)) {
            $parameters['ref'] = $commit_ref;
        }

        return $this->get($this->getProjectPath($project_id, 'pipelines/latest'), $parameters);
    }
}