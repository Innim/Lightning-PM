<?php
/**
 * Сообщение диалога с ИИ-моделью.
 */
class AiMessage
{
    /** Сообщение пользователя */
    const ROLE_USER = 'user';
    /** Ответ модели */
    const ROLE_ASSISTANT = 'assistant';

    /**
     * Создаёт сообщение пользователя.
     * @param string $text Текст сообщения.
     * @return AiMessage
     */
    public static function user($text)
    {
        return new self(self::ROLE_USER, $text);
    }

    /**
     * Создаёт сообщение от лица модели
     * (используется для передачи истории диалога).
     * @param string $text Текст сообщения.
     * @return AiMessage
     */
    public static function assistant($text)
    {
        return new self(self::ROLE_ASSISTANT, $text);
    }

    private $_role;
    private $_text;

    /**
     * @param string $role Роль автора сообщения (одна из констант ROLE_*).
     * @param string $text Текст сообщения.
     * @throws AiException Если роль неизвестна или текст пуст.
     */
    public function __construct($role, $text)
    {
        if ($role !== self::ROLE_USER && $role !== self::ROLE_ASSISTANT) {
            throw new AiException('Неизвестная роль сообщения: ' . $role);
        }

        $text = (string)$text;
        if (trim($text) === '') {
            throw new AiException('Текст сообщения не может быть пустым');
        }

        $this->_role = $role;
        $this->_text = $text;
    }

    /**
     * Роль автора сообщения (одна из констант ROLE_*).
     * @return string
     */
    public function getRole()
    {
        return $this->_role;
    }

    /**
     * Текст сообщения.
     * @return string
     */
    public function getText()
    {
        return $this->_text;
    }
}
