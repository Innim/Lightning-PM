<?php
/**
 * Обработка загрузки изображений.
 * Предполагается, что поле для загрузки изображения имеет определенное имя.
 * Также надо не забывать указывать <code>enctype="multipart/form-data"</code> у формы 
 * @author GreyMag
 * @see LMPImgUpdload::IMG_INPUT_NAME
 *
 */
class LPMImgUpload { 
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
	//private $_userId;
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
	 * @param boolean $defaultLoad Будет выполнена загрука по умолчанию (из $_FILES с именем IMG_INPUT_NAME)
	 */
	function __construct( $maxPhotos = 1, $saveInDB = true, 
	                      $cacheSizes = null, $dir = '', $prefix = '',
	                      $itemType = 0, $itemId = 0, $defaultLoad = true) 
	{
		/*if (!BOEngine::getInstance()->isLogin()) {
			$this->error( 'Только авторизованные пользователи могут загружать изображения' );
		} else {*/
		$engine = LightningEngine::getInstance();
		$userId = $engine->isAuth()
		           ? $engine->getAuth()->getUserId()
		           : 0;
		$this->_db = LPMGlobals::getInstance()->getDBConnect();
		
		$this->_dir = $dir;
		$this->_prefix = $prefix;
		$this->_saveInDB = $saveInDB;

		$this->_itemType = $itemType;
		$this->_itemId = $itemId;

		$this->_maxPhotos = $maxPhotos;
		
        // сохраняем размеры
		if (is_array( $cacheSizes ) && count( $cacheSizes ) > 0) {
			$this->_sizes = array();
			if (is_array( $cacheSizes[0] )) {
				foreach ($cacheSizes as $size) 
					$this->addSize( $size );				
			} else {
				$this->addSize( $cacheSizes );
			}
			if (count( $this->_sizes ) == 0) $this->_sizes = null;
		}
		// Выполняем загрузку по умолчанию
		if ($defaultLoad) $this->uploadViaFiles(self::IMG_INPUT_NAME);
	}

	/**
	 * Осуществляет подготовку изображений 
	 * @param  string $name Имя поля с файлами для загрузки
	 * @return boolean
	 */
	public function uploadViaFiles($name)
	{
	    if (isset($_FILES[$name]))
	    {
	    	$files = array();
	    	$names = array();

	    	foreach ($_FILES[$name]['tmp_name'] as $i => $tmpName) 
	    	{
	    		if (!empty($tmpName))
	    		{
	    			$files[] = $tmpName;
	    			$names[] = $_FILES[$name]['name'][$i];
	    		}
	    	}

	    	return $this->addImages($files, true, $names, false);
	    }
	    else 
	    {
	    	return true;
	    }
	}

	/**
	 * Загружает изображения из массива строк base64 
	 * (используется для загрузки из ьуфера обмена)
	 * @param  array $array 
	 * @return boolean
	 */
	public function uploadFromBase64($array)
	{
		// Создадим временную директорию 
	    $dirTempPath = LPMImg::getSrcImgPath('temp');

	    if (!is_dir($dirTempPath) && !mkdir($dirTempPath))
	    	return $this->error('Ошибка при создании директории');

		// Перебираем массив и записываем в файлы
    	$files = array();
    	$names = array();
	    foreach ($array as $value) 
	    {
	    	if (!empty($value))
	    	{
		    	$value = str_replace(array('data:image/png;base64,', ' '), array('', '+'), $value);
		    	$filename = $dirTempPath . DIRECTORY_SEPARATOR . BaseString::randomStr(10) . '.jpeg';

		    	if (!file_put_contents($filename, base64_decode($value))) 
		    	{
		    		$this->error('Ошибка при записи в файл');
		    		break;
		    	}
		    	else 
		    	{
		    		$files[] = $filename;
		    		$names[] = 'clb_paste_' . date('YmdHis_u') . '.jpg';
		    	}
		    }
	    }
		
		// Если были ошибки - то удаляем все, что сохранили
		if ($this->isErrorsExist())
		{
			$this->clearTmpImages($files);
			return false;
		}
		else
		{
			return $this->addImages($files, false, $names);
		}
	}
	
