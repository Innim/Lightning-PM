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
     * @return 
     */
    public static function downloadImage($url, $targetPath, $maxSizeMb = 10)
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
            // TODO: $type "Content-Type: image/png" и взять из него расширение
            // извлекаем из них размер
            foreach ($imageData["wrapper_data"] as $param) {
                // Там может быть несколько content-length, если делаются пересылки
                // так что перебираем до последнего - все wrapper_data
                if (stristr($param, "content-length")) {
                    //получаем параметр
                    $param = explode(":", $param);
                    $size  = (int)(trim($param[1]));
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


            // Если размер файла валиден и он не превышает лимит - пишем файл на диск
            if (file_put_contents($targetPath, $stream, FILE_APPEND | LOCK_EX)) {
                var_dump($imageData);
            } else {
                // не удалось
                throw new Exception('Ошибка скачивания или записи файла');
            }            
        } finally {
            fclose($stream);
        }

    }
}