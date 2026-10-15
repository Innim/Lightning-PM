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
     * @param IssueStateSnapshot $oldState Снимок состояния задачи, снятый
     *        до сохранения изменений.
     * @param Issue $new Состояние задачи после редактирования.
     * @return IssueChangeSet
     */
    public static function build(Issue $old, IssueStateSnapshot $oldState, Issue $new)
    {
        $newState = IssueStateSnapshot::take($new);
        $lines = [];

        self::addMembersLine($lines, 'Исполнители', $oldState->getMemberIds(), $newState->getMemberIds());
        self::addMembersLine($lines, 'Тестировщики', $oldState->getTesterIds(), $newState->getTesterIds());
        self::addMembersLine($lines, 'Мастеры', $oldState->getMasterIds(), $newState->getMasterIds());

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

        self::addMembersSpLine($lines, $oldState->getMembersSp(), $newState->getMembersSp());

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

        self::addAttachmentsLine(
            $lines,
            'Изображения',
            $oldState->getImageNames(),
            $newState->getImageNames(),
            [
                'added' => 'добавлено',
                'removed' => 'удалено',
                'unnamed' => 'изображение без имени',
            ]
        );
        self::addAttachmentsLine(
            $lines,
            'Файлы',
            $oldState->getFileNames(),
            $newState->getFileNames(),
            [
                'added' => 'добавлен',
                'removed' => 'удалён',
                'unnamed' => 'файл без имени',
            ]
        );

        if ($oldState->isOnBoard() !== $newState->isOnBoard()) {
            $lines[] = $newState->isOnBoard()
                ? 'Задача поставлена на скрам-доску'
                : 'Задача снята со скрам-доски';
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

    /**
     * Добавляет строку об изменении оценок исполнителей.
     *
     * Учитываются только те, кто остался в исполнителях: приход и уход
     * участника уже назван отдельной строкой, а оценка ушедшего не остаётся
     * у задачи.
     * @param string[] $lines Собираемый набор изменений.
     * @param array<int, float> $oldSp Оценки до редактирования.
     * @param array<int, float> $newSp Оценки после редактирования.
     */
    private static function addMembersSpLine(array &$lines, array $oldSp, array $newSp)
    {
        $parts = [];
        foreach ($newSp as $userId => $sp) {
            if (!isset($oldSp[$userId]) || (float)$oldSp[$userId] === (float)$sp) {
                continue;
            }

            $parts[] = self::userName($userId) . ' ' .
                self::storyPoints($oldSp[$userId]) . ' → ' . self::storyPoints($sp);
        }

        if (empty($parts)) {
            return;
        }

        $lines[] = 'SP исполнителей: ' . implode('; ', $parts);
    }

    /**
     * Оценка в SP в том же виде, в каком её показывает задача
     * ({@see Issue::getStrHours()}).
     * @param  float $value
     * @return string
     */
    private static function storyPoints($value)
    {
        $value = (float)$value;

        return $value == .5 ? '1/2' : (string)$value;
    }

    /**
     * Добавляет строку об изменении состава вложений одного вида.
     *
     * Вложение опознаётся по идентификатору, а не по имени: два файла могут
     * быть загружены под одним именем, и замена файла на одноимённый —
     * это тоже изменение.
     * @param string[] $lines Собираемый набор изменений.
     * @param string $label Название вида вложений.
     * @param array<int, string> $oldNames Имена вложений до редактирования.
     * @param array<int, string> $newNames Имена вложений после редактирования.
     * @param array $words Слова этого вида вложений: `added` и `removed` —
     *        как сказать о появившемся и об убранном, `unnamed` — чем
     *        назвать вложение без имени. Все три должны быть
     *        согласованы в роде.
     */
    private static function addAttachmentsLine(
        array &$lines,
        $label,
        array $oldNames,
        array $newNames,
        array $words
    ) {
        $added = array_diff_key($newNames, $oldNames);
        $removed = array_diff_key($oldNames, $newNames);
        if (empty($added) && empty($removed)) {
            return;
        }

        $parts = [];
        foreach ($added as $name) {
            $parts[] = $words['added'] . ' ' . self::attachmentName($name, $words['unnamed']);
        }
        foreach ($removed as $name) {
            $parts[] = $words['removed'] . ' ' . self::attachmentName($name, $words['unnamed']);
        }

        $lines[] = $label . ': ' . implode('; ', $parts);
    }

    /**
     * Как назвать вложение в наборе изменений.
     *
     * Называется имя, под которым вложение загрузили: имени файла
     * на диске пользователь никогда не видел, а идентификатор вложения
     * не виден в интерфейсе нигде — и форма «#123» прочиталась бы
     * как номер задачи.
     * @param  string $name    Имя, под которым вложение загрузили.
     * @param  string $unnamed Чем назвать вложение без имени.
     * @return string Имя в кавычках либо $unnamed, если имя неизвестно.
     */
    private static function attachmentName($name, $unnamed)
    {
        $name = (string)$name;

        return '' === $name ? $unnamed : '«' . $name . '»';
    }

    private static function userName($userId)
    {
        $user = User::load($userId);
        return $user ? $user->getPlainName() : ('#' . (int)$userId);
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
