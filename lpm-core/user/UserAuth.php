<?php
/**
 * Сохранённая авторизация пользователя - то, по чему он входит без пароля,
 * пока живёт кука. Одна запись на устройство.
 */
class UserAuth extends LPMBaseObject
{
    /**
     * Определяет, существует ли ещё запись авторизации.
     *
     * По ней проверяется, не отозвана ли авторизация: сессия в PHP живёт своей
     * жизнью и сама по себе о смене пароля не узнает.
     * @param  int $userId Идентификатор пользователя.
     * @param  int $id     Идентификатор записи авторизации.
     * @return bool
     * @throws \GMFramework\ProviderLoadException При ошибке чтения из базы.
     */
    public static function exists($userId, $id)
    {
        $userId = (int)$userId;
        $id = (int)$id;
        if ($userId <= 0 || $id <= 0) {
            return false;
        }

        $res = self::loadFromDV2([
            'SELECT' => '`id`',
            'FROM'   => LPMTables::USER_AUTH,
            'WHERE'  => [
                'id'     => $id,
                'userId' => $userId,
            ],
            'LIMIT'  => 1,
        ]);

        return (bool)$res->fetch_assoc();
    }

    /**
     * Удаляет сохранённые авторизации пользователя.
     * @param int $userId   Идентификатор пользователя.
     * @param int $exceptId Запись, которую надо оставить (текущая авторизация),
     *                      либо 0, чтобы удалить все.
     * @throws \GMFramework\ProviderSaveException При ошибке записи в базу.
     */
    public static function removeForUser($userId, $exceptId = 0)
    {
        $userId = (int)$userId;
        $exceptId = (int)$exceptId;

        $where = ['userId' => $userId];
        if ($exceptId > 0) {
            $where['id'] = ['<>' => $exceptId];
        }

        self::buildAndSaveToDbV2([
            'DELETE' => LPMTables::USER_AUTH,
            'WHERE'  => $where,
        ]);
    }
}
