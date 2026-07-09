<?php

class FileDownloadController
{
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
     * @var LightningEngine
     */
    private $engine;

    public function __construct(LightningEngine $engine)
    {
        $this->engine = $engine;
    }

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

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $file->size);
        $disposition = $inline && in_array($mimeType, self::INLINE_MIME_TYPES, true)
            ? 'inline'
            : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . $asciiName . '"; filename*=UTF-8\'' . '\'' . $utfName);
        header('X-Content-Type-Options: nosniff');

        readfile($absolutePath);
    }
}
