<?php

class ApiRouter
{
    private $request;
    private $meController;
    private $issueController;
    private $projectController;

    public function __construct(LightningEngine $engine, ApiRequest $request, $baseUrl)
    {
        $serializer = new ApiPayloadSerializer($baseUrl);
        $this->request = $request;
        $this->meController = new ApiMeController($engine, $request, $serializer);
        $this->issueController = new ApiIssueController($engine, $request, $serializer);
        $this->projectController = new ApiProjectController($engine, $request, $serializer);
    }

    public function handle()
    {
        $path = $this->request->getPath();
        if ($path === ['me']) {
            return $this->meController->show();
        }

        if (!empty($path) && $path[0] === 'issues') {
            return $this->issueController->dispatch(array_slice($path, 1));
        }

        if (!empty($path) && $path[0] === 'projects') {
            return $this->projectController->dispatch(array_slice($path, 1));
        }

        return ApiResponse::error('Route not found', 404);
    }
}
