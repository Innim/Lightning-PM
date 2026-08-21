<?php
/**
 * Ключ восстановления пароля - одноразовое значение из ссылки,
 * которую пользователь получает письмом. Одна актуальная запись
 * на пользователя, со сроком действия.
 */
class PassRecoveryKey extends LPMBaseObject
{
    /**
     * Возвращает действующий ключ восстановления пользователя.
     * @param  int $userId Идентификатор пользователя.
     * @return string|null Ключ или null, если действующего ключа нет.
     * @throws \GMFramework\ProviderLoadException При ошибке чтения из базы.
     */
    public static function loadActualKey($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        $res = self::loadFromDV2([
            'SELECT' => '`recoveryKey`',
            'FROM'   => LPMTables::RECOVERY_EMAILS,
            'WHERE'  => [
                'userId'  => $userId,
                'expDate' => ['>=' => DateTimeUtils::mysqlDate()],
            ],
            'LIMIT'  => 1,
        ]);

        $row = $res->fetch_assoc();

        return $row ? $row['recoveryKey'] : null;
    }

    /**
     * Сохраняет ключ восстановления, заменяя прежний ключ пользователя.
     * @param int    $userId  Идентификатор пользователя.
     * @param string $key     Ключ восстановления.
     * @param string $expDate Срок действия в формате даты MySQL.
     * @throws \GMFramework\ProviderSaveException При ошибке записи в базу.
     */
    public static function save($userId, $key, $expDate)
    {
        self::buildAndSaveToDbV2([
            'REPLACE' => [
                'userId'      => (int)$userId,
                'recoveryKey' => $key,
                'expDate'     => $expDate,
            ],
            'INTO'    => LPMTables::RECOVERY_EMAILS,
        ]);
    }

    /**
     * Удаляет использованный ключ восстановления.
     * @param string $key Ключ восстановления.
     * @throws \GMFramework\ProviderSaveException При ошибке записи в базу.
     */
    public static function removeByKey($key)
    {
        self::buildAndSaveToDbV2([
            'DELETE' => LPMTables::RECOVERY_EMAILS,
            'WHERE'  => ['recoveryKey' => (string)$key],
        ]);
    }
}
