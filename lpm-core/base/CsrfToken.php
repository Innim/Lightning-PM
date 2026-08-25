<?php
/**
 * Защита от подделки межсайтовых запросов.
 *
 * Токен живёт в сессии и отдаётся своей же странице: JS шлёт его заголовком,
 * формы - скрытым полем. Страница на стороннем сайте может отправить запрос
 * от имени залогиненного пользователя (куки браузер приложит сам), но прочитать
 * токен ей неоткуда, поэтому такой запрос отсекается.
 */
class CsrfToken
{
    /**
     * Имя переменной сессии, где хранится токен.
     */
    const SESSION_NAME = 'lightning_csrf';
    /**
     * Заголовок, которым токен передаёт JS.
     */
    const HEADER = 'HTTP_X_CSRF_TOKEN';
    /**
     * Имя поля, которым токен передают формы.
     */
    const FIELD = 'csrfToken';

    /**
     * Возвращает токен текущей сессии, создавая его при первом обращении.
     * @return string
     */
    public static function get()
    {
        $session = Session::getInstance();
        $token = $session->get(self::SESSION_NAME);

        if (empty($token)) {
            $token = SecureRandomHelper::hex(32);
            $session->set(self::SESSION_NAME, $token);
        }

        return $token;
    }

    /**
     * Сбрасывает токен.
     *
     * Вызывается при входе и выходе: токен, выданный до смены пользователя,
     * действовать не должен.
     */
    public static function reset()
    {
        Session::getInstance()->unsetVar(self::SESSION_NAME);
    }

    /**
     * Проверяет токен, пришедший с запросом.
     * @param  string|null $token Токен. Если не передан - берётся из заголовка,
     *                            а при его отсутствии из поля формы.
     * @return bool
     */
    public static function check($token = null)
    {
        if ($token === null) {
            $token = self::extractFromRequest();
        }

        $expected = Session::getInstance()->get(self::SESSION_NAME);

        // hash_equals - чтобы по времени ответа нельзя было подбирать токен посимвольно
        return !empty($expected) && is_string($token) && hash_equals($expected, $token);
    }

    /**
     * Определяет, что запрос пришёл с нашей же страницы.
     *
     * Это не проверка доступа - заголовки можно не прислать вовсе, поэтому
     * защиту строим на токене. Признак нужен только чтобы решить, показывать ли
     * пользователю его данные обратно: введённое на нашей странице вернуть
     * в форму можно, а пришедшее со стороннего сайта - нельзя, иначе
     * подделанную форму осталось бы подтвердить одной кнопкой.
     * @return bool
     */
    public static function isSameOrigin()
    {
        $source = '';
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $source = (string)$_SERVER['HTTP_ORIGIN'];
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $source = (string)$_SERVER['HTTP_REFERER'];
        }

        if ($source === '') {
            return false;
        }

        $sourceHost = parse_url($source, PHP_URL_HOST);
        $siteHost = parse_url(SITE_URL, PHP_URL_HOST);

        return !empty($sourceHost) && !empty($siteHost)
            && strcasecmp($sourceHost, $siteHost) === 0;
    }

    /**
     * @return string
     */
    private static function extractFromRequest()
    {
        if (!empty($_SERVER[self::HEADER])) {
            return (string)$_SERVER[self::HEADER];
        }

        return isset($_POST[self::FIELD]) && is_string($_POST[self::FIELD])
            ? $_POST[self::FIELD] : '';
    }
}
