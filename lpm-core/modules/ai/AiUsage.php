<?php
/**
 * Расход токенов на запрос к ИИ-модели.
 */
class AiUsage
{
    private $_promptTokens;
    private $_completionTokens;
    private $_reasoningTokens;
    private $_totalTokens;

    /**
     * @param int $promptTokens Токены запроса.
     * @param int $completionTokens Токены ответа.
     * @param int $reasoningTokens Токены, потраченные моделью на рассуждения.
     * @param int $totalTokens Всего токенов; если не задано — сумма остальных.
     */
    public function __construct($promptTokens, $completionTokens, $reasoningTokens = 0, $totalTokens = 0)
    {
        $this->_promptTokens = (int)$promptTokens;
        $this->_completionTokens = (int)$completionTokens;
        $this->_reasoningTokens = (int)$reasoningTokens;
        $this->_totalTokens = (int)$totalTokens > 0
            ? (int)$totalTokens
            : $this->_promptTokens + $this->_completionTokens + $this->_reasoningTokens;
    }

    /**
     * Количество токенов в запросе.
     * @return int
     */
    public function getPromptTokens()
    {
        return $this->_promptTokens;
    }

    /**
     * Количество токенов в ответе.
     * @return int
     */
    public function getCompletionTokens()
    {
        return $this->_completionTokens;
    }

    /**
     * Количество токенов, потраченных моделью на рассуждения.
     * Ноль, если модель не рассуждает или провайдер не сообщает это значение.
     * @return int
     */
    public function getReasoningTokens()
    {
        return $this->_reasoningTokens;
    }

    /**
     * Общее количество токенов, потраченных на запрос.
     * @return int
     */
    public function getTotalTokens()
    {
        return $this->_totalTokens;
    }

    /**
     * Представление расхода токенов в виде ассоциативного массива.
     * @return array
     */
    public function toArray()
    {
        return [
            'prompt' => $this->_promptTokens,
            'completion' => $this->_completionTokens,
            'reasoning' => $this->_reasoningTokens,
            'total' => $this->_totalTokens,
        ];
    }
}
