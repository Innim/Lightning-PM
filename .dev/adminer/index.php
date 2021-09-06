<?php

require '../../lpm-config.inc.php';

function adminer_object()
{
    class AdminerSoftware extends Adminer
    {
        public function name()
        {
            return '<a href="../..">Lightning PM</a>';
        }
    
        public function credentials()
        {
            return [MYSQL_SERVER, MYSQL_USER, MYSQL_PASS];
        }
    
        public function database()
        {
            return DB_NAME;
        }

        public function login($login, $password)
        {
            return true;
        }
    }
  
    return new AdminerSoftware;
}

include './adminer-4.8.1-mysql-en.php';
