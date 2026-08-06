<?php
/**
 * Ответ ИИ-модели.
 */
class AiResponse
{
    /** Модель завершила ответ сама */
    const FINISH_STOP = 'stop';
    /** Ответ оборван из-за ограничения на длину */
    const FINISH_LENGTH = 'length';
    /** Ответ заблокирован фильтрами провайдера */
    const FINISH_FILTER = 'filter';
    /** Причина завершения неизвестна */
    const FINISH_OTHER = 'other';

    private $_text;
    private $_model;
    private $_finishReason;
    private $_usage;
    private $_raw;

    /**
     * @param string $text Текст ответа.
     * @param string $model Модель, которая сформировала ответ.
     * @param string $finishReason Причина завершения (одна из констант FINISH_*).
     * @param AiUsage $usage Расход токенов, если провайдер его сообщил.
     * @param array $raw Ответ API провайдера как есть.
     */
    public function __construct(
        $text,
        $model,
        $finishReason = self::FINISH_STOP,
        ?AiUsage $usage = null,
        array $raw = []
    ) {
        $this->_text = (string)$text;
        $this->_model = (string)$model;
        $this->_finishReason = $finishReason;
        $this->_usage = $usage;
        $this->_raw = $raw;
    }

    /**
     * Текст ответа.
     * @return string
     */
    public function getText()
    {
        return $this->_text;
    }

    /**
     * Пуст ли ответ модели.
     * @return bool
     */
    public function isEmpty()
    {
        return trim($this->_text) === '';
    }

    /**
     * Модель, сформировавшая ответ.
     * @return string
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * Причина завершения ответа (одна из констант FINISH_*).
     * @return string
     */
    public function getFinishReason()
    {
        return $this->_finishReason;
    }

    /**
     * Завершила ли модель ответ самостоятельно.
     * Если нет — ответ может быть неполным.
     * @return bool
     */
    public function isComplete()
    {
        return $this->_finishReason === self::FINISH_STOP;
    }

    /**
     * Расход токенов или null, если провайдер его не сообщил.
     * @return AiUsage|null
     */
    public function getUsage()
    {
        return $this->_usage;
    }

    /**
     * Ответ API провайдера как есть — для отладки и разбора
     * специфичных для провайдера данных.
     * @return array
     */
    public function getRaw()
    {
        return $this->_raw;
    }

    /**
     * Разбирает ответ модели как JSON.
     *
     * Имеет смысл для запросов с AiRequest::setJsonResponse()
     * или AiRequest::setResponseSchema().
     *
     * @return array Данные ответа.
     * @throws AiException Если ответ не является корректным JSON.
     */
    public function getJson()
    {
        $text = trim($this->_text);

        // модель может обернуть JSON в markdown-блок кода
        if (strpos($text, '```') === 0) {
            $text = preg_replace('/^```[a-z]*\s*/i', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
        }

        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new AiException('Модель вернула ответ, который не является JSON');
        }

        return $data;
    }
}
