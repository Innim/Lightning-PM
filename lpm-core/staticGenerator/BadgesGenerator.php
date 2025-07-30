<?php

/**
 * Генератор бэйджей.
 */
class BadgesGenerator extends StaticGenerator
{
    private $_id;

    public function __construct(LightningEngine $engine, $id)
    {
        parent::__construct($engine);
        $this->_id = $id;
    }

    public function getHeaders()
    {
        return [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ];
    }

    public function generate()
    {
        $badge = Badge::load($this->_id);
        if (empty($badge)) {
            // TODO: status 404
            throw new Exception('Badge not found');
        }

        switch ($badge->type) {
            case Badge::TYPE_PIPELINE:
                $data = $this->dataForPipelineBadge(
                    $badge->label,
                    $badge->gitlabProjectId,
                    $badge->gitlabRef
                );
                break;
            default:
                throw new Exception('Unknown badge type: ' . $badge->type);
        }

        extract($data);

        // Generate Shields.io badge URL
        $shieldsUrl = 'https://img.shields.io/badge/' .
            rawurlencode($label) . '-' .
            rawurlencode($message) . '-' .
            rawurlencode($color) . '.svg';

        // Fetch image
        $badgeSvg = file_get_contents($shieldsUrl);
        if ($badgeSvg === false) {
            //http_response_code(502);
            // TODO: pass http response code in exception
            throw new Exception('Failed to fetch badge from Shields.io');
        }

        // Serve SVG image
        return $badgeSvg;
    }

    private function dataForPipelineBadge($label, $gitlabProjectId, $gitlabRef)
    {
        $pipeline = $this->loadPipelineData($gitlabProjectId, $gitlabRef);
        $label = empty($label) ? 'build' : $label;

        if ($pipeline === null) {
            $message = 'n/a';
            $color = 'lightgrey';
        } else {
            switch ($pipeline->status) {
                case GitlabPipeline::STATUS_SUCCESS:
                    $message = 'success';
                    $color = 'green';
                    break;
                case GitlabPipeline::STATUS_FAILED:
                    $message = 'failed';
                    $color = 'red';
                    break;
                case GitlabPipeline::STATUS_RUNNING:
                    $message = 'running';
                    $color = 'blue';
                    break;
                default:
                    $message = 'unknown';
                    $color = 'lightgrey';
            }
        }

        return compact('label', 'message', 'color');
    }

    private function loadPipelineData($gitlabProjectId, $ref)
    {
        $gitlab = $this->_engine->gitlab();
        $pipeline = $gitlab->getLatestPipeline($gitlabProjectId, $ref, true);
        return $pipeline;
    }
}