	/**
	 * Загружает изображения по url
	 * @param  array $urls Массив URL адресов
	 * @return boolean
	 */
	public function uploadFromUrls($urls)
	{
		// Создадим временную директорию 
	    $dirTempPath = LPMImg::getSrcImgPath('temp');

	    if (!is_dir($dirTempPath) && !mkdir($dirTempPath))
	    	return $this->error('Ошибка при создании директории');

    	$files = array();
    	$names = array();

	    //перебираем все ссылки
		foreach ($urls as $value) 
		{
			//если ссылка не пустая
			$value = trim($value);
			if (!empty($value)) {
  				//получаем из нее картинку и сохраняем ее
  				$srcFileName = $dirTempPath . DIRECTORY_SEPARATOR . BaseString::randomStr( 10 ) . '.png';
  				
  				//проверка, если картинка из сервиса droplr (http://droplr.com/) 
  				if (preg_match("/^https?:\/\/d.pr\/[a-z0-9\/]+$/i", $value))
  					$value.= '+';
  				//проверка, если картинка из сервиса ownCloud (http://cloud.innim.ru/) 
  				else if (preg_match("/^https?:\/\/cloud.innim.ru\/(index.php\/)?s\/[a-z0-9]+$/i", $value))
  					$value.= '/download';
 					
  				//если картинку скачать не удалось - прерываем запись файла
  				if (!file_put_contents($srcFileName, fopen($value, 'r'), FILE_APPEND | LOCK_EX)) 
		    	{
		    		$this->error('Ошибка при записи в файл');
		    		break;
		    	}

	    		$files[] = $srcFileName;
	    		$names[] = 'url_' . date('YmdHis_u') . '.png'; // тут ды настоящее имя выделить из url
  			}
  		}

		// Если были ошибки - то удаляем все, что сохранили
		if ($this->isErrorsExist())
		{
			$this->clearTmpImages($files);
			return false;
		}
		else
		{
			return $this->addImages($files, false, $names);
		}
	}

