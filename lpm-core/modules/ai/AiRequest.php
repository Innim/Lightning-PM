<?php
/**
 * Запрос к ИИ-модели.
 *
 * Описывает диалог (одно или несколько сообщений), системную инструкцию
 * и параметры генерации в терминах, не зависящих от провайдера.
 * Адаптер переводит их в формат конкретного API.
 *
 * Сеттеры возвращают сам объект, что позволяет собирать запрос цепочкой:
 * <code>
 * $response = $adapter->generate(
 *     AiRequest::text('Составь план задачи')
 *         ->setSystemInstruction('Отвечай кратко, на русском')
 *         ->setTemperature(0.2)
 * );
 * </code>
 */
class AiRequest
{
    /**
     * Создаёт запрос из одного сообщения пользователя.
     * @param string $prompt Текст запроса.
     * @param string $systemInstruction Системная инструкция (необязательно).
     * @return AiRequest
     */
    public static function text($prompt, $systemInstruction = '')
    {
        $request = new self([AiMessage::user($prompt)]);

        if ($systemInstruction !== '') {
            $request->setSystemInstruction($systemInstruction);
        }

        return $request;
    }

    /** @var AiMessage[] */
    private $_messages = [];
    private $_systemInstruction = '';
    private $_model = '';
    private $_temperature;
    private $_topP;
    private $_maxOutputTokens;
    private $_jsonResponse = false;
    private $_responseSchema;

    /**
     * @param AiMessage[] $messages Сообщения диалога в хронологическом порядке.
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $message) {
            $this->addMessage($message);
        }
    }

    /**
     * Добавляет сообщение в конец диалога.
     * @param AiMessage $message Сообщение.
     * @return AiRequest
     */
    public function addMessage(AiMessage $message)
    {
        $this->_messages[] = $message;
        return $this;
    }

    /**
     * Добавляет сообщение пользователя в конец диалога.
     * @param string $text Текст сообщения; может быть пустым,
     * если передано хотя бы одно изображение.
     * @param AiImage[] $images Изображения, прилагаемые к сообщению.
     * @return AiRequest
     */
    public function addUserMessage($text, array $images = [])
    {
        return $this->addMessage(AiMessage::user($text, $images));
    }

    /**
     * Добавляет в конец диалога сообщение от лица модели
     * (для передачи истории переписки).
     * @param string $text Текст сообщения.
     * @return AiRequest
     */
    public function addAssistantMessage($text)
    {
        return $this->addMessage(AiMessage::assistant($text));
    }

    /**
     * Сообщения диалога в хронологическом порядке.
     * @return AiMessage[]
     */
    public function getMessages()
    {
        return $this->_messages;
    }

    /**
     * Есть ли в запросе изображения — т.е. требуется ли от модели
     * умение их читать.
     * @return bool
     */
    public function hasImages()
    {
        foreach ($this->_messages as $message) {
            if ($message->hasImages()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Задаёт системную инструкцию — описание роли и правил ответа,
     * которое не является частью диалога.
     * @param string $instruction Текст инструкции.
     * @return AiRequest
     */
    public function setSystemInstruction($instruction)
    {
        $this->_systemInstruction = (string)$instruction;
        return $this;
    }

    /**
     * Системная инструкция, пустая строка — если не задана.
     * @return string
     */
    public function getSystemInstruction()
    {
        return $this->_systemInstruction;
    }

    /**
     * Задаёт модель для этого запроса, переопределяя модель адаптера.
     * @param string $model Название модели в терминах провайдера.
     * @return AiRequest
     */
    public function setModel($model)
    {
        $this->_model = (string)$model;
        return $this;
    }

    /**
     * Модель для этого запроса, пустая строка — если используется модель адаптера.
     * @return string
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * Задаёт температуру — степень случайности ответа.
     * Чем меньше значение, тем более предсказуем результат.
     * @param float $temperature Обычно в диапазоне от 0 до 1.
     * @return AiRequest
     */
    public function setTemperature($temperature)
    {
        $this->_temperature = $temperature === null ? null : (float)$temperature;
        return $this;
    }

    /**
     * Температура или null, если используется значение по умолчанию.
     * @return float|null
     */
    public function getTemperature()
    {
        return $this->_temperature;
    }

    /**
     * Задаёт top-p — долю наиболее вероятных вариантов, из которых
     * выбирается следующий фрагмент ответа.
     * @param float $topP Значение от 0 до 1.
     * @return AiRequest
     */
    public function setTopP($topP)
    {
        $this->_topP = $topP === null ? null : (float)$topP;
        return $this;
    }

    /**
     * Top-p или null, если используется значение по умолчанию.
     * @return float|null
     */
    public function getTopP()
    {
        return $this->_topP;
    }

    /**
     * Ограничивает длину ответа модели.
     *
     * В лимит входят и токены, которые модель тратит на рассуждения,
     * поэтому при слишком малом значении ответ может оказаться пустым:
     * AiResponse::getText() вернёт пустую строку, а причиной завершения
     * будет AiResponse::FINISH_LENGTH.
     *
     * @param int $tokens Максимальное количество токенов в ответе.
     * @return AiRequest
     */
    public function setMaxOutputTokens($tokens)
    {
        $this->_maxOutputTokens = $tokens === null ? null : (int)$tokens;
        return $this;
    }

    /**
     * Ограничение длины ответа или null, если не задано.
     * @return int|null
     */
    public function getMaxOutputTokens()
    {
        return $this->_maxOutputTokens;
    }

    /**
     * Требует, чтобы модель вернула ответ в формате JSON.
     * Разобрать такой ответ можно через AiResponse::getJson().
     * @param bool $enabled
     * @return AiRequest
     */
    public function setJsonResponse($enabled = true)
    {
        $this->_jsonResponse = (bool)$enabled;
        return $this;
    }

    /**
     * Требуется ли ответ в формате JSON.
     * @return bool
     */
    public function isJsonResponse()
    {
        return $this->_jsonResponse || $this->_responseSchema !== null;
    }

    /**
     * Задаёт схему ответа: модель вернёт JSON, соответствующий ей.
     *
     * Схема описывается в подмножестве JSON Schema, которое понимают
     * модели: поддерживаются `type`, `description`, `enum`, `items`,
     * `properties`, `required`, `anyOf`, `nullable`. Служебные ключи вроде
     * `$schema` или `additionalProperties` использовать нельзя.
     * Названия типов задаются в нотации JSON Schema (`object`, `string`);
     * к виду, принятому у провайдера, их приводит адаптер.
     *
     * @param array $schema Схема ответа или null, чтобы убрать ограничение.
     * @return AiRequest
     */
    public function setResponseSchema(?array $schema = null)
    {
        $this->_responseSchema = $schema;
        return $this;
    }

    /**
     * Схема ответа или null, если она не задана.
     * @return array|null
     */
    public function getResponseSchema()
    {
        return $this->_responseSchema;
    }

    /**
     * Проверяет, что запрос заполнен корректно.
     * @throws AiException Если в запросе нет ни одного сообщения.
     */
    public function validate()
    {
        if (empty($this->_messages)) {
            throw new AiException('Запрос к модели не содержит сообщений');
        }
    }
}
