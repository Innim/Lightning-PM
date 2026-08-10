<?php
/**
 * Интеграция с ИИ-моделями.
 *
 * Единая точка доступа к адаптерам моделей: выдаёт адаптер провайдера
 * по имени, а также адаптер, выбранный в настройках приложения.
 * Провайдер задаётся константой AI_PROVIDER, параметры доступа —
 * константами вида AI_<PROVIDER>_* (см. lpm-config.inc.template.php).
 *
 * Пример использования:
 * <code>
 * $ai = AiIntegration::getInstance();
 * if ($ai->isAvailable()) {
 *     $response = $ai->getAdapter()->generate(AiRequest::text('Привет!'));
 *     echo $response->getText();
 * }
 * </code>
 */
class AiIntegration
{
    /** Google Gemini */
    const PROVIDER_GEMINI = 'gemini';

    /** Провайдер, используемый, если он не задан в настройках */
    const DEFAULT_PROVIDER = self::PROVIDER_GEMINI;

    private static $_instance;

    /**
     * @return AiIntegration
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new AiIntegration(
                defined('AI_PROVIDER') && AI_PROVIDER !== '' ? AI_PROVIDER : self::DEFAULT_PROVIDER
            );
        }

        return self::$_instance;
    }

    /**
     * Список всех поддерживаемых провайдеров.
     * @return string[]
     */
    public static function getProviders()
    {
        return [self::PROVIDER_GEMINI];
    }

    /**
     * Настроенный таймаут обращения к модели в секундах
     * или 0, если он не задан и используется значение адаптера.
     * @return int
     */
    public static function getRequestTimeout()
    {
        return defined('AI_REQUEST_TIMEOUT') ? max(0, (int)AI_REQUEST_TIMEOUT) : 0;
    }

    private $_defaultProvider;
    /** @var AiAdapter[] */
    private $_adapters = [];

    /**
     * @param string $defaultProvider Провайдер, используемый по умолчанию
     * (одна из констант PROVIDER_*).
     */
    public function __construct($defaultProvider)
    {
        $this->_defaultProvider = $defaultProvider;
    }

    /**
     * Провайдер, используемый по умолчанию.
     * @return string
     */
    public function getDefaultProvider()
    {
        return $this->_defaultProvider;
    }

    /**
     * Настроена ли интеграция, т.е. можно ли обращаться к модели.
     * @param string $provider Провайдер; если не задан — используется провайдер по умолчанию.
     * @return bool
     */
    public function isAvailable($provider = '')
    {
        try {
            return $this->getAdapter($provider)->isConfigured();
        } catch (AiException $e) {
            return false;
        }
    }

    /**
     * Возвращает адаптер провайдера.
     *
     * Адаптер создаётся один раз и переиспользуется.
     *
     * @param string $provider Провайдер (одна из констант PROVIDER_*);
     * если не задан — используется провайдер по умолчанию.
     * @return AiAdapter
     * @throws AiException Если провайдер не поддерживается.
     */
    public function getAdapter($provider = '')
    {
        if ($provider === '') {
            $provider = $this->_defaultProvider;
        }

        if (!isset($this->_adapters[$provider])) {
            $this->_adapters[$provider] = $this->createAdapter($provider);
        }

        return $this->_adapters[$provider];
    }

    /**
     * Создаёт адаптер провайдера по настройкам приложения.
     * @param string $provider Провайдер.
     * @return AiAdapter
     * @throws AiException Если провайдер не поддерживается.
     */
    private function createAdapter($provider)
    {
        switch ($provider) {
            case self::PROVIDER_GEMINI:
                return GeminiAdapter::create();
            default:
                throw new AiException('Неизвестный провайдер ИИ: ' . $provider);
        }
    }
}
