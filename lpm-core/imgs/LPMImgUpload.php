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
		/*}*/
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