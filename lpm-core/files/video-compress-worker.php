<?php
/**
 * Фоновый CLI-воркер сжатия видео.
 *
 * Запускается отдельным процессом из {@see VideoCompressor::dispatch()}
 * и переживает завершение HTTP-запроса. Аргументы — id файла и номер
 * занятого под него слота очереди.
 *
 * Использование: php video-compress-worker.php <fileId> <slotNo>
 */

require_once dirname(__FILE__) . '/../init.inc.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$fileId = isset($argv[1]) ? (int)$argv[1] : 0;
$slotNo = isset($argv[2]) ? (int)$argv[2] : 0;
if ($fileId > 0 && $slotNo > 0) {
    VideoCompressor::runJob($fileId, $slotNo);
}
