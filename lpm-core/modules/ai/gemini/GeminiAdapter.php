<?php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

/**
 * Адаптер для работы с моделями Google Gemini
 * через Generative Language API.
 *
 * Требует ключ API, который выдаётся в Google AI Studio.
 */
class GeminiAdapter implements AiAdapter
{
    /** Базовый адрес API по умолчанию */
    const DEFAULT_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta';
    /** Модель, используемая, если она не задана явно */
    const DEFAULT_MODEL = 'gemini-3.6-flash';
    /** Таймаут запроса к API по умолчанию, в секундах */
    const DEFAULT_TIMEOUT = 60;

    /**
     * Создаёт адаптер по настройкам приложения
     * (константы AI_GEMINI_* в конфигурации).
     * @return GeminiAdapter
     */
    public static function create()
    {
        $adapter = new self(
            defined('AI_GEMINI_API_KEY') ? AI_GEMINI_API_KEY : '',
            defined('AI_GEMINI_MODEL') ? AI_GEMINI_MODEL : '',
            defined('AI_GEMINI_ENDPOINT') ? AI_GEMINI_ENDPOINT : '',
            defined('AI_REQUEST_TIMEOUT') ? AI_REQUEST_TIMEOUT : 0
        );

        if (defined('AI_GEMINI_THINKING_BUDGET')) {
            $adapter->setThinkingBudget(AI_GEMINI_THINKING_BUDGET);
        }

        return $adapter;
    }

    private $_apiKey;
    private $_model;
    private $_endpoint;
    private $_timeout;
    private $_thinkingBudget;

    private $_client;

    /**
     * @param string $apiKey Ключ доступа к API.
     * @param string $model Модель — короткое имя (`gemini-2.5-flash`) или полное имя
     * ресурса (`models/gemini-2.5-flash`); если не задана, используется DEFAULT_MODEL.
     * @param string $endpoint Базовый адрес API; если не задан, используется DEFAULT_ENDPOINT.
     * @param int $timeout Таймаут запроса в секундах; если не задан, используется DEFAULT_TIMEOUT.
     */
    public function __construct($apiKey, $model = '', $endpoint = '', $timeout = 0)
    {
        $this->_apiKey = trim((string)$apiKey);
        $this->_model = trim((string)$model) === '' ? self::DEFAULT_MODEL : trim((string)$model);
        $this->_endpoint = rtrim(trim((string)$endpoint) === '' ? self::DEFAULT_ENDPOINT : trim($endpoint), '/');
        $this->_timeout = (int)$timeout > 0 ? (int)$timeout : self::DEFAULT_TIMEOUT;
    }

