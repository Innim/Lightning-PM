<?php

require_once( dirname( __FILE__ ) . '/../init.inc.php' );

class UsersService extends LPMBaseService 
{
	public function lockUser ($userId, $isLock){ 
        
        $locked = $isLock ? 1 : 0;
        $userId = (int)$userId;
        if (!$this->checkRole( User::ROLE_MODERATOR )) return $this->error( 'Недостаточно прав' );
        
        if ($userId > 0) {
            $sql = "UPDATE `%s` SET " .
		        "`locked` = '" . $locked . "' " .
			    "WHERE `userId` = '" . $userId . "'";
		
		    if (!$this->_db->queryt( $sql, LPMTables::USERS )) 
			    return $this->error( 'Ошибка записи в БД' );        
        }        
		
		return $this->answer();
    }
}

?>