<?php
/**
 * Простое внешнее API для проверки статуса приложения (healthcheck).
 *
 * URL: /api/status
 */
class StatusExternalApi extends ExternalApi
{
    const UID = 'status';

    public function __construct(LightningEngine $engine)
    {
        parent::__construct($engine, self::UID);
    }

    /**
     * Возвращает JSON со статусом приложения.
     * @param string $input
     * @return string
     */
    public function run($input)
    {
        // Готовим базовый ответ
        $data = array(
            'status' => 'ok',
            'version' => defined('VERSION') ? VERSION : null,
            'time' => date('c'),
            'debug' => LPMGlobals::isDebugMode(),
        );

        // Попробуем быстро проверить доступность БД (не критично)
        $dbStatus = 'unknown';
        try {
            $db = LPMGlobals::getInstance()->getDBConnect();
            if ($db) {
                // @ suppress to avoid warnings leaking into output
                $dbStatus = @$db->ping() ? 'ok' : 'error';
            }
        } catch (Exception $e) {
            $dbStatus = 'error';
        }
        $data['db'] = $dbStatus;

        // Время обработки текущего запроса в миллисекундах (float, без округления)
        $requestTimeMs = $this->engine()->getExecutionTimeSec() * 1000;
        $data['request_time_ms'] = $requestTimeMs;

        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data);
    }
}
