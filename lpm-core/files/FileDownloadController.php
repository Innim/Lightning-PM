<?php

class FileDownloadController
{
    /**
     * Задаёт кодировку HTML-документа: ту, что объявлена в начале самого
     * документа, иначе UTF-8.
     *
     * Кодировку надо задавать явно: заголовок ответа приоритетнее объявления
     * в документе, а PHP сам подставляет в него `default_charset` — без явного
     * значения документ в другой кодировке читался бы как UTF-8. Унаследовать
     * кодировку от страницы, в которую документ вложен, он не может: он
     * изолирован от неё.
     * @param  string $path     Абсолютный путь к файлу.
     * @param  string $mimeType Тип файла.
     * @return string Значение заголовка `Content-Type`.
     */
    private static function htmlContentType($path, $mimeType)
    {
        if (stripos($mimeType, 'charset') !== false) {
            return $mimeType;
        }

        $charset = 'utf-8';
        $head = file_get_contents($path, false, null, 0, self::CHARSET_LOOKUP_BYTES);
        if ($head !== false && preg_match('/charset\s*=\s*["\']?([\w-]+)/i', $head, $matches)) {
            $charset = $matches[1];
        }

        return $mimeType . '; charset=' . $charset;
    }

    /**
     * Запрошен ли файл как вложенный документ - то есть как фрейм страницы
     * просмотра.
     *
     * HTML показываем только так: развёрнутая во весь экран по адресу
     * приложения, приложенная страница могла бы выдать себя за само приложение,
     * например нарисовать форму входа. Браузер, который назначение запроса не
     * сообщает, отличить фрейм от отдельной вкладки не позволяет - такому файл
     * отдаётся вложением, как и до появления просмотра.
     * @return bool
     */
    private static function isFramedRequest()
    {
        $dest = strtolower(trim(
            isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? $_SERVER['HTTP_SEC_FETCH_DEST'] : ''
        ));

        return $dest === 'iframe' || $dest === 'frame';
    }

    private const INLINE_MIME_TYPES = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/ogg',
        'video/webm',
    ];

    /**
     * Политика, с которой отдаётся HTML: документ попадает в непрозрачное
     * происхождение, поэтому его скрипты работают, но куки и API приложения
     * им недоступны.
     *
     * Добавлять сюда `allow-same-origin` нельзя: вместе с `allow-scripts`
     * он возвращает документ в происхождение сайта и снимает всю защиту.
     */
    private const HTML_CSP = 'sandbox allow-scripts';

    /**
     * Сколько байт от начала HTML читается в поисках объявления кодировки.
     */
    private const CHARSET_LOOKUP_BYTES = 2048;

    /**
     * @var LightningEngine
     */
    private $engine;

    public function __construct(LightningEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Отдаёт файл пользователю, если у него есть доступ к связанной задаче.
     * @param string $uid    Уникальный идентификатор файла.
     * @param bool   $inline Показать файл в браузере, а не скачать. Работает
     *   только для типов, которые браузер может показать безопасно; HTML при
     *   этом изолируется от сессии пользователя.
     * @throws NotFoundException  Файла нет или связанных с ним сущностей нет.
     * @throws ForbiddenException Пользователь не авторизован или не имеет
     *   доступа к файлу.
     */
    public function handle($uid, $inline = false)
    {
        $uid = trim((string)$uid);
        if ($uid === '') {
            throw NotFoundException::withMessage('File not found', 'Invalid file identifier');
        }

        $file = LPMFile::loadByUid($uid);
        if (!$file || $file->deleted) {
            throw NotFoundException::withMessage('File not found');
        }

        if (!$this->engine->isAuth()) {
            throw new ForbiddenException('Authentication required to download file');
        }

        $user = $this->engine->getUser();
        $access = $this->canDownload($file, $user->getID());
        if ($access === null) {
            throw new NotFoundException('Access check failed: related item not found');
        }

        if (!$access) {
            throw new ForbiddenException('You do not have permission to download this file');
        }

        $this->streamFile($file, $inline);
    }

    private function canDownload(LPMFile $file, $userId)
    {
        return $file->checkViewPermit($userId);
    }

    private function streamFile(LPMFile $file, $inline)
    {
        $absolutePath = FileUploadManager::getAbsolutePath($file->path);
        if (!is_file($absolutePath)) {
            throw NotFoundException::withMessage('File not found', 'File data is missing on server');
        }

        $mimeType = empty($file->mimeType) ? 'application/octet-stream' : $file->mimeType;
        $asciiName = str_replace('"', '\"', $file->origName);
        $utfName = rawurlencode($file->origName);

        $contentType = $mimeType;
        $disposition = 'attachment';

        if ($inline) {
            if ($file->isHtml() && self::isFramedRequest()) {
                $disposition = 'inline';
                $contentType = self::htmlContentType($absolutePath, $mimeType);
                header('Content-Security-Policy: ' . self::HTML_CSP);
            } elseif (in_array($mimeType, self::INLINE_MIME_TYPES, true)) {
                $disposition = 'inline';
            }
        }

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . $file->size);
        header('Content-Disposition: ' . $disposition . '; filename="' . $asciiName . '"; filename*=UTF-8\'' . '\'' . $utfName);
        header('X-Content-Type-Options: nosniff');

        readfile($absolutePath);
    }
}
