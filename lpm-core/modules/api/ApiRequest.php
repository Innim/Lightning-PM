<?php

class ApiRequest
{
    /**
     * Переводит значение настройки php.ini, заданное с суффиксом единицы
     * измерения (`512M`), в байты.
     * @param  string $value Значение настройки.
     * @return int Размер в байтах или 0, если значение пустое.
     */
    private static function parseIniSize($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        $number = (float)$value;
        switch (strtolower(substr($value, -1))) {
            case 'g':
                return (int)($number * 1024 * 1024 * 1024);
            case 'm':
                return (int)($number * 1024 * 1024);
            case 'k':
                return (int)($number * 1024);
            default:
                return (int)$number;
        }
    }

    /**
     * Приводит данные о загруженных файлах к одному виду.
     *
     * Поле с одним файлом (`files`) PHP описывает скалярами, а поле с несколькими
     * (`files[]`) - массивами. Разбирать оба вида в каждом потребителе незачем,
     * поэтому одиночный файл описывается здесь массивом из одного элемента.
     * @param  array $files Данные о файлах в виде $_FILES.
     * @return array Те же данные, где каждое свойство файла - массив.
     */
    private static function normalizeFiles(array $files)
    {
        foreach ($files as $name => $filesData) {
            if (!is_array($filesData) || !isset($filesData['name']) || is_array($filesData['name'])) {
                continue;
            }

            foreach ($filesData as $key => $value) {
                $files[$name][$key] = [$value];
            }
        }

        return $files;
    }

    /**
     * Тип содержимого запроса, которым передаются файлы.
     */
    const MULTIPART_CONTENT_TYPE = 'multipart/form-data';

    private $engine;
    private $method;
    private $path;
    private $query;
    private $body;
    private $files;

    public function __construct(LightningEngine $engine, $input)
    {
        $this->engine = $engine;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD']);
        $this->path = array_values($engine->getParams()->getArgs());
        $this->query = ApiKey::getQueryArgs();

        $this->checkSizeLimit();

        if ($this->isMultipart()) {
            // Файлы PHP принимает только у POST: у остальных методов тело
            // multipart не разбирается и осталось бы просто потерянным
            if ($this->method !== 'POST') {
                throw new ApiException(self::MULTIPART_CONTENT_TYPE . ' is supported only for POST requests', 400);
            }

            $this->body = $_POST;
            $this->files = self::normalizeFiles($_FILES);

            // Загрузчики приложения читают $_FILES напрямую
            // ({@see LPMImgUpload::uploadViaFiles()}), поэтому приведённый
            // к одному виду набор должен быть виден и им
            $_FILES = $this->files;
        } else {
            $this->body = $this->decodeBody($input);
            $this->files = [];
        }
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getQuery($name, $default = null)
    {
        return array_key_exists($name, $this->query) ? $this->query[$name] : $default;
    }

    /**
     * Значение поля тела запроса.
     *
     * В multipart-запросе значения полей - всегда строки, в том числе
     * у логических параметров: `false` приходит как `"false"`.
     * @param  string $name    Имя поля.
     * @param  mixed  $default Значение, если поля в запросе нет.
     * @return mixed
     */
    public function getBody($name, $default = null)
    {
        return array_key_exists($name, $this->body) ? $this->body[$name] : $default;
    }

    /**
     * Значение логического поля тела запроса.
     *
     * Строки `"false"`, `"0"` и пустая строка - это `false`: в multipart-запросе
     * значение поля приходит строкой, и непустая строка `"false"` иначе
     * означала бы истину.
     * @param  string $name Имя поля.
     * @return bool
     */
    public function getBodyFlag($name)
    {
        $value = $this->getBody($name);
        if (is_string($value)) {
            $value = trim(strtolower($value));

            return $value !== '' && $value !== '0' && $value !== 'false';
        }

        return !empty($value);
    }

    /**
     * Файлы, загруженные в поле multipart-запроса.
     *
     * Отдаются в том же виде, в каком их передаёт PHP через $_FILES, -
     * структурой из массивов по каждому свойству файла, которую принимают
     * {@see FileUploadManager} и {@see LPMImgUpload}.
     * @param  string $name Имя поля.
     * @return array|null Данные поля или null, если файлов в нём нет.
     */
    public function getFiles($name)
    {
        if (!isset($this->files[$name]) || !is_array($this->files[$name])
                || !isset($this->files[$name]['name'])) {
            return null;
        }

        $filesData = $this->files[$name];

        return FileUploadManager::hasUploads($filesData) ? $filesData : null;
    }

    /**
     * Передаются ли в запросе файлы, то есть разобран ли он как форма.
     * @return bool
     */
    private function isMultipart()
    {
        $contentType = '';
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'];
        } elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
        }

        return stripos(trim($contentType), self::MULTIPART_CONTENT_TYPE) === 0;
    }

    /**
     * Проверяет, что запрос уложился в ограничение PHP на размер тела.
     *
     * Превысив его, запрос приходит в обработчик выпотрошенным: PHP молча
     * отбрасывает и разобранные поля, и файлы, и php://input, - поэтому
     * без этой проверки клиент получил бы жалобу на отсутствие полей
     * вместо внятной причины.
     * @throws ApiException Если тело запроса больше допустимого.
     */
    private function checkSizeLimit()
    {
        $limit = self::parseIniSize(ini_get('post_max_size'));
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;

        if ($limit > 0 && $contentLength > $limit) {
            throw new ApiException(
                'Request is too large: the whole request must fit in ' . ini_get('post_max_size'),
                413
            );
        }
    }

    private function decodeBody($input)
    {
        $input = trim((string)$input);
        if ($input === '') {
            return [];
        }

        $data = json_decode($input, true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON body');
        }

        return $data;
    }
}