	private function clearTmpImages($files)
	{
	    foreach ($files as $filename)
		{
			if (file_exists($filename)) @unlink($filename);
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
		if ($this->_saveInDB && 
			!($prepare = $this->_db->preparet("INSERT INTO `%s` (`url`, `userId`, `name`, `itemType`, `itemId`) VALUES (?, '{$userId}', ?, '{$this->_itemType}', '{$this->_itemId}')", LPMTables::IMAGES)))
		{
	   		return $this->error('Ошибка при записи в БД');
		}
		else 
		{	
			// Перебираем все файлы 
			foreach ($files as $i => $file) 
			{
				if ($this->getLoadedCount() + 1 > $this->_maxPhotos) 
				{
					if ($clearTmp) $this->clearTmpImages($files);
					break;
				}

				// Загружаем файл, если была ошибка - прерываем все
				$originalName = null !== $originalNames && isset($originalNames[$i]) 
					? $originalNames[$i] : null;
				if (!($img = $this->loadImage($file, $uploaded, $originalName))) break;
				// Выполняем запрос записи в БД
				if ($this->_saveInDB) $this->saveInDB($img, $prepare);	
			}
		}

		// Закрываем подготовленный запрос
		if ($this->_saveInDB) $prepare->close();	

		// Если были ошибки - то удаляем все, что загружено
		if ($this->isErrorsExist())
		{
			$this->removeImgs();
			if ($clearTmp) $this->clearTmpImages($files);
			return false;
		}
		else 
		{
			return true;
		}
	}

	private function loadImage($filepath, $uploaded = false, $originalName = null)
	{			
		if (!file_exists($filepath)) return $this->error('Не удалось загрузить файл');

		// Директория сохранения
	    $dir = $this->_dir;

	    // Максимальный размер
        $maxSize = LPMImgUpload::MAX_SIZE * 1024 * 1024;
        $allowedTypes = array(//'jpg', /*'jpeg',*/ 'png');
        	// тип => расширение
            IMAGETYPE_JPEG        	=> 'jpg',
            IMAGETYPE_PNG         	=> 'png',
            IMAGETYPE_JPEG2000		=> 'jpeg'
        );
        
        // Проверяем вес файла
        $size = filesize($filepath);
        if ($size > $maxSize) return $this->error(sprintf(
        	'Размер файла не должен превышать %d Мб', LPMImgUpload::MAX_SIZE));

        // Проверяем тип файла и получаем расширение
		list($width, $height, $type, $attr) = getimagesize($filepath);
		if (!isset($allowedTypes[$type])) return $this->error(
			'Вы можете загружать только файлы типов ' . implode(', ', array_unique((array_values($allowedTypes)))));
		$ext = $allowedTypes[$type];

		// Проверяем, существует ли директория
        // и пытаемся создать, если не существует
		if (!empty($dir))
		{
        	$dirpath = LPMImg::getSrcImgPath($dir);

        	if (!is_dir($dirpath) && !mkdir($dirpath))
				return $this->error('Ошибка при создании директории');
    		
    		$dls = mb_substr($dir, -1);
    		if ($dls !== '/') $dir .= '/';
		}

		// Сохраняем исходный файл
		// Ищем уникальное имя 
		do 
		{
            $srcFilename = $dir . $this->_prefix . BaseString::randomStr( 10 ) . '.' . $ext;
            $srcFilepath = LPMImg::getSrcImgPath($srcFilename);
        } while (file_exists($srcFilepath));

        // Пемемещаем исходный файл
        $moveFunc = $uploaded ? 'move_uploaded_file' : 'rename';
        if (!call_user_func($moveFunc, $filepath, $srcFilepath))
        	return $this->error('Ошибка при сохранении файла');

        // Генерируем необходимые изображения 
        $img = new LPMImg($srcFilename);
        $img->origName = null === $originalName ? $originalName : '';

        if (null !== $this->_sizes)
        {
        	foreach ($this->_sizes as $size) 
        	{
        		$img->getCacheImg($size[0], $size[1]);
        	}
        }
        else 
        {
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
	public function getImgByIndex( $index ) {
		return $index >= 0 && $index < count( $this->_imgs ) 
		    ? $this->_imgs[$index] : null; 
	}
	
	/**
	 * Удаляет загруженные фотографии и их кэши
	 */
	public function removeImgs() {
		// удаляем файли и 
		// не забываем удалять из базы
		$ids = array();
		while ($img = array_shift( $this->_imgs )) {		
			$img->removeAll();
			$ids[] = $img->id;
		}
		
		if (count( $ids ) > 0) {
			$this->_db->queryt( 
			 'DELETE FROM `%s` WHERE `id` IN (' . implode( ',', $ids ) . ')',
			 LPMTables::IMAGES
			);
		}
	}
	
	/**
	 * Определяет количество загруженных фото
	 */
	public function getLoadedCount() {
		return count( $this->_imgs );
	}
	
	/**
	 * Во время загрузки были встречены ошибки
	 */
	public function isErrorsExist() {
		return count( $this->_errors ) > 0;
	}
	
	/**
	 * Возвращает массив ошибок
	 */
	public function getErrors() {
		return $this->_errors;
	}

	/**
	 * Сохраняет информацию об изображении в базе данных
	 * @param string $imgName
	 * @return float идентификатор сохранённого изображения
	 */
	private function saveInDB( LPMImg $img, mysqli_stmt $prepare ) {
		$srcImgName = $img->getSrcImgName();
		$prepare->bind_param( 'ss', $srcImgName, $img->origName );
		$prepare->execute();
		$img->imgId = $this->_db->insert_id;
	} 
	
	private function  error( $mess ) {
		$this->_errors[] = $mess;
		return false;
	}

	private function addSize( $sizeArr ) {		
		if (is_array( $sizeArr ) && count( $sizeArr ) > 0) {
			$size = array();
			$size[] = (int)$sizeArr[0];
			$size[] = (count( $sizeArr ) > 1) ? (int)$sizeArr[1] : $size[0];
			$this->_sizes[] = $size;
		}		
	}
}
?>