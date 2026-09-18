<?php
/**
 * Правила изменения положения задачи на скрам-доске.
 *
 * Единственное место, где собраны постановка задачи на доску, перевод стикера
 * между колонками, снятие с доски и закрытие спринта: через этот класс работают
 * и доска с карточкой задачи ({@see IssueService}), и форма задачи
 * ({@see ProjectPage::updateScrumBoard()}), и внешнее API
 * ({@see ApiIssueController}, {@see ApiProjectController}), чтобы все
 * представления вели себя одинаково.
 *
 * Нарушение правил - {@see ScrumBoardException}, неудачное сохранение -
 * {@see \GMFramework\ProviderSaveException}.
 */
class ScrumBoardManager
{
    /**
     * Ставит задачу на доску в колонку, соответствующую её статусу.
     *
     * Статус задачи при этом не меняется, поскольку колонка выводится из него.
     *
     * @param  Issue $issue Задача.
     * @throws ScrumBoardException Если задачу нельзя поставить на доску.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить стикер.
     */
    public static function putOnBoard(Issue $issue)
    {
        self::requireScrumProject($issue);

        $state = ScrumSticker::getStateForIssue($issue);
        // Задача в работе попадает сразу на спринт, а туда без тегов нельзя,
        // если проект их требует
        self::requireLabelsForActiveState($issue, $state);

        if (!ScrumSticker::putStickerOnBoard($issue, $state)) {
            throw new \GMFramework\ProviderSaveException();
        }

        $issue->reloadSubstatusSources();
    }

    /**
     * Переводит стикер задачи в указанное состояние.
     *
     * Статус задачи синхронизируется с новой колонкой: «Тестируется» ставит
     * задачу на проверку, «Готово» - завершает её, а возврат в TO DO или
     * «В работе» переоткрывает задачу, ожидающую проверки.
     *
     * @param  Issue $issue Задача.
     * @param  int   $state Новое состояние стикера, см. {@see ScrumStickerState}.
     * @param  User|null $user Пользователь, от имени которого меняется статус задачи.
     * @param  bool  $allowPutOnBoard Разрешает поставить задачу на доску, если стикера
     *                                ещё нет; иначе отсутствие стикера - ошибка.
     * @throws ScrumBoardException Если состояние неизвестно, стикера нет
     *                             или задачу нельзя перевести в это состояние.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить стикер.
     */
    public static function changeState(Issue $issue, $state, $user, $allowPutOnBoard = false)
    {
        $state = (int)$state;
        if (!ScrumStickerState::validateValue($state)) {
            throw new ScrumBoardException('Неизвестное состояние');
        }

        self::requireScrumProject($issue);

        // Загрузчик отдаёт false, если стикера нет
        $sticker = ScrumSticker::load($issue->id);
        if (empty($sticker)) {
            if (!$allowPutOnBoard) {
                throw new ScrumBoardException('Нет стикера для этой задачи');
            }

            $currentState = ScrumStickerState::BACKLOG;
        } else {
            $currentState = $sticker->state;
        }

        // Если проект требует теги - задачу без них нельзя взять
        // из бэклога на спринт. Любое неактивное состояние - это «не на доске»,
        // а значит выход из него равнозначен выходу из бэклога
        if (!ScrumStickerState::isActiveState($currentState)) {
            self::requireLabelsForActiveState($issue, $state);
        }

        // Менять состояние стикера может любой пользователь
        $saved = empty($sticker)
            ? ScrumSticker::putStickerOnBoard($issue, $state)
            : ScrumSticker::updateStickerState($issue->id, $state);

        if (!$saved) {
            throw new \GMFramework\ProviderSaveException();
        }

        self::syncIssueStatus($issue, $state, $user);
    }

    /**
     * Снимает задачу с доски - она возвращается в бэклог.
     *
     * Задача, которой на доске и не было, просто остаётся в бэклоге.
     * Статус задачи не меняется.
     *
     * @param  Issue $issue Задача.
     * @param  User|null $user Пользователь, выполняющий действие.
     * @throws ScrumBoardException Если у проекта нет скрам-доски.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить изменение.
     */
    public static function removeFromBoard(Issue $issue, $user)
    {
        self::changeState($issue, ScrumStickerState::BACKLOG, $user, true);
    }

