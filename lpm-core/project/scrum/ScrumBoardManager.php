<?php
/**
 * Правила изменения положения задачи на скрам-доске.
 *
 * Единственное место, где собраны постановка задачи на доску, перевод стикера
 * между колонками и снятие с доски: через этот класс работают и доска
 * с карточкой задачи ({@see IssueService}), и форма задачи
 * ({@see ProjectPage::updateScrumBoard()}), и внешнее API
 * ({@see ApiIssueController}), чтобы все представления вели себя одинаково.
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
