<?php
/**
 * Пользователь
 * @author GreyMag
 *
 */
class User extends LPMBaseObject
{
    public static function loadList($where, $onlyNotLocked = false)
    {
        $whereArr = ['`%1$s`.`userId` = `%2$s`.`userId`'];

        if (!empty($where)) {
            $whereArr[] = $where;
        }

        if ($onlyNotLocked) {
            $whereArr[] = '`locked` = 0';
        }

        $whereStr = implode(' AND ', $whereArr);

        return StreamObject::loadListDefault(
            self::getDB(),
            $whereStr . ' ORDER BY `locked`',
            array( LPMTables::USERS, LPMTables::USERS_PREF ),
            __CLASS__
        );
    }
    
    /**
     * @param int $userId
     * @return User
     */
    public static function load($userId)
    {
        //return StreamObject::loadListDefault( $where, LPMTables::USERS, __CLASS__ );
        return StreamObject::singleLoad($userId, __CLASS__, '', '%1$s`.`userId');
    }
    
    /**
     * @param String $email
     * @return User
     */
    public static function loadByEmail($email)
    {
        $db = self::getDB();
        $res = $db->queryb([
            'SELECT' => '*',
            'FROM'   => LPMTables::USERS,
            'WHERE'  => [
                'email' => $email,
            ],
            'LIMIT' => 1,
        ]);
        
        $list = StreamObject::parseListResult($res, __CLASS__);
        return empty($list) ? null : $list[0];
    }
    
    /**
     * @param int $gitlabId
     * @return User
     */
    public static function loadByGitlabId($gitlabId)
    {
        $db = self::getDB();
        $res = $db->queryb([
            'SELECT' => '*',
            'FROM'   => LPMTables::USERS,
            'WHERE'  => [
                'gitlabId' => $gitlabId,
            ],
            'LIMIT' => 1,
        ]);
        
        $list = StreamObject::parseListResult($res, __CLASS__);
        return empty($list) ? null : $list[0];
    }
    
    /**
     * Обновляет поле блокировки пользователя.
     * @param int $userId
     * @param bool $isLocked
     */
    public static function updateLocked($userId, $isLocked)
    {
        return self::updateField($userId, 'locked', $isLocked);
    }
    
    /**
     * Обновляет поле с именем в Slack для пользователя.
     * @param int $userId
     * @param string $slackName
     */
    public static function updateSlackName($userId, $slackName)
    {
        return self::updateField($userId, 'slackName', $slackName);
    }
    
    /**
     * Обновляет поле с токеном GitLab для пользователя.
     * @param int $userId
     * @param string $gitlabToken
     * @param int $gitlabId
     */
    public static function updateGitlabToken($userId, $gitlabToken, $gitlabId)
    {
        return self::updateFields($userId, compact('gitlabToken', 'gitlabId'));
    }
    
    /**
     * Обновляет указанное поле пользователя.
     * @param int $userId
     * @param bool $isLocked
     */
    private static function updateField($userId, $fieldName, $value)
    {
        return self::updateFields($userId, [$fieldName => $value]);
    }
    
    /**
     * Обновляет поля пользователя.
     * @param int $userId
     * @param bool $isLocked
     */
    private static function updateFields($userId, $keyValues)
    {
        $db = self::getDB();
        return $db->queryb([
            'UPDATE' => LPMTables::USERS,
            'SET' => $keyValues,
            'WHERE' => ['userId' => $userId]
        ]);
    }
    
    public static function checkCurRole($curRole, $reqRole)
    {
        if ($reqRole == self::ROLE_USER) {
            return true;
        }
        if ($curRole == self::ROLE_USER) {
            return false;
        }
        
        return $curRole <= $reqRole;
    }

