<?php
/**
 * Адрес клиента запроса.
 *
 * Приложение почти всегда стоит за обратным прокси, и тогда веб-сервер видит
 * не адрес пользователя, а адрес прокси — один и тот же у всех запросов.
 * Настоящий адрес приходит только в заголовке `X-Forwarded-For`, а заголовок
 * подделывается кем угодно, поэтому верить ему можно, лишь когда сам запрос
 * пришёл от прокси из списка доверенных ({@see SECURITY_TRUSTED_PROXIES}).
 *
 * Отсюда два разных ответа: {@see getRemoteAddr()} — адрес, который видит
 * веб-сервер, он есть всегда, но за прокси общий на всех;
 * {@see getTrusted()} — адрес самого пользователя, и он есть только там, где
 * его удалось установить достоверно. Ограничения, которые бьют по всем
 * владельцам адреса сразу, строятся только на втором.
 */
class ClientIp
{
    /**
     * Сколько узлов разбирать в `X-Forwarded-For`. Заголовок присылает клиент,
     * поэтому его длина ничем не ограничена, а нужен нам только хвост цепочки.
     */
    const MAX_FORWARDED_HOPS = 10;

    /**
     * Адрес пользователя, установленный достоверно.
     *
     * @return string Пустая строка, если достоверного адреса нет: список
     *                доверенных прокси не задан, запрос пришёл не от него
     *                либо заголовка с адресом в запросе не было.
     */
    public static function getTrusted()
    {
        $proxies = self::getTrustedProxies();
        if (empty($proxies)) {
            return '';
        }

        $remoteAddr = self::getRemoteAddr();
        if ($remoteAddr === '' || !in_array($remoteAddr, $proxies, true)) {
            return '';
        }

        $chain = self::getForwardedChain();
        if (empty($chain)) {
            return '';
        }

        // Цепочка дописывается слева направо, каждый прокси добавляет в конец
        // адрес того, от кого получил запрос. Поэтому идём с конца и берём
        // первый узел, который не наш прокси: всё, что левее него, записано
        // с чужих слов и подделывается.
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!in_array($chain[$i], $proxies, true)) {
                return $chain[$i];
            }
        }

        return '';
    }

    /**
     * Адрес, с которого запрос пришёл к веб-серверу. За прокси это адрес
     * самого прокси, то есть общий для всех пользователей.
     * @return string Пустая строка, если адреса нет (например, вызов из CLI).
     */
    public static function getRemoteAddr()
    {
        $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '';
    }

    /**
     * Адреса прокси, чьему `X-Forwarded-For` можно верить.
     * @return string[] Пустой массив, если доверенные прокси не настроены.
     */
    public static function getTrustedProxies()
    {
        if (!defined('SECURITY_TRUSTED_PROXIES') || !is_array(SECURITY_TRUSTED_PROXIES)) {
            return [];
        }

        $proxies = [];
        foreach (SECURITY_TRUSTED_PROXIES as $proxy) {
            $proxy = trim((string)$proxy);
            if (filter_var($proxy, FILTER_VALIDATE_IP)) {
                $proxies[] = $proxy;
            }
        }

        return $proxies;
    }

    /**
     * Цепочка адресов из `X-Forwarded-For`, слева направо.
     * @return string[] Только корректные адреса, не длиннее MAX_FORWARDED_HOPS.
     */
    private static function getForwardedChain()
    {
        if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return [];
        }

        $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        $parts = array_slice($parts, -self::MAX_FORWARDED_HOPS);

        $chain = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (filter_var($part, FILTER_VALIDATE_IP)) {
                $chain[] = $part;
            }
        }

        return $chain;
    }
}
