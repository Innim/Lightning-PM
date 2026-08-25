<?php
/**
 * Изображение, передаваемое модели вместе с текстом запроса.
 *
 * Хранит двоичные данные и тип изображения; адаптер кодирует их
 * в формат конкретного провайдера. Ограничения на количество и размер
 * изображений задаёт не этот класс, а тот, кто собирает запрос.
 *
 * Тип определяется по самим данным, а не по тому, что указал вызывающий:
 * изображения часто приходят из браузера, где заявленный тип подделывается.
 *
 * Пример использования:
 * <code>
 * $request = (new AiRequest())
 *     ->addUserMessage('Что не так на скриншоте?', [AiImage::fromDataUri($dataUri)]);
 * </code>
 */
class AiImage
{
    const MIME_PNG = 'image/png';
    const MIME_JPEG = 'image/jpeg';
    const MIME_WEBP = 'image/webp';
    const MIME_GIF = 'image/gif';

    /**
     * Типы изображений, которые можно передать модели.
     * @return string[]
     */
    public static function getSupportedMimeTypes()
    {
        return [self::MIME_PNG, self::MIME_JPEG, self::MIME_WEBP, self::MIME_GIF];
    }

    /**
     * Создаёт изображение из двоичных данных.
     * @param string $data Двоичные данные изображения.
     * @return AiImage
     * @throws AiException Если данные не являются изображением
     * поддерживаемого типа.
     */
    public static function fromBinary($data)
    {
        return new self($data);
    }

    /**
     * Создаёт изображение из data URI вида `data:image/png;base64,<данные>`.
     *
     * @param string $dataUri Строка data URI.
     * @return AiImage
     * @throws AiException Если строка не является корректным data URI
     * или данные не являются изображением поддерживаемого типа.
     */
    public static function fromDataUri($dataUri)
    {
        $dataUri = (string)$dataUri;

        if (!preg_match('~^data:([\w.+-]+/[\w.+-]+)?;base64,~i', $dataUri, $matches)) {
            throw new AiException('Изображение передано не как data URI в кодировке base64');
        }

        $payload = substr($dataUri, strlen($matches[0]));

        // Пробелы появляются, если data URI прошёл через разметку или через
        // разбор формы, где `+` превращается в пробел.
        $payload = str_replace(' ', '+', $payload);

        $data = base64_decode($payload, true);
        if ($data === false || $data === '') {
            throw new AiException('Не удалось раскодировать изображение');
        }

        return new self($data);
    }

    private $_data;
    private $_mimeType;

    /**
     * @param string $data Двоичные данные изображения.
     * @throws AiException Если данные не являются изображением
     * поддерживаемого типа.
     */
    public function __construct($data)
    {
        $data = (string)$data;
        if ($data === '') {
            throw new AiException('Изображение пустое');
        }

        $info = @getimagesizefromstring($data);
        if ($info === false || empty($info['mime'])) {
            throw new AiException('Переданные данные не являются изображением');
        }

        $mimeType = strtolower($info['mime']);
        if (!in_array($mimeType, self::getSupportedMimeTypes(), true)) {
            throw new AiException('Изображение в формате ' . $mimeType . ' модели передать нельзя');
        }

        $this->_data = $data;
        $this->_mimeType = $mimeType;
    }

    /**
     * Тип изображения (одна из констант MIME_*).
     * @return string
     */
    public function getMimeType()
    {
        return $this->_mimeType;
    }

    /**
     * Двоичные данные изображения.
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Данные изображения в кодировке base64.
     * @return string
     */
    public function getBase64()
    {
        return base64_encode($this->_data);
    }

    /**
     * Размер изображения в байтах.
     * @return int
     */
    public function getSize()
    {
        return strlen($this->_data);
    }
}
