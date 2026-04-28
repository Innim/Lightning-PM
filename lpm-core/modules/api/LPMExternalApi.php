<?php

/**
 * Публичное API версии v1.
 *
 * Базовый URL: /api/v1
 */
class LPMExternalApi extends ExternalApi
{
    const UID = 'v1';

    public function __construct(LightningEngine $engine)
    {
        parent::__construct($engine, self::UID);
    }

    public function run($input)
    {
        try {
            $auth = $this->engine()->getAuth();
            if (!$auth->isApiKeyAuth()) {
                return ApiResponse::error('API key authentication required', 401)->output();
            }

            $request = new ApiRequest($this->engine(), $input);
            $router = new ApiRouter($this->engine(), $request, $this->getUrl());

            return $router->handle()->output();
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500)->output();
        }
    }
}
