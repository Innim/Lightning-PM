<?php
/**
 * Набор изменений задачи между двумя её состояниями (до и после редактирования),
 * представленный в виде готового к выводу списка человекочитаемых строк.
 *
 * Используется для детализации оповещения об изменении задачи и записи в лог действий.
 */
class IssueChangeSet
{
    /**
     * Сравнивает состояние задачи до и после редактирования и собирает набор изменений.
     *
     * @param Issue $old Состояние задачи до редактирования.
     * @param int[] $oldMemberIds Идентификаторы исполнителей до редактирования.
     * @param int[] $oldTesterIds Идентификаторы тестировщиков до редактирования.
     * @param int[] $oldMasterIds Идентификаторы мастеров до редактирования.
     * @param Issue $new Состояние задачи после редактирования.
     * @return IssueChangeSet
     */
    public static function build(
        Issue $old,
        array $oldMemberIds,
        array $oldTesterIds,
        array $oldMasterIds,
        Issue $new
    ) {
        $lines = [];

        self::addMembersLine($lines, 'Исполнители', $oldMemberIds, $new->getMemberIds());
        self::addMembersLine($lines, 'Тестировщики', $oldTesterIds, $new->getTesterIds());
        self::addMembersLine($lines, 'Мастера', $oldMasterIds, $new->getMasterIds());

        if ($old->getType() !== $new->getType()) {
            $lines[] = 'Тип: ' . $old->getType() . ' → ' . $new->getType();
        }

        // Сравниваем по человекочитаемой градации, чтобы не сообщать
        // о правках приоритета в пределах одной группы (низкий/нормальный/высокий).
        if ($old->getPriorityStr() !== $new->getPriorityStr()) {
            $lines[] = 'Приоритет: ' . $old->getPriorityStr() . ' → ' . $new->getPriorityStr();
        }

        if ((float)$old->hours !== (float)$new->hours) {
            $lines[] = 'Оценка (SP): ' . $old->getStrHours() . ' → ' . $new->getStrHours();
        }

        if ((float)$old->completeDate !== (float)$new->completeDate) {
            $oldDate = $old->hasCompleteDate() ? $old->getCompleteDate() : 'не задан';
            $newDate = $new->hasCompleteDate() ? $new->getCompleteDate() : 'не задан';
            $lines[] = 'Срок: ' . $oldDate . ' → ' . $newDate;
        }

        if ($old->name !== $new->name) {
            $lines[] = 'Название: «' . $old->name . '» → «' . $new->name . '»';
        }

        if ($old->desc !== $new->desc) {
            $lines[] = 'Описание изменено';
        }

        return new self($lines);
    }

    private static function addMembersLine(array &$lines, $label, array $oldIds, array $newIds)
    {
        $oldIds = array_map('intval', $oldIds);
        $newIds = array_map('intval', $newIds);

        $added = array_diff($newIds, $oldIds);
        $removed = array_diff($oldIds, $newIds);
        if (empty($added) && empty($removed)) {
            return;
        }

        $parts = [];
        foreach ($added as $id) {
            $parts[] = 'добавлен ' . self::userName($id);
        }
        foreach ($removed as $id) {
            $parts[] = 'снят ' . self::userName($id);
        }

        $lines[] = $label . ': ' . implode('; ', $parts);
    }

    private static function userName($userId)
    {
        $user = User::load($userId);
        return $user ? $user->getName() : ('#' . (int)$userId);
    }

    /** @var string[] */
    private $lines;

    private function __construct(array $lines)
    {
        $this->lines = $lines;
    }

    /**
     * @return bool Есть ли зафиксированные изменения.
     */
    public function isEmpty()
    {
        return empty($this->lines);
    }

    /**
     * @return string Список изменений, по одной строке на изменение, с маркерами.
     */
    public function asText()
    {
        return '• ' . implode("\n• ", $this->lines);
    }
}
