<?php
class WSRecord extends LPMBaseObject
{
    public $date;
    public $comingTime;
    public $leavingTime;
    public $late = -1;
    public $lunchBreak;
    public $hoursAway = 0;
    public $mustHours;
    public $mustComingTime;
    
    private $_comingTime     = '';
    private $_leavingTime    = '';
    private $_mustComingTime = '';
    
    /**
     *
     * @var WSUtils
     */
    private $_utils;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->_utils = new WSUtils();
        
        $this->addDateTimeFields('date');
        $this->_typeConverter->addBoolVars('late', 'lunchBreak');
        $this->_typeConverter->addIntVars('hoursAway', 'mustHours');
    }
    
    public function getDayOfWeek()
    {
        return DateTimeUtils::date(DateTimeFormat::DAY_OF_WEEK_NUMBER_ISO8601, $this->date) - 1;
    }
    
    public function isToday()
    {
        return $this->date >= DateTimeUtils::dayBegin();
    }
    
    public function getComingTime()
    {
        return $this->_comingTime;
    }
    
    public function getMustComingTime()
    {
        return $this->_mustComingTime;
    }
    
    public function getLeavingTime()
    {
        return $this->_leavingTime;
    }
    
    protected function setVar($var, $value)
    {
        switch ($var) {
            case 'comingTime':
            case 'leavingTime':
            case 'mustComingTime': {
                //$value = $this->_utils->getTimeFromDB( $value );
                preg_match("/^([0-9]{2}):([0-9]{2}):(?:[0-9]{2})$/", $value, $parts);
                $value = ($parts[1] * 60 + $parts[2]) * 60;
                if (!$value) {
                    $value = 0;
                    $this->{'_' . $var} = '...';
                } else {
                    $this->{'_' . $var} = $parts[1] . ':' . $parts[2];
                }
            } break;
        }
        
        return parent::setVar($var, $value);
    }
}
