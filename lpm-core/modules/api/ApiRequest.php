<?php

class ApiRequest
{
    private $engine;
    private $method;
    private $path;
    private $query;
    private $body;

    public function __construct(LightningEngine $engine, $input)
    {
        $this->engine = $engine;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD']);
        $this->path = array_values($engine->getParams()->getArgs());
        $this->query = ApiKey::getQueryArgs();
        $this->body = $this->decodeBody($input);
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getQuery($name, $default = null)
    {
        return array_key_exists($name, $this->query) ? $this->query[$name] : $default;
    }

    public function getBody($name, $default = null)
    {
        return array_key_exists($name, $this->body) ? $this->body[$name] : $default;
    }

    private function decodeBody($input)
    {
        $input = trim((string)$input);
        if ($input === '') {
            return [];
        }

        $data = json_decode($input, true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON body');
        }

        return $data;
    }
}