    public static function blowfishSalt($cost = 13)
    {
        if (!is_numeric($cost) || $cost < 4 || $cost > 31) {
            throw new Exception("cost parameter must be between 4 and 31");
        }
        $rand = array();
        for ($i = 0; $i < 8; $i += 1) {
            $rand[] = pack('S', mt_rand(0, 0xffff));
        }
        $rand[] = substr(microtime(), 2, 6);
        $rand = sha1(implode('', $rand), true);
        $salt = '$2a$' . sprintf('%02d', $cost) . '$';
        $salt .= strtr(substr(base64_encode($rand), 0, 22), array('+' => '.'));
        return $salt;
    }
    
    public static function passwordHash($value, $salt = null)
    {
        //return password_hash($value);
        if (null === $salt) {
            $salt = self::blowfishSalt();
        }
        return crypt($value, $salt);
    }

    public static function passwordVerify($value, $hash)
    {
        //return password_verify($value, $hash);
        return crypt($value, $hash) == $hash;
    }
    
    const ROLE_USER      = 0;
    const ROLE_ADMIN     = 1;
    const ROLE_MODERATOR = 2;
        
    public $userId;
    public $email     = '';
    public $nick      = '';
    public $firstName = '';
    public $lastName  = '';
    public $slackName = '';
    public $gitlabToken = '';
    public $gitlabId;
    public $lastVisit = 0;
    public $regDate   = 0;
    public $role      = 0;
    public $secret    = false;
    public $avatarUrl = '';
    public $locked = false;
    
    public $pref;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->pref = new UserPref();
        
        $this->_typeConverter->addIntVars('userId', 'gitlabId');
        $this->_typeConverter->addBoolVars('secret', 'locked');
        $this->addDateTimeFields('lastVisit', 'regDate');
        
        $this->addClientFields('userId', 'firstName', 'lastName', 'nick', 'avatarUrl');
        
        // TODO обновлять последний вход
    }
    
    public function getID()
    {
        return $this->userId;
    }

    public function getEmail()
    {
        return '***';
        // FIXME продумать систему закрытых email'ов
        return $this->secret ? '***' : $this->email;
    }
    
    public function getName()
    {
        return $this->firstName . ' ' .
               ($this->nick != '' ? $this->nick . ' ' : '') .
               $this->lastName;
    }
    
    public function getShortName()
    {
        if (empty($this->nick)) {
            return $this->lastName . ' ' . mb_substr($this->firstName, 0, 1);
        } else {
            return $this->nick;
        }
    }
    
    public function getAvatarUrl()
    {
        return $this->avatarUrl;
    }
    
    public function getLinkedName()
    {
        return $this->getName();
    }
    
    public function getLastVisit()
    {
        return self::getDateStr($this->lastVisit);
    }
    
    public function getRegDate()
    {
        return self::getDateStr($this->regDate);
    }
    
    public function canCreateProject()
    {
        return $this->isModerator();
    }
    
    public function isAdmin()
    {
        return $this->role == self::ROLE_ADMIN;
    }
    
    public function isModerator()
    {
        return $this->isAdmin() || $this->role == self::ROLE_MODERATOR;
    }
    
    public function isLocked()
    {
        return $this->locked == true;
    }
    
    public function checkRole($reqRole)
    {
        return self::checkCurRole($this->role, $reqRole);
    }
    
    protected function onLoadStream($hash)
    {
        $this->pref->loadStream($hash);
        
        if (empty($this->avatarUrl)) {
            $this->avatarUrl = $this->getMyGravatar();
        }
        
        parent::onLoadStream($hash);
    }
    
    protected function clientObjectCreated($obj)
    {
        $obj = parent::clientObjectCreated($obj);
        
        $obj->linkedName = $this->getLinkedName();
        return $obj;
    }
    
    private function getMyGravatar()
    {
        return $this->getGravatar($this->email);
    }
    
    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 512 ]
     * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source http://gravatar.com/site/implement/images/php/
     */
    private function getGravatar(
        $email,
        $s = 80,
        $d = 'mm',
        $r = 'g',
        $img = false,
        $atts = array()
    ) {
        $url = 'http://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";
        
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val) {
                $url .= ' ' . $key . '="' . $val . '"';
            }
            $url .= ' />';
        }
        return $url;
    }
}
