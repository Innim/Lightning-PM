<?php

/**
 * Ошибка обработки запроса API с известным кодом ответа HTTP.
 *
 * Обычное исключение отдаётся клиенту как 500. Это - для случаев, когда запрос
 * отвергается по вине клиента и код ответа обязан это показывать.
 */
class ApiException extends Exception
{
    private $statusCode;

    /**
     * @param string $message    Текст ошибки для клиента.
     * @param int    $statusCode Код ответа HTTP.
     */
    public function __construct($message, $statusCode)
    {
        parent::__construct($message);
        $this->statusCode = (int)$statusCode;
    }

    /**
     * Код ответа HTTP, с которым отдаётся ошибка.
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
