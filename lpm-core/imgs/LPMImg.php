<?php
/**
 * Загруженное изображение
 * @author greymag
 */
class LPMImg extends LPMBaseObject
{
    public static function loadListByInstance($instanceType, $instanceId)
    {
        return StreamObject::loadListDefault(
            self::getDB(),
            '`deleted` = 0 AND `itemType` = ' . $instanceType .
                    ' AND `itemId` = ' . $instanceId,
            LPMTables::IMAGES,
            __CLASS__
        );
    }

    public static function loadCountByInstance($instanceType, $instanceId)
    {
        $res = self::buildAndExecute([
            'SELECT' => 'COUNT(*) AS `count`',
            'FROM'   => LPMTables::IMAGES,
            'WHERE'  => [
                '`deleted` = 0',
                '`itemType` = ' . $instanceType,
                '`itemId` = ' . $instanceId 
            ]
        ]);

        if (!$res) {
            throw new \GMFramework\ProviderLoadException();
        }

        return (int)$res->fetch_assoc()['count'];
    }

    public static function loadListByProject($projectId)
    {
        return self::loadListByInstance(LPMInstanceTypes::PROJECT, $projectId);
    }
    
    public static function loadListByIssue($issueId)
    {
        return self::loadListByInstance(LPMInstanceTypes::ISSUE, $issueId);
    }


    public static function getImgPath($imgName = '')
    {
        return ROOT . UPLOAD_IMGS_DIR . $imgName;
    }
    
    public static function getSrcImgPath($imgName = '')
    {
        return self::getImgPath() . self::SRC_DIR . $imgName;
    }
    
    public static function getImgURL($imgName = '')
    {
        return SITE_URL . UPLOAD_IMGS_DIR . $imgName;
    }
    
    const SRC_DIR = 'src/';
    const PREVIEW_WIDTH = 150;
    const PREVIEW_HEIGHT = 100;
    
    /**
     * Идентификатор изображения в баз
     * @var float
     */
    public $imgId = 0;
    public $name = '';
    public $origName = '';
    public $desc = '';

    /**
     * Сохраненный адрес
     * @var string
     */
    //private $_url;
    
    private $_srcImgName;
    /**
     * Абсолютный путь до исходного изображения
     * @var string
     */
    private $_srcImg;
    
    private $_imgDir = '';
    private $_imgName;
    private $_imgExt;
    /**
     * @var \Verot\Upload\Upload
     */
    private $_upload;
    private $_errors = array();
    
    public function __construct($srcImgName = null)
    {
        parent::__construct();

        if ($srcImgName !== null) {
            $this->setSrcImgName($srcImgName);
        }
    }
    
    /**
     * Возвращает url до картинки с заданными размерами.
     * Если картинка с такими размерами не найдена - пытается создать её из исходной
     * @param int $width ширина требуемого изображения,
     * если не задана - размер исходного файла
     * @param int $height высота требуемого изображения,
     * если не задана - равно ширине
     * @return string
     */
    public function getCacheImg($width = -1, $height = -1)
    {
        if ($height == -1) {
            $height = $width;
        }
        
        $cacheImgNameBody = $this->_imgName;
        if ($width != -1) {
            $cacheImgNameBody .= '_' . $width;
            if ($width != $height) {
                $cacheImgNameBody .= 'x' . $height;
            }
        }
        $cacheImgName = $this->_imgDir . $cacheImgNameBody . '.' . $this->_imgExt;
        
        $cacheImg = self::getImgPath($cacheImgName);
        
        if (!file_exists($this->_srcImg)) {
            $this->_errors[] = 'Не найдено исходное изображение';
            return false;
        } elseif (!file_exists($cacheImg)) {
            if (!$this->_upload) {
                $this->_upload = new \Verot\Upload\Upload($this->_srcImg);
            }
            $this->_upload->file_auto_rename = false;
            $this->_upload->file_overwrite   = true;
            if ($width != -1) {
                $this->_upload->image_resize     = true;
                $this->_upload->image_ratio_crop = true;
                $this->_upload->image_x          = $width;
                $this->_upload->image_y          = $height;
                $this->_upload->image_background_color = '#ffffff';
            }
            $this->_upload->file_new_name_body = $cacheImgNameBody;
            
            $this->_upload->process(self::getImgPath() . $this->_imgDir);
            
            // успешность операции никого не волнует
            if (!$this->_upload->processed) {
                $this->_errors[] = $this->_upload->error;
            }
        }
        
        return self::getImgURL($cacheImgName);
    }

    /**
     * Возвращает url превью изображения
     * (при необходимости создавая его)
     * @return string
     */
    public function getPreview()
    {
        return $this->getCacheImg(self::PREVIEW_WIDTH, self::PREVIEW_HEIGHT);
    }

    /**
     * Возвращает url исходного изображения
     * @return string
     */
    public function getSource()
    {
        return self::getImgURL(self::SRC_DIR . $this->_srcImgName);
    }
    
    public function getSrcImgName()
    {
        return $this->_srcImgName;
    }
    
    /**
     * Абсолютный путь до исходного изображения
     * @var string
     */
    public function getSrcImg()
    {
        return $this->_srcImg;
    }

    public function setSrcImg($value)
    {
        $baseSrcPath = self::getSrcImgPath();
        $subPath = stripos($value, $baseSrcPath) === 0 
            ? mb_substr($value, mb_strlen($baseSrcPath))
            : basename($value);
        $this->setSrc($value, $subPath);
    }
    
    /**
     * Удаляет кэшированные изображения
     */
    public function removeCache()
    {
        $mask = self::getImgPath(
            $this->_imgDir . $this->_imgName .
                   '*' .
                   '.' . $this->_imgExt
        );
        $files = glob($mask);
        if (!is_array($files)) {
            return false;
        }
        foreach ($files as $file) {
            FileSystemUtils::remove($file);
        }
        
        return true;
    }
    
    /**
     * Удаляет исходник и кэшированные изображения
     */
    public function removeAll()
    {
        $this->removeCache();
        FileSystemUtils::remove($this->_srcImg);
    }

    protected function setVar($var, $value)
    {
        if ($var === 'url') {
            $this->setSrcImgName($value);
        } else {
            return parent::setVar($var, $value);
        }
    }

    private function setSrcImgName($value)
    {
        //$this->_url = $value;
        // TODO обработку передаваемого полного url
        $this->setSrc(self::getSrcImgPath($value), $value);
    }

    private function setSrc($fullPath, $subPath) {
        $this->_srcImgName = $subPath;
        $this->_srcImg = $fullPath;
        $nameParts = explode('.', $subPath);
        $this->_imgExt  = array_pop($nameParts);
        
        $dirParts  = explode('/', implode('.', $nameParts));
        $this->_imgName = array_pop($dirParts);
        $this->_imgDir  = implode('/', $dirParts);
        if ($this->_imgDir != '') {
            $this->_imgDir .= '/';
        }
    }
}
