<?php
/**
 * Фоновый CLI-воркер сжатия видео.
 *
 * Запускается отдельным процессом из {@see VideoCompressor::spawnWorker()}
 * и переживает завершение HTTP-запроса. Единственный аргумент — id файла.
 *
 * Использование: php video-compress-worker.php <fileId>
 */

require_once dirname(__FILE__) . '/../init.inc.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$fileId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($fileId > 0) {
    VideoCompressor::compress($fileId);
}
