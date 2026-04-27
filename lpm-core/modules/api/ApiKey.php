<?php

class ApiKey extends LPMBaseObject
{
    const TOKEN_PREFIX = 'lpm_u';
    const HEADER_API_KEY = 'HTTP_X_LPM_API_KEY';
    const HEADER_AUTHORIZATION = 'HTTP_AUTHORIZATION';
    const QUERY_ARG_API_KEY = 'apiKey';

    public static function loadListByUserId($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return [];
        }

        return self::loadAndParseV2([
            'SELECT' => '*',
            'FROM' => LPMTables::API_KEYS,
            'WHERE' => [
                'userId' => $userId,
                'deleted' => 0,
            ],
            'ORDER BY' => '`id` DESC',
        ], __CLASS__);
    }

    public static function createForUser(User $user, $name = '')
    {
        $userId = (int)$user->getID();
        if ($userId <= 0) {
            throw new Exception('Не удалось определить пользователя');
        }

        $name = trim((string)$name);
        $token = self::buildToken($userId);

        self::buildAndSaveToDbV2([
            'INSERT' => [
                'userId' => $userId,
                'name' => $name,
                'keyHash' => User::passwordHash($token),
                'keyPreview' => self::buildPreview($token),
                'created' => DateTimeUtils::mysqlDate(),
            ],
            'INTO' => LPMTables::API_KEYS,
        ]);

        $key = self::loadAndParseSingleV2([
            'SELECT' => '*',
            'FROM' => LPMTables::API_KEYS,
            'WHERE' => [
                '`id` = ' . (int)self::getDB()->insert_id,
            ],
            'LIMIT' => 1,
        ], __CLASS__);

        return [
            'token' => $token,
            'key' => [
                'id' => $key->id,
                'name' => $key->name,
                'preview' => $key->keyPreview,
                'created' => $key->getCreatedLabel(),
            ],
        ];
    }

    public static function revokeForUser($keyId, $userId)
    {
        $keyId = (int)$keyId;
        $userId = (int)$userId;
        if ($keyId <= 0 || $userId <= 0) {
            throw new Exception('Некорректный API ключ');
        }

        $res = self::buildAndExecute([
            'UPDATE' => LPMTables::API_KEYS,
            'SET' => [
                'deleted' => 1,
            ],
            'WHERE' => [
                'id' => $keyId,
                'userId' => $userId,
                'deleted' => 0,
            ],
        ]);

        if (!$res || self::getDB()->affected_rows === 0) {
            throw new Exception('Не удалось отозвать API ключ');
        }
    }

    /**
     * @return User|null
     */
    public static function hasAuthDataInRequest()
    {
        return self::extractTokenFromRequest() !== null;
    }

    public static function authenticateUserFromRequest()
    {
        $token = self::extractTokenFromRequest();
        if (empty($token)) {
            return null;
        }

        $userId = self::extractUserId($token);
        if ($userId <= 0) {
            return null;
        }

        $user = User::load($userId);
        if (!$user || $user->locked) {
            return null;
        }

        $keys = self::loadListByUserId($userId);
        foreach ($keys as $key) {
            if (User::passwordVerify($token, $key->keyHash)) {
                return $user;
            }
        }

        return null;
    }

    public static function extractTokenFromRequest()
    {
        if (!empty($_SERVER[self::HEADER_API_KEY])) {
            return trim((string)$_SERVER[self::HEADER_API_KEY]);
        }

        if (!empty($_SERVER[self::HEADER_AUTHORIZATION])) {
            $value = trim((string)$_SERVER[self::HEADER_AUTHORIZATION]);
            if (stripos($value, 'Bearer ') === 0) {
                return trim(substr($value, 7));
            }
        }

        $query = self::getQueryArgs();
        if (!empty($query[self::QUERY_ARG_API_KEY])) {
            return trim((string)$query[self::QUERY_ARG_API_KEY]);
        }

        return null;
    }

    public static function getQueryArgs()
    {
        $query = [];
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $query);
        }

        return $query;
    }

    public $id = 0;
    public $userId = 0;
    public $name = '';
    public $keyHash = '';
    public $keyPreview = '';
    public $created = 0;
    public $deleted = false;

    public function __construct()
    {
        parent::__construct();

        $this->_typeConverter->addFloatVars('id', 'userId');
        $this->_typeConverter->addBoolVars('deleted');
        $this->addDateTimeFields('created');
    }

    public function getCreatedLabel()
    {
        return DateTimeUtils::date(
            DateTimeFormat::DAY_OF_MONTH_2 . '.' .
            DateTimeFormat::MONTH_NUMBER_2_DIGITS . '.' .
            DateTimeFormat::YEAR_NUMBER_4_DIGITS . ' ' .
            DateTimeFormat::HOUR_24_NUMBER_2_DIGITS . ':' .
            DateTimeFormat::MINUTES_OF_HOUR_2_DIGITS,
            $this->created
        );
    }

    private static function buildToken($userId)
    {
        return self::TOKEN_PREFIX . $userId . '_' . BaseString::randomStr(48);
    }

    private static function buildPreview($token)
    {
        return mb_substr($token, 0, 10) . '...' . mb_substr($token, -4);
    }

    private static function extractUserId($token)
    {
        if (!preg_match('/^' . preg_quote(self::TOKEN_PREFIX, '/') . '([0-9]+)_[A-Za-z0-9]+$/', $token, $matches)) {
            return 0;
        }

        return (int)$matches[1];
    }
}
