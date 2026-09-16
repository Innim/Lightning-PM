<?php

/**
 * Отдаёт полноразмерное изображение под тем именем, под которым его загрузили.
 *
 * Нужен ради имени: имя сохраняемой картинки браузер берёт из заголовка
 * `Content-Disposition`, а у файла, отданного напрямую, такого заголовка нет -
 * и картинка ложится на диск под сгенерированным именем.
 *
 * Прав маршрут не проверяет: каталог с картинками отдаётся напрямую (см.
 * `lpm-files/.htaccess`), тот же файл читается по статическому адресу, и
 * проверка была бы видимостью защиты. Закрывать картинки правами имеет смысл
 * только на уровне каталога, целиком.
 *
 * Зато доступ держится на том же, на чём и у прямой ссылки: в адресе должно
 * стоять случайное имя файла на диске. Без него хватило бы перебора
 * идентификаторов, чтобы прочитать картинки чужих задач.
 */
class ImageDownloadController
{
    /**
     * Заголовок, которым задаётся имя сохраняемого файла.
     *
     * Картинку показываем в браузере (`inline`), но имя сообщаем: по нему
     * браузер назовёт файл, когда картинку сохранят из просмотрщика. Имя
     * передаётся дважды - ASCII-вариантом и в кодировке UTF-8: первый
     * понимают все браузеры, второй сохраняет не-латиницу.
     * @param  LPMImg $img
     * @return string Значение заголовка `Content-Disposition`.
     */
    private static function disposition(LPMImg $img)
    {
        $name = $img->getDisplayName();
        if ($name === '') {
            // У картинок, загруженных до того, как имя начали сохранять, его
            // нет - тогда имя остаётся за браузером, как и при прямой ссылке.
            return 'inline';
        }

        return 'inline; filename="' . str_replace('"', '\"', $name) . '"' .
                "; filename*=UTF-8''" . rawurlencode($name);
    }

    /**
     * Тип изображения определяется по содержимому, а не по расширению: ответ
     * идёт с `nosniff`, поэтому заявленный тип должен быть настоящим.
     * @param  string $path Абсолютный путь к файлу.
     * @return string Значение заголовка `Content-Type`.
     */
    private static function mimeType($path)
    {
        $info = @getimagesize($path);

        return empty($info['mime']) ? self::FALLBACK_MIME_TYPE : $info['mime'];
    }

    /**
     * Имя файла, которым заканчивается запрошенный адрес.
     *
     * Читается из запроса, а не из разобранных аргументов: сегмент с точкой
     * до них не доходит - маршрутизация обрезает путь по первому же имени
     * с расширением.
     * @return string Пустая строка, если имени в адресе нет.
     */
    private static function requestedFileName()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $path = (string)parse_url($uri, PHP_URL_PATH);
        $slashPos = strrpos($path, '/');

        return rawurldecode($slashPos === false ? $path : substr($path, $slashPos + 1));
    }

    /**
     * Есть ли у браузера актуальная копия картинки.
     * @param  int $modified Время последнего изменения файла (unix).
     * @return bool
     */
    private static function isNotModified($modified)
    {
        if (empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            return false;
        }

        $since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

        return $since !== false && $modified <= $since;
    }

    /**
     * Сообщение о картинке, которой нет.
     */
    public const NOT_FOUND_MESSAGE = 'Такой картинки нет - возможно, её удалили';

    /**
     * Насколько браузеру разрешено держать картинку в кэше, секунд.
     *
     * Месяц - столько же, сколько эти же файлы живут в кэше, когда их отдаёт
     * веб-сервер напрямую (`ExpiresByType image/*` в корневом `.htaccess`):
     * выдача через приложение не должна заставлять выкачивать картинку заново.
     * Содержимое по одному адресу не меняется - файл на диске не
     * перезаписывается, изменение картинки означает новую запись.
     */
    private const CACHE_MAX_AGE = 2592000;

    /**
     * Тип, с которым отдаётся файл, не опознанный как изображение.
     */
    private const FALLBACK_MIME_TYPE = 'application/octet-stream';

    /**
     * Отдаёт изображение.
     * @param  int $imgId Идентификатор изображения. Адрес должен заканчиваться
     *   ещё и именем файла картинки на диске - без него изображение не
     *   отдаётся.
     * @throws NotFoundException Изображения нет, оно удалено, адрес не
     *   сходится с именем файла или файл не найден на диске.
     */
    public function handle($imgId)
    {
        $img = LPMImg::load($imgId);
        // Несовпадение имени отвечает так же, как отсутствие картинки: иначе
        // по разнице ответов стало бы видно, какие идентификаторы заняты.
        if (empty($img) || self::requestedFileName() !== $img->getFileName()) {
            throw NotFoundException::withMessage(self::NOT_FOUND_MESSAGE, 'Image not found');
        }

        $this->streamImage($img);
    }

    private function streamImage(LPMImg $img)
    {
        $path = realpath($img->getSrcImg());
        $baseDir = realpath(LPMImg::getSrcImgPath());
        // Путь складывается из имени, которое выдал сервер, но приходит он из
        // базы - проверка удерживает выдачу в каталоге с исходниками картинок.
        if ($path === false || $baseDir === false || !is_file($path) ||
                strpos($path, $baseDir . DIRECTORY_SEPARATOR) !== 0) {
            throw NotFoundException::withMessage(
                self::NOT_FOUND_MESSAGE,
                'Image data is missing on server'
            );
        }

        $modified = filemtime($path);

        header('Content-Type: ' . self::mimeType($path));
        header('Content-Disposition: ' . self::disposition($img));
        // Запрет кэширования, выставленный сессией (`session.cache_limiter`),
        // снимаем: для картинки он означал бы выкачивать её заново при каждом
        // открытии. Одного `Cache-Control` мало - `Expires` и `Pragma` от
        // сессии запрещают кэш тем клиентам, которые его не разбирают. Срок
        // проставляем свой, а не оставляем это веб-серверу: на разных
        // установках он настроен по-разному.
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + self::CACHE_MAX_AGE) . ' GMT');
        header_remove('Pragma');
        header('Cache-Control: private, max-age=' . self::CACHE_MAX_AGE);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        header('X-Content-Type-Options: nosniff');

        if (self::isNotModified($modified)) {
            http_response_code(304);
            return;
        }

        header('Content-Length: ' . filesize($path));

        readfile($path);
    }
}
