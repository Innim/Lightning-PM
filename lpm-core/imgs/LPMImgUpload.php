<?php
/**
 * Обработка загрузки изображений.
 * Предполагается, что поле для загрузки изображения имеет определенное имя.
 * Также надо не забывать указывать <code>enctype="multipart/form-data"</code> у формы
 * @author GreyMag
 * @see LPMImgUpload::IMG_INPUT_NAME
 *
 */
class LPMImgUpload
{
    /**
     * Проверяет файлы, выбранные в форме, не сохраняя их.
     * Позволяет отсечь некорректные изображения до записи каких-либо данных.
     * @param  string $name Имя поля с файлами для загрузки.
     * @return array Массив сообщений об ошибках. Пустой, если всё в порядке.
     */
    public static function validateUploadedFiles($name)
    {
        $errors = [];

        if (!isset($_FILES[$name]) || !isset($_FILES[$name]['tmp_name'])
                || !is_array($_FILES[$name]['tmp_name'])) {
            return $errors;
        }

        $files = $_FILES[$name];
        foreach ($files['tmp_name'] as $i => $tmpName) {
            $originalName = isset($files['name'][$i]) ? $files['name'][$i] : null;
            $errorCode = isset($files['error'][$i]) ? $files['error'][$i] : UPLOAD_ERR_OK;

            if ($errorCode === UPLOAD_ERR_NO_FILE || (empty($tmpName) && $errorCode === UPLOAD_ERR_OK)) {
                continue;
            }

            if ($errorCode !== UPLOAD_ERR_OK) {
                $errors[] = FileUploadManager::translateUploadError($errorCode, $originalName);
                continue;
            }

            $error = self::checkImageFile($tmpName, $originalName);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Готовит к загрузке изображения, вставленные из буфера обмена и добавленные
     * по URL: сохраняет их во временные файлы и проверяет.
     *
     * Загрузить подготовленные изображения можно через uploadPrepared(),
     * а отказаться от них - через removeTempFiles().
     * @param  array $base64Items Массив изображений в виде строк base64.
     * @param  array $urls        Массив URL адресов изображений.
     * @param  array $errors      Сюда добавляются сообщения об ошибках.
     * @return array Подготовленные изображения. Пусто, если были ошибки.
     */
    public static function prepareImages($base64Items, $urls, array &$errors)
    {
        $prepared = self::prepareFromBase64($base64Items, $errors);
        if (!empty($errors)) {
            return $prepared;
        }

        $fromUrls = self::prepareFromUrls($urls, $errors);
        if (!empty($errors)) {
            self::removeTempFiles($prepared);
            return self::emptyPrepared();
        }

        return [
            'files' => array_merge($prepared['files'], $fromUrls['files']),
            'names' => array_merge($prepared['names'], $fromUrls['names']),
        ];
    }

    /**
     * Удаляет временные файлы подготовленных изображений.
     * @param array $prepared Подготовленные изображения.
     */
    public static function removeTempFiles($prepared)
    {
        if (!empty($prepared['files'])) {
            self::clearTmpImages($prepared['files']);
        }
    }

    /**
     * Готовит изображения, переданные строками base64
     * (используется для вставки из буфера обмена).
     * @param  array $items  Массив изображений в виде строк base64.
     * @param  array $errors Сюда добавляются сообщения об ошибках.
     * @return array Подготовленные изображения. Пусто, если были ошибки.
     */
    private static function prepareFromBase64($items, array &$errors)
    {
        $prepared = self::emptyPrepared();

        if (empty($items)) {
            return $prepared;
        }

        $dirTempPath = self::prepareTempDir($errors);
        if (null === $dirTempPath) {
            return $prepared;
        }

        foreach ($items as $value) {
            if (empty($value)) {
                continue;
            }

            $value = str_replace(['data:image/png;base64,', ' '], ['', '+'], $value);
            $filepath = $dirTempPath . DIRECTORY_SEPARATOR . BaseString::randomStr(10) . '.jpeg';

            if (!file_put_contents($filepath, base64_decode($value))) {
                $errors[] = 'Ошибка при записи в файл';
                break;
            }

            $prepared['files'][] = $filepath;
            $prepared['names'][] = 'clb_paste_' . date('YmdHis_u') . '.jpg';

            $error = self::checkImageFile($filepath);
            if (null !== $error) {
                $errors[] = $error;
                break;
            }
        }

        return self::finishPrepare($prepared, $errors);
    }

    /**
     * Готовит изображения, которые нужно получить по url.
     * @param  array $urls   Массив URL адресов.
     * @param  array $errors Сюда добавляются сообщения об ошибках.
     * @return array Подготовленные изображения. Пусто, если были ошибки.
     */
    private static function prepareFromUrls($urls, array &$errors)
    {
        $prepared = self::emptyPrepared();

        if (empty($urls)) {
            return $prepared;
        }

        $dirTempPath = self::prepareTempDir($errors);
        if (null === $dirTempPath) {
            return $prepared;
        }

        // перебираем все ссылки
        foreach ($urls as $url) {
            // если ссылка не пустая
            $value = trim($url);
            if (empty($value)) {
                continue;
            }

            // получаем из нее картинку и сохраняем ее
            $filepath = $dirTempPath . DIRECTORY_SEPARATOR . BaseString::randomStr(10) . '.png';

            $value = AttachmentImageHelper::getDirectUrl($value);

            try {
                DownloadHelper::downloadImage($value, $filepath, self::MAX_SIZE);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                continue;
            }

            $prepared['files'][] = $filepath;
            $prepared['names'][] = 'url_' . date('YmdHis_u') . '.png'; // тут бы настоящее имя выделить из url

            $error = self::checkImageFile($filepath, $url);
            if (null !== $error) {
                $errors[] = $error;
            }
        }

        return self::finishPrepare($prepared, $errors);
    }

    /**
     * Создаёт директорию для временных файлов.
     * @param  array $errors Сюда добавляется сообщение об ошибке.
     * @return string|null Путь до директории или null, если создать не удалось.
     */
    private static function prepareTempDir(array &$errors)
    {
        $dirTempPath = LPMImg::getSrcImgPath('temp');

        if (!is_dir($dirTempPath) && !mkdir($dirTempPath, 0777, true)) {
            $msg = 'Ошибка при создании директории';
            if (DefaultGlobals::isDebugMode()) {
                $msg .= ' "' . $dirTempPath . '" - ';
                $error = error_get_last();
                if (!empty($error) && isset($error['message'])) {
                    $msg .= $error['message'];
                } else {
                    $msg .= 'unknown error';
                }
            }

            $errors[] = $msg;
            return null;
        }

        return $dirTempPath;
    }

    /**
     * Если при подготовке были ошибки - удаляет всё, что успели сохранить.
     * @param  array $prepared Подготовленные изображения.
     * @param  array $errors   Сообщения об ошибках.
     * @return array Подготовленные изображения. Пусто, если были ошибки.
     */
    private static function finishPrepare(array $prepared, array $errors)
    {
        if (empty($errors)) {
            return $prepared;
        }

        self::removeTempFiles($prepared);

        return self::emptyPrepared();
    }

    private static function emptyPrepared()
    {
        return ['files' => [], 'names' => []];
    }

    /**
     * Проверяет, что файл может быть загружен как изображение.
     * @param  string $filepath     Путь до файла.
     * @param  string $originalName Оригинальное имя файла - для сообщения об ошибке.
     * @param  int    $type         Тип изображения (одна из констант IMAGETYPE_*),
     *                              заполняется, если файл корректен.
     * @return string|null Текст ошибки или null, если файл может быть загружен.
     */
    public static function checkImageFile($filepath, $originalName = null, &$type = null)
    {
        $label = empty($originalName) ? '' : ' "' . $originalName . '"';

        if (!file_exists($filepath)) {
            return 'Не удалось загрузить файл' . $label;
        }

        if (filesize($filepath) > self::MAX_SIZE * 1024 * 1024) {
            return sprintf('Размер изображения%s не должен превышать %d Мб', $label, self::MAX_SIZE);
        }

        $info = @getimagesize($filepath);
        $type = false === $info ? null : $info[2];
        $allowedTypes = self::getAllowedTypes();
        if (null === $type || !isset($allowedTypes[$type])) {
            $type = null;
            return sprintf(
                'Файл%s не является изображением. Допустимые форматы: %s',
                $label,
                implode(', ', array_unique(array_values($allowedTypes)))
            );
        }

        return null;
    }

    /**
     * Допустимые типы изображений.
     * @return array Ассоциативный массив [тип изображения => расширение файла].
     */
    public static function getAllowedTypes()
    {
        return [
            IMAGETYPE_JPEG          => 'jpg',
            IMAGETYPE_JPEG2000      => 'jpeg',
            IMAGETYPE_PNG           => 'png',
            IMG_GIF                 => 'gif',
        ];
    }

    /**
     * Максимальный размер (в Мб)
     * @var int
     */
    const MAX_SIZE = 10;
    /**
     * Основная часть название поля для загрузки изображения.
     * Чтобы получить название поле, необходимо добавить к этой части '[]'.
     * Например при значении константы <pre>files</pre>,
     * поле должно будет называться <pre>files[]</pre>
     * @var string
     */
    const IMG_INPUT_NAME = 'images';
    
    /**
     * Ошибки при загрузке и обработке изображений
     * @var array
     */
    private $_errors = array();
    /**
     * Поддиректория в директории изображений для сохранения фото
     * @var String
     */
    private $_dir = '';
    /**
     * Префикс фото
     * @var String
     */
    private $_prefix = '';
    
    /**
     * Размеры
     * @var array
     */
    private $_sizes = null;
    /**
     * Идентификатор пользователя, который загружает изображения
     * @var int
     */
    private $_userId;
    /**
     * Массив загруженных изображений
     * @var array
     */
    private $_imgs = array();
    /**
     *
     * @var DBConnect
     */
    private $_db;
    private $_itemType = 0;
    private $_itemId = 0;

    private $_saveInDB;

    private $_maxPhotos;
    
    /**
     *
     * @param int $maxPhotos максимальное количество загружаемых фото
     * @param boolean $saveInDB сохранять информацию в таблице изображений
     * @param array $cacheSizes Массив массивов, определяющих размер [int width, int height].
     * Если нужен только один размер - можно передать сразу массив, определяющий размер.
     * Если изображение квадратное, то в массиве может быть одно число.
     * Если передано null - кэшируется исходное изображение.
     * @param string $dir
     * @param string $prefix
     * @param boolean $defaultLoad Будет выполнена загрузка по умолчанию (из $_FILES с именем IMG_INPUT_NAME)
     */
    public function __construct(
        $maxPhotos = 1,
        $saveInDB = true,
        $cacheSizes = null,
        $dir = '',
        $prefix = '',
        $itemType = 0,
        $itemId = 0,
        $defaultLoad = true
    ) {
        $engine = LightningEngine::getInstance();
        $userId = $engine->isAuth()
                   ? $engine->getAuth()->getUserId()
                   : 0;
        $this->_userId = $userId;
        $this->_db = LPMGlobals::getInstance()->getDBConnect();
        
        $this->_dir = $dir;
        $this->_prefix = $prefix;
        $this->_saveInDB = $saveInDB;

        $this->_itemType = $itemType;
        $this->_itemId = $itemId;

        $this->_maxPhotos = $maxPhotos;
        
        // сохраняем размеры
        if (is_array($cacheSizes) && count($cacheSizes) > 0) {
            $this->_sizes = array();
            if (is_array($cacheSizes[0])) {
                foreach ($cacheSizes as $size) {
                    $this->addSize($size);
                }
            } else {
                $this->addSize($cacheSizes);
            }
            if (count($this->_sizes) == 0) {
                $this->_sizes = null;
            }
        }
        
        // Выполняем загрузку по умолчанию
        if ($defaultLoad) {
            $this->uploadViaFiles(self::IMG_INPUT_NAME);
        }
    }

    /**
     * Осуществляет подготовку изображений
     * @param  string $name Имя поля с файлами для загрузки
     * @return boolean
     */
    public function uploadViaFiles($name)
    {
        if (isset($_FILES[$name])) {
            $files = array();
            $names = array();

            foreach ($_FILES[$name]['tmp_name'] as $i => $tmpName) {
                if (!empty($tmpName)) {
                    $files[] = $tmpName;
                    $names[] = $_FILES[$name]['name'][$i];
                }
            }

            return $this->addImages($files, true, $names, false);
        } else {
            return true;
        }
    }

    /**
     * Загружает изображения, подготовленные через prepareImages().
     * @param  array $prepared Подготовленные изображения.
     * @return boolean
     */
    public function uploadPrepared($prepared)
    {
        if (empty($prepared['files'])) {
            return true;
        }

        return $this->addImages($prepared['files'], false, $prepared['names']);
    }

    private static function clearTmpImages($files)
    {
        foreach ($files as $filename) {
            if (file_exists($filename)) {
                @unlink($filename);
            }
        }
    }

    /**
     * Добавляет изображения (с переносом)
     * @param array   $files         Массив путей до изображений, которые должны быть добавлены
     * @param boolean $uploaded      Определяет, были ли файлы загружены из формы через POST
     * @param array   $originalNames Массив оригинальных имен файлов (индексы должны совпадать с $files)
     * @param boolean $clearTmp      Удалит все файлы из $files вне зависимости от результата
     */
    private function addImages($files, $uploaded = false, $originalNames = null, $clearTmp = true)
    {
        // Готовим запрос записи в БД
        $userId = $this->_userId;
        if ($this->_saveInDB &&
            !($prepare = $this->_db->preparet("INSERT INTO `%s` (`url`, `userId`, `name`, `itemType`, `itemId`) VALUES (?, '{$userId}', ?, '{$this->_itemType}', '{$this->_itemId}')", LPMTables::IMAGES))) {
            return $this->error('Ошибка при записи в БД');
        } else {
            // Перебираем все файлы
            foreach ($files as $i => $file) {
                if ($this->getLoadedCount() + 1 > $this->_maxPhotos) {
                    if ($clearTmp) {
                        self::clearTmpImages($files);
                    }
                    break;
                }

                // Загружаем файл, если была ошибка - прерываем все
                $originalName = null !== $originalNames && isset($originalNames[$i])
                    ? $originalNames[$i] : null;
                if (!($img = $this->loadImage($file, $uploaded, $originalName))) {
                    break;
                }
                // Выполняем запрос записи в БД
                if ($this->_saveInDB) {
                    $this->saveInDB($img, $prepare);
                }
            }
        }

        // Закрываем подготовленный запрос
        if ($this->_saveInDB) {
            $prepare->close();
        }

        // Если были ошибки - то удаляем все, что загружено
        if ($this->isErrorsExist()) {
            $this->removeImgs();
            if ($clearTmp) {
                self::clearTmpImages($files);
            }
            return false;
        } else {
            return true;
        }
    }

    private function loadImage($filepath, $uploaded = false, $originalName = null)
    {
        // Директория сохранения
        $dir = $this->_dir;

        // Проверяем вес и тип файла, получаем расширение
        $checkError = self::checkImageFile($filepath, $originalName, $type);
        if (null !== $checkError) {
            return $this->error($checkError);
        }

        $allowedTypes = self::getAllowedTypes();
        $ext = $allowedTypes[$type];

        // Проверяем, существует ли директория
        // и пытаемся создать, если не существует
        if (!empty($dir)) {
            $dirPath = LPMImg::getSrcImgPath($dir);

            if (!is_dir($dirPath) && !mkdir($dirPath)) {
                return $this->error('Ошибка при создании директории');
            }
            
            $dls = mb_substr($dir, -1);
            if ($dls !== '/') {
                $dir .= '/';
            }
        }

        // Сохраняем исходный файл
        // Ищем уникальное имя
        do {
            $srcFilename = $dir . $this->_prefix . BaseString::randomStr(10) . '.' . $ext;
            $srcFilepath = LPMImg::getSrcImgPath($srcFilename);
        } while (file_exists($srcFilepath));

        // Перемещаем исходный файл
        $moveFunc = $uploaded ? 'move_uploaded_file' : 'rename';
        if (!call_user_func($moveFunc, $filepath, $srcFilepath)) {
            return $this->error('Ошибка при сохранении файла');
        }

        // Генерируем необходимые изображения
        $img = new LPMImg($srcFilename);
        $img->origName = null === $originalName ? $originalName : '';

        if (null !== $this->_sizes) {
            foreach ($this->_sizes as $size) {
                $img->getCacheImg($size[0], $size[1]);
            }
        } else {
            $img->getCacheImg();
        }

        $this->_imgs[] = $img;

        return $img;
    }
    
    /**
     *
     * @param int $index
     * @return LPMImg
     */
    public function getImgByIndex($index)
    {
        return $index >= 0 && $index < count($this->_imgs)
            ? $this->_imgs[$index] : null;
    }
    
    /**
     * Удаляет загруженные фотографии и их кэши
     */
    public function removeImgs()
    {
        // удаляем файлы и
        // не забываем удалять из базы
        $ids = array();
        while ($img = array_shift($this->_imgs)) {
            $img->removeAll();
            $ids[] = $img->id;
        }
        
        if (count($ids) > 0) {
            $this->_db->queryt(
                'DELETE FROM `%s` WHERE `id` IN (' . implode(',', $ids) . ')',
                LPMTables::IMAGES
            );
        }
    }
    
    /**
     * Определяет количество загруженных фото
     */
    public function getLoadedCount()
    {
        return count($this->_imgs);
    }
    
    /**
     * Во время загрузки были встречены ошибки
     */
    public function isErrorsExist()
    {
        return count($this->_errors) > 0;
    }
    
    /**
     * Возвращает массив ошибок
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * Сохраняет информацию об изображении в базе данных
     * @param string $imgName
     * @return float идентификатор сохранённого изображения
     */
    private function saveInDB(LPMImg $img, mysqli_stmt $prepare)
    {
        $srcImgName = $img->getSrcImgName();
        $prepare->bind_param('ss', $srcImgName, $img->origName);
        $prepare->execute();
        $img->imgId = $this->_db->insert_id;
    }
    
    private function error($mess)
    {
        $this->_errors[] = $mess;
        return false;
    }

    private function addSize($sizeArr)
    {
        if (is_array($sizeArr) && count($sizeArr) > 0) {
            $size = array();
            $size[] = (int)$sizeArr[0];
            $size[] = (count($sizeArr) > 1) ? (int)$sizeArr[1] : $size[0];
            $this->_sizes[] = $size;
        }
    }
}
