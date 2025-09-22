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

        parent::__construct($message, $code, $previous);
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