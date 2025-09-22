<?php

class FileDownloadController
{
    /**
     * @var LightningEngine
     */
    private $engine;

    public function __construct(LightningEngine $engine)
    {
        $this->engine = $engine;
    }

    public function handle($uid)
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

        $access = $this->canDownload($file);
        if ($access === null) {
            throw new NotFoundException('Access check failed: related item not found');
        }

        if (!$access) {
            throw new ForbiddenException('You do not have permission to download this file');
        }

        $this->streamFile($file);
    }

    private function canDownload(LPMFile $file)
    {
        $links = LPMFile::loadInstanceLinks($file->fileId);
        if (empty($links)) {
            return null;
        }

        $hasExistingInstances = false;

        foreach ($links as $link) {
            switch ($link['itemType']) {
                case LPMInstanceTypes::ISSUE:
                    $issue = Issue::load($link['itemId']);
                    if (!$issue) {
                        continue 2;
                    }

                    $hasExistingInstances = true;
                    if ($issue->checkViewPermit($this->engine->getUserId())) {
                        return true;
                    }
                    break;
            }
        }

        return $hasExistingInstances ? false : null;
    }

    private function streamFile(LPMFile $file)
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
        header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'' . '\'' . $utfName);
        header('X-Content-Type-Options: nosniff');

        readfile($absolutePath);
    }
}
