<?php
use Mailgun\Mailgun;
use Mailgun\Message;

/**
 * Реализация отправки через Mailgun.com
 *
 * На логическом уровне реализации совместима с MailSender.
 */
class MailgunSender
{
    public static function create(string $fromEmail, string $fromName = ''): self
    {
        return new self(MAILGUN_DOMAIN, MAILGUN_API_KEY, defined('MAILGUN_ENDPOINT') ? MAILGUN_ENDPOINT : '', $fromEmail, $fromName);
    }

    private $_apiKey;
    private $_domain;
    private $_endpoint;

    private $_fromEmail;
    private $_fromName;

    private $_client;

    public function __construct(string $domain, string $apiKey, string $endpoint, string $fromEmail, string $fromName = '')
    {
        $this->_domain = $domain;
        $this->_apiKey = $apiKey;
        $this->_endpoint = $endpoint;

        $this->setFrom($fromEmail, $fromName);
    }

    public function setFrom($fromEmail, $fromName)
    {
        $this->_fromEmail = $fromEmail;
        $this->_fromName = $fromName;
    }

    public function send(EmailMessage $mess): bool
    {
        $toEmail = $mess->getToEmail();
        $toName = $mess->getToName();

        $builder = new Message\MessageBuilder();
        $builder->setFromAddress($this->_fromEmail, !empty($this->_fromName) ? ['full_name' => $this->_fromName] : []);
        $builder->addToRecipient($toEmail, !empty($toName) ? ['full_name' => $toName] : []);
        $builder->setSubject($mess->getSubject());
        if ($mess->isHtml()) {
            $builder->setHtmlBody($mess->getMessage());
        } else {
            $builder->setTextBody($mess->getMessage());
        }

        try {
            $this->getClient()->messages()->send($this->_domain, $builder->getMessage());
            // TODO: записать debug лог с ответом
            return true;
        } catch (Exception $e) {
            // TODO: записать лог ошибки
            return false;
        }
    }

    private function getClient():Mailgun
    {
        if ($this->_client == null) {
            $this->_client = empty($this->_endpoint)
                ? Mailgun::create($this->_apiKey)
                : Mailgun::create($this->_apiKey, $this->_endpoint);
        }

        return $this->_client;
    }
}
