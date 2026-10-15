<?php
/**
 * Снимок того в задаче, что хранится отдельно от неё самой: состав участников
 * с их оценками, вложения и наличие стикера на скрам-доске.
 *
 * Эти данные задача загружает лениво, а сохранение их перезаписывает, поэтому
 * по объекту задачи после сохранения состояние «до» уже не восстановить —
 * снимок нужно снять до того, как изменения попадут в базу.
 *
 * С историей содержимого задачи ({@see IssueContentSnapshot}) ничего общего
 * не имеет: этот снимок нигде не хранится и живёт только до сравнения.
 *
 * @see IssueChangeSet::build()
 */
class IssueStateSnapshot
{
    /**
     * Снимает состояние задачи — такое, какое оно в базе сейчас.
     *
     * Состав участников и вложения читаются через саму задачу, поэтому
     * у объекта, у которого их уже запрашивали, снимется закешированное в нём
     * состояние, а не содержимое базы. Стикер, наоборот, всегда читается
     * из базы: его состояние задача кеширует при загрузке, и постановка
     * на доску этот кеш не обновляет.
     * @param  Issue $issue Задача.
     * @return IssueStateSnapshot
     */
    public static function take(Issue $issue)
    {
        $members = $issue->getMembers();
        $sticker = ScrumSticker::load($issue->id);

        return new self(
            $issue->getMemberIds(),
            $issue->getTesterIds(),
            $issue->getMasterIds(),
            self::membersSp($members),
            !empty($sticker) && $sticker->isOnBoard(),
            self::imageNames($issue->getImages()),
            self::fileNames($issue->getFiles())
        );
    }

    /**
     * @param  Member[] $members Исполнители задачи.
     * @return array<int, float> Оценки исполнителей в SP
     *         по их идентификаторам.
     */
    private static function membersSp(array $members)
    {
        $sp = [];
        foreach ($members as $member) {
            $sp[(int)$member->getID()] = isset($member->sp) ? (float)$member->sp : 0.0;
        }

        return $sp;
    }

    /**
     * @param  LPMImg[] $images
     * @return array<int, string> Имена изображений по их идентификаторам.
     *         Пустая строка — имя неизвестно.
     */
    private static function imageNames(array $images)
    {
        $names = [];
        foreach ($images as $image) {
            $names[(int)$image->imgId] = $image->getDisplayName();
        }

        return $names;
    }

    /**
     * @param  LPMFile[] $files
     * @return array<int, string> Имена файлов по их идентификаторам.
     *         Пустая строка — имя неизвестно.
     */
    private static function fileNames(array $files)
    {
        $names = [];
        foreach ($files as $file) {
            $names[(int)$file->fileId] = FileNameHelper::normalizeDisplayName($file->origName);
        }

        return $names;
    }

    /** @var int[] */
    private $memberIds;

    /** @var int[] */
    private $testerIds;

    /** @var int[] */
    private $masterIds;

    /** @var array<int, float> */
    private $membersSp;

    /** @var bool */
    private $onBoard;

    /** @var array<int, string> */
    private $imageNames;

    /** @var array<int, string> */
    private $fileNames;

    private function __construct(
        array $memberIds,
        array $testerIds,
        array $masterIds,
        array $membersSp,
        $onBoard,
        array $imageNames,
        array $fileNames
    ) {
        $this->memberIds = array_map('intval', $memberIds);
        $this->testerIds = array_map('intval', $testerIds);
        $this->masterIds = array_map('intval', $masterIds);
        $this->membersSp = $membersSp;
        $this->onBoard = (bool)$onBoard;
        $this->imageNames = $imageNames;
        $this->fileNames = $fileNames;
    }

    /**
     * @return int[] Идентификаторы исполнителей.
     */
    public function getMemberIds()
    {
        return $this->memberIds;
    }

    /**
     * @return int[] Идентификаторы тестировщиков.
     */
    public function getTesterIds()
    {
        return $this->testerIds;
    }

    /**
     * @return int[] Идентификаторы мастеров.
     */
    public function getMasterIds()
    {
        return $this->masterIds;
    }

    /**
     * @return array<int, float> Оценки исполнителей в SP
     *         по их идентификаторам.
     */
    public function getMembersSp()
    {
        return $this->membersSp;
    }

    /**
     * @return bool Стоит ли задача на скрам-доске.
     */
    public function isOnBoard()
    {
        return $this->onBoard;
    }

    /**
     * @return array<int, string> Имена приложенных изображений
     *         по их идентификаторам.
     */
    public function getImageNames()
    {
        return $this->imageNames;
    }

    /**
     * @return array<int, string> Имена приложенных файлов
     *         по их идентификаторам.
     */
    public function getFileNames()
    {
        return $this->fileNames;
    }
}
