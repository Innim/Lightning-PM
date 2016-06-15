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
	 * Количество загруженных изображений
	 * @var int
	 */
	//private $_loaded = 0;
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
	 * Префик фото
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
	 */
	function __construct( $maxPhotos = 1, $saveInDB = true, 
	                      $cacheSizes = null, $dir = '', $prefix = '',
	                      $itemType = 0, $itemId = 0 ) 
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

		$this->_itemType = $itemType;
		$this->_itemId = $itemId;
		
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

		// для вставленных через паст
		if ( isset( $_POST['clipboardImg'] ) )
		{
			if ($saveInDB && !$prepare = $this->_db->preparet( 
			     			'INSERT INTO `%s` (`url`, `userId`, `name`, `itemType`, `itemId`) ' .
			              	"VALUES ( ?, '" . $userId . "', ?, " ."'" . $this->_itemType . "', '" . $this->_itemId . "')", 
			     LPMTables::IMAGES)) 
			{
			    $this->error( 'Ошибка при записи в БД' );
			}
			else
			{
				$dirTempPath = LPMImg::getSrcImgPath('temp');
				if (!is_dir( $dirTempPath ) && !mkdir( $dirTempPath ))
				     return $this->error( 'Ошибка при создании директории' );

				    foreach ($_POST['clipboardImg'] as $imgCode) {
				    	$imgCode = str_replace('data:image/png;base64,', '', $imgCode);
				    	$imgCode = str_replace(' ', '+', $imgCode);
				    	$srcFileName = $dirTempPath . DIRECTORY_SEPARATOR . BaseString::randomStr( 10 ) . '.jpeg';
				    	$result = file_put_contents($srcFileName, base64_decode($imgCode));
				    }

				    $tempFiles = scandir($dirTempPath);
				    foreach ($tempFiles as $value) {
				    	if ($value!=="." and $value!=="..") {
				    		$fileSrc = $dirTempPath . DIRECTORY_SEPARATOR . $value;
				    		if ( $img = $this->loadImageFromTemp($fileSrc) )
				    		{
				    			if ($saveInDB) $this->saveInDB( $img, $prepare );
				    		} 
				    		else
				    		{
				    			// была ошибка - прерываем все
				    			break;
				    		}
				    	}
				    }

				    $tempFiles = scandir($dirTempPath);
				    foreach ($tempFiles as $value) {
				    	if ($value!=="." and $value!=="..") {
				    		$fileSrc = $dirTempPath . DIRECTORY_SEPARATOR . $value;
				    		unlink ($fileSrc);
				    	}
				    }
			}
		}

	    if (isset( $_FILES[self::IMG_INPUT_NAME] )) {	
	    	if ($saveInDB && 
	    	   !$prepare = $this->_db->preparet( 
	    	     'INSERT INTO `%s` (`url`, `userId`, `name`, `itemType`, `itemId`) ' .
	    	               "VALUES ( ?, '" . $userId . "', ?, " .
	    	                        "'" . $this->_itemType . "', '" . $this->_itemId . "')", 
	    	     LPMTables::IMAGES)) 
	    	{
	    	    $this->error( 'Ошибка при записи в БД' );
	    	} else {
			    foreach ($_FILES[self::IMG_INPUT_NAME]['tmp_name'] as $i => $tmpName) {
			        if ($tmpName != '') {
			            //if ($this->loadPhoto( $i )) $this->_loaded++;
			            if ($img = $this->loadPhoto( $i )) {
			            	if ($saveInDB) $this->saveInDB( $img, $prepare );
			            } else {
			            	// была ошибка - прерываем все
			            	break;
			            }
			            if ($this->getLoadedCount() >= $maxPhotos) break;
			        }
			    }
			    if ($saveInDB) $prepare->close();	
			    
			    // в случае ошибки удаляем изображения
			    if ($this->isErrorsExist()) $this->removeImgs(); 
	    	}	    
	    }

		//сохранение картинки по ссылке
		if (isset( $_POST['urls']) && is_array($_POST['urls'])) {
			if ($saveInDB && !$prepare = $this->_db->preparet( 
			     			'INSERT INTO `%s` (`url`, `userId`, `name`, `itemType`, `itemId`) ' .
			              	"VALUES ( ?, '" . $userId . "', ?, " ."'" . $this->_itemType . "', '" . $this->_itemId . "')", 
			     LPMTables::IMAGES)) 
			{
			    $this->error( 'Ошибка при записи в БД' );
			}

			$dirTempPath = LPMImg::getSrcImgPath('temp');
			if (!is_dir( $dirTempPath ) && !mkdir( $dirTempPath ))
				return $this->error( 'Ошибка при создании директории' );
			$srcFileName = $dirTempPath . DIRECTORY_SEPARATOR;
			//перебираем все ссылки
			foreach ($_POST['urls'] as $key => $value) {
				//если ссылка не пустая
				if (!empty($value)) {
	  				//получаем из нее картинку и сохраняем ее
	  				$srcFileName.= BaseString::randomStr( 10 ) . '.png';
	  				file_put_contents($srcFileName, fopen($value, 'r'), FILE_APPEND | LOCK_EX);
	  			}
	  		}

	  		$tempFiles = scandir($dirTempPath);
				    foreach ($tempFiles as $value) {
				    	if ($value!=="." and $value!=="..") {
				    		$fileSrc = $dirTempPath . DIRECTORY_SEPARATOR . $value;
				    		if ( $img = $this->loadImageFromUrl($fileSrc) )
				    		{
				    			if ($saveInDB) $this->saveInDB( $img, $prepare );
				    		} 
				    		else
				    		{
				    			// была ошибка - прерываем все
				    			break;
				    		}
				    	}
				    }

				    $tempFiles = scandir($dirTempPath);
				    foreach ($tempFiles as $value) {
				    	if ($value!=="." and $value!=="..") {
				    		$fileSrc = $dirTempPath . DIRECTORY_SEPARATOR . $value;
				    		unlink ($fileSrc);
				    	}
				    }
		}
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
		// удаляем файлы и 
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
	 * Загрузка i-того фото
	 * @param unknown_type $i
	 */
	private function loadPhoto( $i ) {
		$dir = $this->_dir;		
		
		// максимальный размер
        $maxSize = LPMImgUpload::MAX_SIZE * 1024 * 1024;
        $allowedTypes = array( 'jpg', 'jpeg', 'png' );
        
        $files = $_FILES[self::IMG_INPUT_NAME];
        // проверка размера
        if ($files['size'][$i] > $maxSize) {
            return $this->error( 
                        sprintf( 'Размер файла не должен превышать %d Мб', 
                                 $maxSize / ( 1024 * 1024 ) )
                   );
        }
        
        // проверка типа файла
        $fileType = explode( '/', $files['type'][$i] );
        if (count( $fileType ) != 2 || $fileType[0] != 'image'
            || !in_array( strtolower( $fileType[1] ), $allowedTypes )) {
            return $this->error( 'Вы можете загружать только файлы типов ' . 
                                    implode( ', ', $allowedTypes ) );
        }
        
        // проверяем, существует ли директория
        // и пытаемся создать, если не существует
        if ($dir != '') {
        	$dirPath = LPMImg::getSrcImgPath( $dir );
        	
        	if (!is_dir( $dirPath ) && !mkdir( $dirPath ))
                return $this->error( 'Ошибка при создании директории' );
                
	            if (substr( $dir, -1 ) != '/') $dir .= '/';      
        }                  
        // сохраняем исходный файл
        $ext = $fileType[1];
        do {
            $srcFileName = $dir . $this->_prefix . BaseString::randomStr( 10 ) . '.' . $ext;
            $srcFile = LPMImg::getSrcImgPath( $srcFileName );
        } while (file_exists( $srcFile ));
        
        if (!move_uploaded_file($files['tmp_name'][$i], $srcFile))
            return $this->error( 'Ошибка при сохранении файла' );
        // делаем необходимые изображения
        $img = new LPMImg( $srcFileName );
        $img->origName = $files['name'][$i];
        
        if ($this->_sizes != null) {
        	foreach ($this->_sizes as $size) {
        		$img->getCacheImg( $size[0], $size[1] );
        	}
        } else {
        	$img->getCacheImg();
        }
        
        $this->_imgs[] = $img;
        return $img;
	}

	private function loadImageFromUrl( $tempFileSrc ) {
		//копируем текущую директорию
		$dir = $this->_dir;
		//устанавливаем макс.размеры для картинки
		$maxSize = LPMImgUpload::MAX_SIZE * 1024 * 1024;
		//устанавливаем допустимые расширения для картинки
		$allowedTypes = array( 'jpg', 'jpeg', 'png' );
		/*проверяем.соответсвует ли файл макс.размеру*/
		if (stat($tempFileSrc)['size'] > $maxSize) {
        	unlink ($tempFileSrc);
        	return $this->error(
        		sprintf("File's size don't be more then %d Mb", LPMImg::MAX_SIZE)
        		);
        }
        /*проверяем,соответствуют ли картинки допустимым расширениям*/
        $extension = pathinfo($tempFileSrc, PATHINFO_EXTENSION);
        if (!in_array($extension, $allowedTypes)) {
        	unlink ($tempFileSrc);
        	return $this->error( 'Вы можете загружать только файлы типов ' . implode( ', ', $allowedTypes ) );
        }
        // проверяем, существует ли директория и пытаемся создать, если не существует
        if ($dir != '') {
        	$dirPath = LPMImg::getSrcImgPath( $dir );	
        	if (!is_dir( $dirPath ) && !mkdir( $dirPath ))
                return $this->error( 'Ошибка при создании директории' );
	        if (substr( $dir, -1 ) != '/') $dir .= '/'; 
        }
        //сохраняем файл в новой директории
        $ext = $allowedTypes[1];
        do {
            $srcFileName = $dir . $this->_prefix . BaseString::randomStr( 10 ) . '.' . $ext;
            $srcFile = LPMImg::getSrcImgPath( $srcFileName );
        } while (file_exists( $srcFile ));
        rename(	$tempFileSrc , $srcFile );
        //получаем изображение из директории и задаем ей префикс имени
        $img = new LPMImg( $srcFileName );
        $img->origName = "url_" . BaseString::randomStr( 10 ) . '.' . $ext;
        //кэшируем изображение
        if ($this->_sizes != null) {
        	foreach ($this->_sizes as $size) {
        		$img->getCacheImg( $size[0], $size[1] );
        	}
        } 
        else $img->getCacheImg();
   		//возвращаем картинку
        $this->_imgs[] = $img;
        return $img;
	}

	private function loadImageFromTemp( $tempFileSrc )
	{
		$dir=$this->_dir;
        $maxSize = LPMImgUpload::MAX_SIZE * 1024 * 1024;
        $allowedTypes = array( 'jpg', 'jpeg', 'png' );

        if (stat($tempFileSrc)['size']>$maxSize) {
        	unlink ($tempFileSrc);
        	return $this->error(
        		sprintf("File's size don't be more then %d Mb", LPMImg::MAX_SIZE)
        		);
        }

        $extension = pathinfo($tempFileSrc, PATHINFO_EXTENSION);
        if (!in_array($extension, $allowedTypes)) {
        	unlink ($tempFileSrc);
        	return $this->error( 'Вы можете загружать только файлы типов ' . implode( ', ', $allowedTypes ) );
        }

        // проверяем, существует ли директория и пытаемся создать, если не существует
        if ($dir != '') {
        	$dirPath = LPMImg::getSrcImgPath( $dir );
         	
        	if (!is_dir( $dirPath ) && !mkdir( $dirPath ))
                return $this->error( 'Ошибка при создании директории' );
                
	        if (substr( $dir, -1 ) != '/') $dir .= '/'; 
        }

        $ext = $allowedTypes[1];
        do {
            $srcFileName = $dir . $this->_prefix . BaseString::randomStr( 10 ) . '.' . $ext;
            $srcFile = LPMImg::getSrcImgPath( $srcFileName );
        } while (file_exists( $srcFile ));
        rename(	$tempFileSrc , $srcFile );

        $img = new LPMImg( $srcFileName );
        $img->origName = "clb_paste_" . BaseString::randomStr( 10 ) . '.' . $ext;
        
        if ($this->_sizes != null) {
        	foreach ($this->_sizes as $size) {
        		$img->getCacheImg( $size[0], $size[1] );
        	}
        } else {
        	$img->getCacheImg();
        }
        
        $this->_imgs[] = $img;
        return $img;
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