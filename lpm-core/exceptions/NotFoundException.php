<?php
class NotFoundException extends LPMException
{
    public static function withMessage($localizedMessage, $message = null)
    {
        return new self($message ?: $localizedMessage, null, $localizedMessage);
    }

    public function __construct($message = null, $code = null, $localizedMessage = null, $previous = null)
    {
        parent::__construct($message, $code, empty($localizedMessage) ? 'Not found' : $localizedMessage, 404, $previous);
    }
}