<?php
/**
 * Статистика проекта по скрам спринтам.
 */
class ProjectScrumStat extends ScrumStatBase
{
    public $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function getSP()
    {
        return $this->getTotalDoneSP();
    }
}
