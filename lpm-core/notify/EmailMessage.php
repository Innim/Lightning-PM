<?php
class EmailMessage
{
    private $_toEmail;
    private $_toName;
    private $_subject;
    private $_message;
    private $_isHtml;

    public function __construct(string $subject, string $message, string $toEmail, string $toName = '', bool $isHtml = false)
    {
        $this->setTo($toEmail, $toName);
        $this->setMessage($subject, $message, $isHtml);
    }

    public function setTo(string $email, string $name = ''): self
    {
        $this->_toEmail = $email;
        $this->_toName = $name;
        return $this;
    }

    public function getToEmail(): string
    {
        return $this->_toEmail;
    }

    public function getToName(): string
    {
        return $this->_toName;
    }

    public function setMessage(string $subject, string $message, bool $isHtml = false): self
    {
        $this->_subject = $subject;
        $this->_message = $message;
        $this->_isHtml = $isHtml;
        return $this;
    }

    public function getSubject(): string
    {
        return $this->_subject;
    }

    public function getMessage(): string
    {
        return $this->_message;
    }

    public function isHtml(): string
    {
        return $this->_isHtml;
    }
}
