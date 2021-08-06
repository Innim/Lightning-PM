<?php
// XXX: сделать чтобы работал или выпилить
class ASyncUploader
{
    private $_success = true;
    private $_error = '';
    private $_auth;
    private $_db;
    private $_user;
    
    public function __construct()
    {
        $this->_auth = new LPMAuth();
        $this->_db = LPMGlobals::getInstance()->getDBConnect();
    }
    
    public function init($params)
    {
        if (!$this->_auth->isLogin()) {
            return $this->error('Ошибка аутентификации');
        }
        
        $this->_user = User::load($this->_auth->getUserId());
        if (!$this->_user->isAdmin()) {
            return $this->error('Недостаточно прав');
        }
        
        if (!isset($params['type'], $params['id'])) {
            return $this->error('Неверные параметры');
        }
            
        switch ($params['type']) {
            case 'preview': {
                $newsId = (float)$_REQUEST['id'];
                // выбираем новость из БД
                $news = News::load($newsId);
                if (!$news) {
                    return $this->error('Не найдено новости с таким идентификатором');
                }
                
                $uploader = new BOImgUpload(
                    1,
                    false,
                    array( News::PREVIEW_WIDTH, News::PREVIEW_HEIGHT ),
                    News::PREVIEWS_DIR,
                    News::PREVIEWS_PREFIX
                );
                if ($uploader->isErrorsExist()) {
                    $errors = $uploader->getErrors();
                    return $this->error($errors[0]);
                }
                
                if ($uploader->getLoadedCount() == 0) {
                    return $this->error('Файлы не загружены');
                }
                
                $img = $uploader->getImgByIndex(0);
                
                $sql = 'UPDATE `%s` ' .
                          "SET `previewImg` = '" . $img->getSrcImgName() . "' " .
                        'WHERE `id` = ' . $newsId;
                if (!$this->_db->queryt($sql, BOTables::NEWS)) {
                    if ($uploader) {
                        $uploader->removeImgs();
                    }
                    return $this->saveInDBError();
                } else {
                    // удаляем старое изображение если оно было
                    if ($news->previewImg) {
                        $news->previewImg->removeAll();
                    }
                }
            } break;
            case 'photo': {
                $newsId = (float)$_REQUEST['id'];
                // выбираем новость из БД
                $news = News::load($newsId);
                if (!$news) {
                    return $this->error('Не найдено новости с таким идентификатором');
                }
                
                $uploader = new BOImgUpload(
                    1,
                    true,
                    array(
                                                array( News::PHOTO_WIDTH, News::PHOTO_HEIGHT ),
                                                array( News::PHOTO_PRV_WIDTH, News::PHOTO_PRV_HEIGHT )
                                             ),
                    News::PHOTOS_DIR,
                    News::PHOTOS_PREFIX
                );
                if ($uploader->isErrorsExist()) {
                    $errors = $uploader->getErrors();
                    return $this->error($errors[0]);
                }
                
                if ($uploader->getLoadedCount() == 0) {
                    return $this->error('Файлы не загружены');
                }
                
                $img = $uploader->getImgByIndex(0);
                
                $sql = 'UPDATE `%s` ' .
                          "SET `imgId` = '" . $img->id . "' " .
                        'WHERE `id` = ' . $newsId;
                if (!$this->_db->queryt($sql, BOTables::NEWS)) {
                    if ($uploader) {
                        $uploader->removeImgs();
                    }
                    return $this->saveInDBError();
                } else {
                    // удаляем старое изображение если оно было
                    if ($news->photoImg) {
                        $news->photoImg->removeAll();
                    }
                }
            } break;
            default: return $this->error('Недопустимый тип');
        }
        
        return $this->answer();
    }
    
    private function saveInDBError($errStr = '')
    {
        return $this->error($errStr == '' ? 'Ошибка сохранения в БД' : $errStr);
    }
    
    private function loadFromDBError($errStr = '')
    {
        return $this->error($errStr == '' ? 'Ошибка загрузки из БД' : $errStr);
    }
        
    private function error($errStr)
    {
        $this->_success = false;
        $this->_error = $errStr;
        $this->answer();
        return false;
    }
    
    private function answer()
    {
        $answer = array( 'success' => $this->_success );
        if (!$this->_success) {
            $answer['error'] = $this->_error;
        }
        //header('Content-Type: application/json');
        echo json_encode($answer);
        return true;
    }
}
