<?php
/**
 * Заголовки безопасности, общие для всех HTML-страниц приложения.
 *
 * Значения по умолчанию заданы в `lpm-core/consts.inc.php`
 * и могут быть переопределены в `lpm-config.inc.php`.
 */
class SecurityHeaders
{
    /**
     * Отправляет заголовки безопасности.
     *
     * Вызывать до любого вывода: после того как тело ответа началось,
     * заголовки уже не уходят. Если заголовки уже отправлены,
     * ничего не делает.
     *
     * @return void
     */
    public static function send()
    {
        if (headers_sent()) {
            return;
        }

        // Кликджекинг: показывать страницу в рамке разрешаем только своему origin.
        header('X-Frame-Options: SAMEORIGIN');

        // Браузер не должен угадывать тип содержимого вместо заявленного.
        header('X-Content-Type-Options: nosniff');

        // На сторонние сайты уходит только origin, путь и параметры не утекают.
        // Для запросов внутри сайта Referer остаётся полным - на нём держится
        // резервная проверка источника в CsrfToken::isSameOrigin().
        header('Referrer-Policy: strict-origin-when-cross-origin');

        $policy = trim(SECURITY_CSP_POLICY);
        if ($policy !== '') {
            $name = SECURITY_CSP_REPORT_ONLY
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            header($name . ': ' . $policy);
        }

        if (SECURITY_HSTS_MAX_AGE > 0 && self::isHttpsSite()) {
            header('Strict-Transport-Security: max-age=' . (int)SECURITY_HSTS_MAX_AGE);
        }
    }

    /**
     * Работает ли установка по HTTPS.
     *
     * Ориентируемся на схему в SITE_URL, а не на переменные запроса:
     * за завершающим TLS прокси приложение видит обычный HTTP.
     *
     * @return bool
     */
    private static function isHttpsSite()
    {
        return stripos(SITE_URL, 'https://') === 0;
    }
}
