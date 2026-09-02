<?php
/**
 * Посещение проекта пользователем.
 *
 * На пару «пользователь — проект» приходится одна запись, хранящая
 * время последнего захода. По ней строится список недавних проектов.
 */
class ProjectVisit extends LPMBaseObject
{
    /**
     * Отмечает, что пользователь открыл страницу проекта.
     * Предыдущая отметка по этому проекту перезаписывается.
     * @param  int $userId    Идентификатор пользователя.
     * @param  int $projectId Идентификатор проекта.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить.
     */
    public static function registerVisit($userId, $projectId)
    {
        self::buildAndSaveToDbV2([
            'INSERT' => [
                'userId'    => (int)$userId,
                'projectId' => (int)$projectId,
                'visitDate' => self::now(),
            ],
            'INTO'   => LPMTables::PROJECT_VISITS,
            'ODKU'   => ['visitDate'],
        ]);
    }

    /**
     * Текущее время в формате mysql datetime с миллисекундами.
     *
     * Точности до секунды не хватает: два проекта, открытых в одну и ту же
     * секунду, упорядочиваются произвольно, и список недавних перемешивается.
     * @return string
     */
    private static function now()
    {
        $time = microtime(true);
        return date('Y-m-d H:i:s', (int)$time) . sprintf('.%03d', ($time - (int)$time) * 1000);
    }

    /**
     * Загружает проекты, которые пользователь открывал последними.
     *
     * Проекты, недоступные пользователю сейчас, и заархивированные
     * в список не попадают, даже если отметка о посещении осталась.
     * @param  User $user  Пользователь.
     * @param  int  $limit Максимальное количество проектов.
     * @return array<Project> Проекты от самого недавно открытого к более давним.
     * @throws \GMFramework\ProviderLoadException При ошибке выборки.
     */
    public static function loadRecentProjects(User $user, $limit)
    {
        $userId = (int)$user->userId;
        $hash = [
            'SELECT' => '`p`.*',
            'FROM'   => LPMTables::PROJECT_VISITS,
            'AS'     => 'v',
            'JOINS'  => [
                [
                    'INNER JOIN' => LPMTables::PROJECTS,
                    'AS'         => 'p',
                    'ON'         => ['`p`.`id`' => self::col('v.projectId')],
                ],
            ],
            'WHERE'  => [
                '`v`.`userId`'    => $userId,
                '`p`.`isArchive`' => 0,
            ],
            'ORDER BY' => '`v`.`visitDate` DESC',
            'LIMIT'    => max(1, (int)$limit),
        ];

        // Модератору доступны все проекты, остальным - только те, где он участник
        if (!$user->isModerator()) {
            $hash['JOINS'][] = [
                'INNER JOIN' => LPMTables::MEMBERS,
                'AS'         => 'm',
                'ON'         => [
                    '`m`.`instanceId`'   => self::col('p.id'),
                    '`m`.`instanceType`' => LPMInstanceTypes::PROJECT,
                    '`m`.`userId`'       => $userId,
                ],
            ];
        }

        return self::loadAndParseV2($hash, 'Project');
    }
}