    /**
     * Ограничивает количество токенов, которые модель тратит на рассуждения.
     *
     * Значение -1 отдаёт выбор модели, ноль отключает рассуждения — но
     * нулевой лимит принимают не все модели, часть отклоняет такой запрос
     * с ошибкой. Настройка поддерживается моделями 2.5 и новее.
     *
     * @param int $budget Лимит токенов или null, чтобы не задавать его.
     * @return GeminiAdapter
     */
    public function setThinkingBudget($budget)
    {
        $this->_thinkingBudget = $budget === null || $budget === '' ? null : (int)$budget;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getProviderName()
    {
        return AiIntegration::PROVIDER_GEMINI;
    }

    /**
     * @inheritDoc
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * @inheritDoc
     */
    public function isConfigured()
    {
        return $this->_apiKey !== '';
    }

    /**
     * @inheritDoc
     */
    public function generate(AiRequest $request)
    {
        if (!$this->isConfigured()) {
            throw new AiException('Интеграция с Gemini не настроена: не задан ключ API');
        }

        $request->validate();

        $model = $request->getModel() === '' ? $this->_model : $request->getModel();
        $payload = $this->buildPayload($request);

        LPMLog::debug('Запрос к Gemini', LPMLog::CH_AI, [
            'model' => $model,
            'payload' => $payload,
        ]);

        $data = $this->send($model, $payload);
        $response = $this->parseResponse($data, $model);

        LPMLog::info('Получен ответ Gemini', LPMLog::CH_AI, [
            'model' => $response->getModel(),
            'finishReason' => $response->getFinishReason(),
            'usage' => $response->getUsage() === null ? null : $response->getUsage()->toArray(),
        ]);

        return $response;
    }

    /**
     * Собирает тело запроса в формате Generative Language API.
     * @param AiRequest $request Запрос к модели.
     * @return array
     */
    private function buildPayload(AiRequest $request)
    {
        $contents = [];
        foreach ($request->getMessages() as $message) {
            $contents[] = [
                'role' => $message->getRole() === AiMessage::ROLE_ASSISTANT ? 'model' : 'user',
                'parts' => [['text' => $message->getText()]],
            ];
        }

        $payload = ['contents' => $contents];

        $systemInstruction = $request->getSystemInstruction();
        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        $config = [];
        if ($request->getTemperature() !== null) {
            $config['temperature'] = $request->getTemperature();
        }
        if ($request->getTopP() !== null) {
            $config['topP'] = $request->getTopP();
        }
        if ($request->getMaxOutputTokens() !== null) {
            $config['maxOutputTokens'] = $request->getMaxOutputTokens();
        }
        if ($request->isJsonResponse()) {
            $config['responseMimeType'] = 'application/json';
        }
        if ($request->getResponseSchema() !== null) {
            $config['responseSchema'] = $this->convertSchema($request->getResponseSchema());
        }
        if ($this->_thinkingBudget !== null) {
            $config['thinkingConfig'] = ['thinkingBudget' => $this->_thinkingBudget];
        }

        if (!empty($config)) {
            $payload['generationConfig'] = $config;
        }

        return $payload;
    }

    /**
     * Переводит схему ответа в формат, который ожидает Gemini.
     *
     * Названия типов в Gemini записываются в верхнем регистре
     * (`OBJECT`, `ARRAY`, `STRING`), тогда как в схеме запроса они
     * задаются в нотации JSON Schema. Остальные ключи схемы,
     * включая значения `enum`, передаются как есть.
     *
     * @param array $schema Схема ответа.
     * @return array
     */
    private function convertSchema(array $schema)
    {
        $result = [];

        foreach ($schema as $key => $value) {
            switch ($key) {
                case 'type':
                    $result[$key] = is_string($value) ? strtoupper($value) : $value;
                    break;
                case 'properties':
                    $properties = [];
                    foreach ((array)$value as $name => $property) {
                        $properties[$name] = is_array($property) ? $this->convertSchema($property) : $property;
                    }
                    $result[$key] = $properties;
                    break;
                case 'items':
                    $result[$key] = is_array($value) ? $this->convertSchema($value) : $value;
                    break;
                case 'anyOf':
                    $variants = [];
                    foreach ((array)$value as $variant) {
                        $variants[] = is_array($variant) ? $this->convertSchema($variant) : $variant;
                    }
                    $result[$key] = $variants;
                    break;
                default:
                    $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Формирует путь к модели для адреса запроса.
     *
     * Модель можно задавать как коротким именем (`gemini-2.5-flash`),
     * так и полным именем ресурса, которое возвращает API
     * (`models/gemini-2.5-flash`, `tunedModels/my-model`).
     *
     * @param string $model Модель.
     * @return string Путь вида `models/gemini-2.5-flash`.
     * @throws AiException Если модель не задана.
     */
    private function getModelPath($model)
    {
        $segments = array_values(array_filter(explode('/', trim((string)$model)), function ($segment) {
            return $segment !== '';
        }));

        if (empty($segments)) {
            throw new AiException('Не задана модель Gemini');
        }

        // короткое имя без коллекции — это модель
        if (count($segments) === 1) {
            array_unshift($segments, 'models');
        }

        return implode('/', array_map('rawurlencode', $segments));
    }

    /**
     * Выполняет обращение к API и возвращает разобранный ответ.
     * @param string $model Модель.
     * @param array $payload Тело запроса.
     * @return array Данные ответа API.
     * @throws AiException Если запрос не удался или ответ не разобран.
     */
    private function send($model, array $payload)
    {
        $url = $this->_endpoint . '/' . $this->getModelPath($model) . ':generateContent';

        try {
            $response = $this->getClient()->request('POST', $url, [
                'headers' => [
                    'x-goog-api-key' => $this->_apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpClientExceptionInterface $e) {
            LPMLog::exception($e, LPMLog::CH_AI, ['model' => $model]);
            throw new AiException('Не удалось обратиться к Gemini: ' . $e->getMessage(), 0, null, null, $e);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            LPMLog::error('Gemini вернул неразбираемый ответ', LPMLog::CH_AI, [
                'model' => $model,
                'statusCode' => $statusCode,
                'content' => mb_substr((string)$content, 0, 1000),
            ]);
            throw new AiException('Gemini вернул некорректный ответ');
        }

        if ($statusCode >= 400) {
            $message = isset($data['error']['message']) ? $data['error']['message'] : 'код ' . $statusCode;
            LPMLog::error('Ошибка запроса к Gemini', LPMLog::CH_AI, [
                'model' => $model,
                'statusCode' => $statusCode,
                'error' => $message,
            ]);
            throw new AiException('Gemini вернул ошибку: ' . $message, 0, null, $statusCode);
        }

        return $data;
    }

    /**
     * Преобразует ответ API в объект ответа модели.
     * @param array $data Данные ответа API.
     * @param string $model Запрошенная модель.
     * @return AiResponse
     * @throws AiException Если запрос был отклонён и ответа нет.
     */
    private function parseResponse(array $data, $model)
    {
        $candidate = isset($data['candidates'][0]) ? $data['candidates'][0] : null;
        if ($candidate === null) {
            $blockReason = isset($data['promptFeedback']['blockReason'])
                ? $data['promptFeedback']['blockReason']
                : '';
            LPMLog::warning('Gemini не вернул ответ', LPMLog::CH_AI, [
                'model' => $model,
                'blockReason' => $blockReason,
            ]);
            throw new AiException($blockReason === ''
                ? 'Gemini не вернул ответ'
                : 'Запрос отклонён Gemini: ' . $blockReason);
        }

        $text = '';
        if (isset($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        return new AiResponse(
            $text,
            isset($data['modelVersion']) ? $data['modelVersion'] : $model,
            $this->mapFinishReason(isset($candidate['finishReason']) ? $candidate['finishReason'] : ''),
            $this->parseUsage(isset($data['usageMetadata']) ? $data['usageMetadata'] : []),
            $data
        );
    }

    /**
     * Приводит причину завершения ответа к константам AiResponse::FINISH_*.
     * @param string $reason Причина в терминах Gemini.
     * @return string
     */
    private function mapFinishReason($reason)
    {
        switch ($reason) {
            case '':
            case 'STOP':
                return AiResponse::FINISH_STOP;
            case 'MAX_TOKENS':
                return AiResponse::FINISH_LENGTH;
            case 'SAFETY':
            case 'RECITATION':
            case 'BLOCKLIST':
            case 'PROHIBITED_CONTENT':
            case 'SPII':
                return AiResponse::FINISH_FILTER;
            default:
                return AiResponse::FINISH_OTHER;
        }
    }

    /**
     * Разбирает статистику расхода токенов.
     * @param array $usage Блок usageMetadata ответа API.
     * @return AiUsage|null
     */
    private function parseUsage(array $usage)
    {
        if (empty($usage)) {
            return null;
        }

        return new AiUsage(
            isset($usage['promptTokenCount']) ? $usage['promptTokenCount'] : 0,
            isset($usage['candidatesTokenCount']) ? $usage['candidatesTokenCount'] : 0,
            isset($usage['thoughtsTokenCount']) ? $usage['thoughtsTokenCount'] : 0,
            isset($usage['totalTokenCount']) ? $usage['totalTokenCount'] : 0
        );
    }

    private function getClient()
    {
        if ($this->_client === null) {
            $this->_client = HttpClient::create(['timeout' => $this->_timeout]);
        }

        return $this->_client;
    }
}
