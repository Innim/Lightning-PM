<?php
/**
 * Пользователь
 * @author GreyMag
 *
 */
class User extends LPMBaseObject
{
    /**
     * Кэш загруженных по id пользователей в рамках запроса (userId => User).
     * Сбрасывается для пользователя при изменении его полей (см. {@see updateFields()}).
     * @var User[]
     */
    private static $_usersById = [];

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
     * @param bool $forceReload Загрузить из БД, игнорируя кэш.
     * @return User
     */
    public static function load($userId, $forceReload = false)
    {
        $key = (int) $userId;
        if (!$forceReload && isset(self::$_usersById[$key])) {
            return self::$_usersById[$key];
        }

        $user = StreamObject::singleLoad($userId, __CLASS__, '', '%1$s`.`userId');
        if ($user !== false) {
            self::$_usersById[$key] = $user;
        }
        return $user;
    }
    
    /**
     * @param  String $email
     * @return User|null Пользователь или null, если такого email нет.
     * @throws \GMFramework\ProviderLoadException При ошибке чтения из базы.
     */
    public static function loadByEmail($email)
    {
        return self::loadAndParseSingleV2([
            'SELECT' => '*',
            'FROM'   => LPMTables::USERS,
            'WHERE'  => [
                'email' => $email,
            ],
            'LIMIT' => 1,
        ], __CLASS__);
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
     * Возвращает хэш пароля пользователя.
     *
     * Пароль намеренно не входит в загружаемые поля пользователя,
     * поэтому для проверки он читается отдельно.
     * @param  int $userId Идентификатор пользователя.
     * @return string|null Хэш или null, если пользователя нет.
     * @throws \GMFramework\ProviderLoadException При ошибке чтения из базы.
     */
    public static function loadPasswordHash($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        $res = self::loadFromDV2([
            'SELECT' => '`pass`',
            'FROM'   => LPMTables::USERS,
            'WHERE'  => ['userId' => $userId],
            'LIMIT'  => 1,
        ]);

        $row = $res->fetch_assoc();

        return $row ? $row['pass'] : null;
    }

    /**
     * Задаёт пользователю новый пароль.
     * @param int    $userId       Идентификатор пользователя.
     * @param string $passwordHash Хэш нового пароля.
     * @throws \GMFramework\ProviderSaveException При ошибке записи в базу.
     */
    public static function updatePassword($userId, $passwordHash)
    {
        self::buildAndSaveToDbV2([
            'UPDATE' => LPMTables::USERS,
            'SET'    => ['pass' => $passwordHash],
            'WHERE'  => ['userId' => (int)$userId],
        ]);
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
     * Обновляет роль пользователя.
     * @param int $userId
     * @param int $role Одна из констант User::ROLE_*.
     */
    public static function updateRole($userId, $role)
    {
        return self::updateField($userId, 'role', $role);
    }

    /**
     * Возвращает список доступных ролей в виде «роль => читаемое название».
     * @return array
     */
    public static function getRolesMap()
    {
        return [
            self::ROLE_USER      => 'Пользователь',
            self::ROLE_MODERATOR => 'Модератор',
            self::ROLE_ADMIN     => 'Администратор',
        ];
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
        // Сбрасываем кэш — загруженный объект пользователя мог устареть.
        unset(self::$_usersById[(int) $userId]);

        $db = self::getDB();
        return $db->queryb([
            'UPDATE' => LPMTables::USERS,
            'SET' => $keyValues,
            'WHERE' => ['userId' => $userId]
        ]);
    }

    /**
     * Обновляет время последнего визита пользователя текущим моментом.
     * @param int $userId
     */
    public static function updateLastVisit($userId)
    {
        return self::updateField($userId, 'lastVisit', DateTimeUtils::mysqlDate());
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
        $url = $this->getUrl();
        return '<a href="' . $url . '">'.$this->getName() . '</a>';
    }

    public function getUrl()
    {
        return UserPage::getUrlFor($this->userId);
    }
    
    public function getLastVisit()
    {
        return self::getDateStr($this->lastVisit);
    }

    /**
     * Возвращает полную дату и время последнего визита (с часами и минутами).
     * @return string Пустая строка, если визитов ещё не было.
     */
    public function getLastVisitFull()
    {
        return self::getDateTimeStr($this->lastVisit);
    }

    /**
     * Возвращает относительное время последнего визита («N единиц назад»).
     * @return string Пустая строка, если визитов ещё не было.
     */
    public function getLastVisitAgo()
    {
        return TimeAgoHelper::format($this->lastVisit);
    }
    
    public function getRegDate()
    {
        return self::getDateStr($this->regDate);
    }

    /**
     * Возвращает читаемое название роли пользователя.
     * @return string
     */
    public function getRoleName()
    {
        $map = self::getRolesMap();
        return isset($map[$this->role]) ? $map[$this->role] : $map[self::ROLE_USER];
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
            $this->avatarUrl = $this->getMySlackAvatar();

            if (empty($this->avatarUrl)) {
                $this->avatarUrl = $this->getMyGravatar();
            }
        }
        
        parent::onLoadStream($hash);
    }
    
    protected function clientObjectCreated($obj)
    {
        $obj = parent::clientObjectCreated($obj);
        
        $obj->linkedName = $this->getLinkedName();
        $obj->url = $this->getUrl();
        return $obj;
    }
    
    private function getMyGravatar()
    {
        return $this->getGravatar($this->email);
    }
    
    private function getMySlackAvatar()
    {
        if (empty($this->slackName)) return '';

        $engine = LightningEngine::getInstance();
        $cache = $engine->cache();

        // Функционал аватаров из Slack включаем только если кэш включен
        // потому что иначе заспамим Slack API, да и долго будет все.
        // Если нужны будут аватарки и с выключенным кэшем - то нужно
        // сделать хранение их в базе
        if (!$cache->isEnabled()) return '';

        $cachedValue = $cache->getUserSlackAvatarUrl($this->userId);
        if ($cachedValue !== false) return $cachedValue;

        $slack = SlackIntegration::getInstance();
        try {
            $profile = $slack->getProfile($this->slackName);
            $url = $profile ? $profile->getImage192() : '';
        } catch (Exception $e) {
            $url = '';
        }

        $cache->setUserSlackAvatarUrl($this->userId, $url);
        return $url;
    }
    
    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 512 ]
     * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $attrs Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    private function getGravatar(
        $email,
        $s = 80,
        $d = 'mm',
        $r = 'g',
        $img = false,
        $attrs = array()
    ) {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";
        
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($attrs as $key => $val) {
                $url .= ' ' . $key . '="' . $val . '"';
            }
            $url .= ' />';
        }
        return $url;
    }
}
