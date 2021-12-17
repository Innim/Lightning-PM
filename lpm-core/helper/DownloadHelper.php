<?php
/**
 * Класс, содержащий вспомогательные методы для загрузки.
 */
class DownloadHelper {
    /**
     * Скачивает изображение. 
     * 
     * В случае ошибке будет порождено исключение.
     * @param string $url URL изображения, которое надо скачать.
     * @param string $targetPath Путь, куда надо скачивать.
     * @param int    $maxSizeMb Максимальный допустимый размер в MB.
     * @param bool   $addExtension Если true, то к имени файла в $targetPath будет добавлено расширение,
     *                             Автоматически определенное по ContentType
     * @return string Путь до скачанного файла.
     * @throws Exception В случае возникновения ошибки при скачивании.
     */
    public static function downloadImage($url, $targetPath, $maxSizeMb = 10, $addExtension = false)
    {
         // определяем размер скачиваемой картинки
         $context = stream_context_create([
            // XXX здесь дыра безопасности по сути - отключена проверка ssl
            // на с включенной не работает droplr, хотя сертификат у них правильный
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $stream = fopen($url, "r", false, $context);
        if (!$stream) {
            throw new Exception('Не удалось загрузить файл ' . $url);
        }

        try {
            // берем ее параметры из url
            $imageData = stream_get_meta_data($stream);
            if (!$imageData) {
                throw new Exception('Ошибка чтения данных потока');
            }
        
            // данные должны быть и иметь определенный тип
            if (!isset($imageData["wrapper_data"]) || !is_array($imageData["wrapper_data"])) {
                throw new Exception('Неверный формат данных файла');
            }

            $size = -1;
            $contentType = null;
            // извлекаем из них размер
            foreach ($imageData["wrapper_data"] as $param) {
                // Там может быть несколько content-length, если делаются пересылки
                // так что перебираем до последнего - все wrapper_data
                if (stristr($param, "content-length")) {
                    //получаем параметр
                    $param = explode(":", $param);
                    $size  = (int)(trim($param[1]));
                } elseif (strripos($param, 'content-type') === 0) {
                    $parts = explode(":", $param);
                    $contentType = (trim($parts[1]));
                }
            }

            // Проверяем размер
            if ($size === -1) {
                throw new Exception('Не удалось получить размер файла');
            }

            if ($size <= 0) {
                throw new Exception('Файл не может быть нулевого размера');
            }

            if ($size > $maxSizeMb * 1024 * 1024) {
                throw new Exception(sprintf(
                    'Размер файла не должен превышать %d Мб',
                    $maxSizeMb
                ));
            }

            if ($addExtension && $contentType != null) {
                $mimes = new \Mimey\MimeTypes;
                $ext = $mimes->getExtension($contentType); 
                if (!empty($ext)) $targetPath .= '.' . $ext;
            }


            // Если размер файла валиден и он не превышает лимит - пишем файл на диск
            if (file_put_contents($targetPath, $stream, FILE_APPEND | LOCK_EX)) {
                return $targetPath;
            } else {
                // не удалось
                throw new Exception('Ошибка скачивания или записи файла');
            }            
        } finally {
            fclose($stream);
        }

    }
}