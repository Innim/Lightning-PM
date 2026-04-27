<?php

class ApiResponse
{
    private $statusCode;
    private $payload;

    private function __construct($payload, $statusCode)
    {
        $this->payload = $payload;
        $this->statusCode = (int)$statusCode;
    }

    public static function success($data, $statusCode = 200)
    {
        return new self([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    public static function error($message, $statusCode)
    {
        return new self([
            'success' => false,
            'error' => $message,
        ], $statusCode);
    }

    public function output()
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($this->payload);
    }
}
