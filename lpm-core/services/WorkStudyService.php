<?php
require_once(dirname(__FILE__) . '/../init.inc.php');

class WorkStudyService extends LPMBaseService
{
    /**
     *
     * @var WSUtils
     */
    private $_utils;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->_utils = new WSUtils();
    }
    
    public function addWorker($userId, $hours, $comingTime)
    {
        if ($comingTime == '') {
            $comingTime = 0;
        }
        if ($comingTime != 0 && !$this->_utils->checkTime($comingTime)) {
            return $this->error('Неверное время прихода');
        }
        
        $hours  = max(min((int)$hours, 24), 0);
        $userId = (float)$userId;
        
        // проверяем, что такой пользователь существует,
        // а работник нет
        $sql = "select `%1\$s`.`userId`, `%2\$s`.`id` from `%1\$s` left join `%2\$s` " .
                                       "on `%1\$s`.`userId` = `%2\$s`.`userId` " .
                                    "where `%1\$s`.`userId` = '" . $userId . "' " .
                                    "limit 0,1";
        if (!$query = $this->_db->queryt($sql, LPMTables::USERS, LPMTables::WORKERS)) {
            return $this->errorDBLoad();
        }
        
        if (!$row = $query->fetch_assoc()) {
            return $this->error('Нет такого пользователя');
        }
        if (!empty($row['id'])) {
            return $this->error('Такой работник уже добавлен |' . $row['id'] . '|');
        }
        
        $sql = "insert into `%s` ( `userId`, `hours`, `comingTime` ) " .
                          "values ( '" . $userId . "', '" . $hours . "', '" . $comingTime . "')";
        
        if (!$this->_db->queryt($sql, LPMTables::WORKERS)) {
            return $this->errorDBSave();
        }
        
        $this->add2Answer('id', $this->_db->insert_id);
        
        return $this->answer();
    }
    
    public function beforeFilter($calledFunc)
    {
        if (!parent::beforeFilter($calledFunc)) {
            return false;
        }
        
        // TODO проверка можераторских прав
        //if (!$this->_auth->)
        
        return true;
    }
}
