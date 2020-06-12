<?php
/**
 * Фото
 * @author GreyMag
 *
 */
class BOPhoto extends BOBaseObject
{
    public static function loadList4Item($itemType, $itemId)
    {
        return self::loadList("`itemType` = '" . $itemType . "' " .
                           "AND `itemId` = '" . $itemId . "'");
    }
    
    public static function loadList($where = '', $orderBy = '')
    {
        $fields4Select = array(
          '`%1$s`.*'
        );
        
        //$userId = BOEngine::getInstance()->getUserId();
        //$loadVotes = $loadVotes && $userId > 0;
        
        // загрузка информации о голосах
        /* if ($loadVotes) {
             array_push(
                 $fields4Select,
                 '`%4$s`.`vote`',
                 '`%2$s`.`votes`'
             );
         }*/
        
        // выбираем из базы
        $sql = 'SELECT ' . implode(', ', $fields4Select) . ' ' .
                 'FROM `%1$s` ';
  
        /* if ($loadVotes) {
             $sql .=
              'LEFT JOIN `%4$s`' .
                    "ON `%4\$s`.`userId` = '" . $userId . "' " .
                   "AND `%4\$s`.`itemType` = '" . CommentedObject::ITEM_TYPE_NEWS . "' " .
                   'AND `%4$s`.`itemId` = `%1$s`.`id` ';
         }*/
                
        
        if ($where   != '') {
            $sql .= 'WHERE ' . $where . ' ';
        }
        if ($orderBy != '') {
            $sql .= 'ORDER BY ' . $orderBy . ' ';
        }
        
        //if ($limitFrom >= 0 && $limitCount > 0)
        //    $sql .= 'LIMIT ' . $limitFrom . ',' . $limitCount . ' ';
            
        return StreamObject::loadObjList(
            array( $sql, BOTables::IMAGES, BOTables::VOTES ),
            __CLASS__
        );
    }
    
    /**
     *
     * @var BOImg
     */
    public $img;
    
    public $id;
    public $url = '';
    public $userId;
    /**
     * Всего голосов
     * @var int
     */
    public $votes;
    /**
     * Голоса текущего пользователя
     * @var int
     */
    public $vote = 0;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->_typeConverter->addFloatVars('id', 'userId');
        $this->_typeConverter->addIntVars('vote', 'votes');
        
        $this->addImgFields('img');
    }
    
    public function loadStream($hash)
    {
        if (!parent::loadStream($hash)) {
            return false;
        }
        
        if ($this->url != '') {
            $this->setVar('img', $this->url);
        }
          
        return true;
    }
}
