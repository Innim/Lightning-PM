<?php
/**
 * Сообщение диалога с ИИ-моделью.
 *
 * Кроме текста сообщение может нести изображения ({@see AiImage}) —
 * их получает модель вместе с текстом. Сообщение без текста допустимо,
 * если в нём есть хотя бы одно изображение.
 */
class AiMessage
{
    /** Сообщение пользователя */
    const ROLE_USER = 'user';
    /** Ответ модели */
    const ROLE_ASSISTANT = 'assistant';

    /**
     * Создаёт сообщение пользователя.
     * @param string $text Текст сообщения; может быть пустым,
     * если передано хотя бы одно изображение.
     * @param AiImage[] $images Изображения, прилагаемые к сообщению.
     * @return AiMessage
     */
    public static function user($text, array $images = [])
    {
        return new self(self::ROLE_USER, $text, $images);
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
    /** @var AiImage[] */
    private $_images = [];

    /**
     * @param string $role Роль автора сообщения (одна из констант ROLE_*).
     * @param string $text Текст сообщения; может быть пустым,
     * если передано хотя бы одно изображение.
     * @param AiImage[] $images Изображения, прилагаемые к сообщению.
     * @throws AiException Если роль неизвестна или сообщение пусто,
     * т.е. в нём нет ни текста, ни изображений.
     */
    public function __construct($role, $text, array $images = [])
    {
        if ($role !== self::ROLE_USER && $role !== self::ROLE_ASSISTANT) {
            throw new AiException('Неизвестная роль сообщения: ' . $role);
        }

        foreach ($images as $image) {
            $this->addImage($image);
        }

        $text = (string)$text;
        if (trim($text) === '' && empty($this->_images)) {
            throw new AiException('Сообщение не содержит ни текста, ни изображений');
        }

        $this->_role = $role;
        $this->_text = $text;
    }

    /**
     * Прилагает изображение к сообщению.
     * @param AiImage $image Изображение.
     * @return AiMessage
     */
    public function addImage(AiImage $image)
    {
        $this->_images[] = $image;
        return $this;
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
     * Текст сообщения; пустая строка, если сообщение состоит
     * только из изображений.
     * @return string
     */
    public function getText()
    {
        return $this->_text;
    }

    /**
     * Изображения, прилагаемые к сообщению.
     * @return AiImage[]
     */
    public function getImages()
    {
        return $this->_images;
    }

    /**
     * Есть ли в сообщении изображения.
     * @return bool
     */
    public function hasImages()
    {
        return !empty($this->_images);
    }
}
