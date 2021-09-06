<?php
/**
 * Глобальные опции проекта
 * @author GreyMag
 */
class LPMGlobals extends Globals
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function createOptions()
    {
        return new LPMOptions();
    }

    protected function createDBConnect()
    {
        $db = parent::createDBConnect();
        $db->set_charset('utf8mb4');
        return $db;
    }
}