    /**
     * Закрывает спринт: доска уходит в архив, а на её месте начинается новый спринт.
     *
     * Снимок доски попадает в архив спринтов вместе с целями спринта, после чего
     * стикеры снимаются с доски. С $transferOpened стикеры колонок TO DO
     * и «В работе» остаются на доске и начинают новый спринт - у них обновляется
     * дата добавления; в снимок закрытого спринта они всё равно попадают.
     *
     * Пустая доска - не ошибка: закрывать нечего, снимок не создаётся
     * и номер спринта не меняется.
     *
     * @param  Project $project        Проект со скрам-доской.
     * @param  bool    $transferOpened Переносить ли незавершённые задачи
     *                                 (TO DO и «В работе») в новый спринт.
     * @param  User    $user           Пользователь, закрывающий спринт.
     * @return array Результат: `closed` - был ли закрыт спринт, `sprintNumber` -
     *         номер закрытого спринта (null, если доска была пуста),
     *         `currentSprintNumber` - номер спринта, идущего теперь,
     *         `archived` - снятые с доски стикеры, `transferred` - стикеры,
     *         оставшиеся на доске.
     * @throws ScrumBoardException Если у проекта нет скрам-доски или доску
     *                             нельзя заархивировать.
     * @throws \GMFramework\ProviderSaveException Если не удалось снять стикеры.
     */
    public static function closeSprint(Project $project, $transferOpened, User $user)
    {
        if (!$project->scrum) {
            throw new ScrumBoardException('У проекта нет скрам-доски');
        }

        $transferOpened = (bool)$transferOpened;
        $transferStates = [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS];

        // Доска читается один раз: тот же состав и уходит в снимок,
        // иначе отчёт описывал бы не ту доску, которую заархивировали
        $stickers = ScrumSticker::loadBoard($project->id);

        $sprintNumber = null;
        $archived = [];
        $transferred = [];

        if (!empty($stickers)) {
            // Номер берём у самого снимка: предсказывать его отдельным
            // запросом - значит разойтись с тем, что записано в архив
            $sprintNumber = ScrumStickerSnapshot::createSnapshot($project->id, $user->getID(), $stickers);

            $notRemoveStates = $transferOpened ? $transferStates : null;
            if (!ScrumSticker::removeStickersForProject($project->id, $notRemoveStates)) {
                throw new \GMFramework\ProviderSaveException();
            }

            if ($transferOpened) {
                ScrumSticker::updateStickerAdded($project->id);
            }
        }

        if ($sprintNumber !== null) {
            foreach ($stickers as $sticker) {
                if ($transferOpened && in_array($sticker->state, $transferStates)) {
                    $transferred[] = $sticker;
                } else {
                    $archived[] = $sticker;
                }
            }
        }

        return [
            'closed' => $sprintNumber !== null,
            'sprintNumber' => $sprintNumber,
            'currentSprintNumber' => ScrumStickerSnapshot::getLastSnapshotId($project->id) + 1,
            'archived' => $archived,
            'transferred' => $transferred,
        ];
    }

    /**
     * Приводит статус задачи в соответствие с колонкой, в которую переехал стикер.
     * @param Issue $issue Задача.
     * @param int   $state Новое состояние стикера.
     * @param User|null $user Пользователь, от имени которого меняется статус.
     */
    private static function syncIssueStatus(Issue $issue, $state, $user)
    {
        $newStatus = null;
        if ($state === ScrumStickerState::TESTING) {
            // Если состояние "Тестируется" - ставим задачу на проверку
            $newStatus = Issue::STATUS_WAIT;
        } elseif ($state === ScrumStickerState::DONE) {
            // Если "Готово" - закрываем задачу
            $newStatus = Issue::STATUS_COMPLETED;
        } elseif ($issue->status == Issue::STATUS_WAIT &&
                ($state === ScrumStickerState::TODO || $state === ScrumStickerState::IN_PROGRESS)) {
            // Если она в режиме ожидания - переоткрываем задачу
            $newStatus = Issue::STATUS_IN_WORK;
        }

        if ($newStatus !== null) {
            // Стикер уже переставлен, второй раз его двигать не нужно
            Issue::setStatus($issue, $newStatus, $user, true, false);
        }
    }

    /**
     * Проверяет, что задача может оказаться на доске в указанном состоянии.
     * @param Issue $issue Задача.
     * @param int   $state Состояние стикера.
     * @throws ScrumBoardException Если проект требует теги, а у задачи их нет.
     */
    private static function requireLabelsForActiveState(Issue $issue, $state)
    {
        if (ScrumStickerState::isActiveState($state)
                && $issue->getProject()->requireLabels
                && !Issue::hasLabels($issue->getName())) {
            throw new ScrumBoardException(
                'Нельзя добавить на спринт задачу без тегов - ' .
                'у задачи должен быть указан хотя бы один тег'
            );
        }
    }

    /**
     * @param Issue $issue Задача.
     * @throws ScrumBoardException Если у проекта задачи нет скрам-доски.
     */
    private static function requireScrumProject(Issue $issue)
    {
        $project = $issue->getProject();
        if (empty($project) || !$project->scrum) {
            throw new ScrumBoardException('У проекта нет скрам-доски');
        }
    }
}
