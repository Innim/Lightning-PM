<?php
class LPMException extends Exception
{
    protected $statusCode = 500;
    protected $localizedMessage = 'Internal Server Error';

    public function __construct($message = null, $code = null, $localizedMessage = null, $statusCode = null, $previous = null)
    {
        if ($localizedMessage !== null) {
            $this->localizedMessage = $localizedMessage;
        }

        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }

        // Аргументы по умолчанию null, а Exception::__construct() ждёт string
        // и int - с PHP 8.1 передача null в них осуждается.
        parent::__construct($message === null ? '' : $message, $code === null ? 0 : $code, $previous);
    }

    public function getLocalizedMessage()
    {
        return $this->localizedMessage;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}