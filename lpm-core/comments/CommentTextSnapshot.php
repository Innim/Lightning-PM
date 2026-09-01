<?php
/**
 * Слепок текста комментария.
 *
 * Каждая запись — текст комментария на момент, когда он стал текущим:
 * первый слепок повторяет исходный текст, дальше по одному на каждую правку.
 * Благодаря этому текст, затёртый правкой, остаётся в истории.
 *
 * Слепки пишутся только для комментариев, которые вообще можно править
 * ({@see Comment::checkEditPermit()}): у комментария без единой правки
 * истории нет.
 */
class CommentTextSnapshot extends LPMBaseObject
{
    /**
     * Фиксирует текст комментария новым слепком.
     *
     * Вызывается сразу после записи текста в БД — тогда слепок повторяет то,
     * что реально сохранено. Сбой записи истории намеренно не прерывает
     * сохранение комментария: ошибка только пишется в лог, чтобы пользователь
     * не потерял правку из-за проблем с историей.
     *
     * @param int $commentId Идентификатор комментария.
     * @param string $text Текст комментария.
     * @param int $editorId Пользователь, сохранивший этот текст.
     * @param float $date Момент, когда текст стал текущим; 0 — сейчас.
     * @return bool Записан ли слепок.
     */
    public static function record($commentId, $text, $editorId, $date = 0)
    {
        try {
            self::add($commentId, $text, $editorId, $date);
            return true;
        } catch (\Throwable $e) {
            LPMLog::exception($e, LPMLog::CH_APP, ['commentId' => (int)$commentId]);
            return false;
        }
    }

    /**
     * Фиксирует исходный текст комментария базовым слепком,
     * если история комментария ещё пуста.
     *
     * Вызывается до записи правки: иначе первая же правка затёрла бы текст,
     * от которого не осталось бы ни одного слепка. Автором базового слепка
     * считается автор комментария — исходный текст написал он.
     *
     * Как и {@see record()}, не бросает исключений.
     *
     * @param Comment $comment Комментарий с ещё не изменённым текстом.
     * @return bool Записан ли базовый слепок.
     */
    public static function recordBaseline(Comment $comment)
    {
        try {
            if (self::hasSnapshots($comment->id)) {
                return false;
            }

            self::add($comment->id, $comment->text, $comment->authorId, $comment->date);
            return true;
        } catch (\Throwable $e) {
            LPMLog::exception($e, LPMLog::CH_APP, ['commentId' => (int)$comment->id]);
            return false;
        }
    }

    /**
     * Загружает слепки текста комментария, начиная с самого свежего.
     *
     * @param int $commentId Идентификатор комментария.
     * @param int $limit Максимальное количество слепков; null — все.
     * @return array<CommentTextSnapshot>
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function loadListByComment($commentId, $limit = null)
    {
        $hash = [
            'SELECT' => '*',
            'FROM' => LPMTables::COMMENT_TEXT_SNAPSHOTS,
            'WHERE' => [
                'commentId' => (int)$commentId,
            ],
            'ORDER BY' => '`id` DESC',
        ];

        if (!empty($limit)) {
            $hash['LIMIT'] = (int)$limit;
        }

        return self::loadAndParseV2($hash, __CLASS__);
    }

    /**
     * Проверяет, есть ли у комментария хотя бы один сохранённый слепок.
     *
     * @param int $commentId Идентификатор комментария.
     * @return bool
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function hasSnapshots($commentId)
    {
        $res = self::loadFromDV2([
            'SELECT' => '1',
            'FROM' => LPMTables::COMMENT_TEXT_SNAPSHOTS,
            'WHERE' => [
                'commentId' => (int)$commentId,
            ],
            'LIMIT' => 1,
        ]);

        return $res->num_rows > 0;
    }

    /**
     * Записывает текст комментария новым слепком.
     *
     * @param int $commentId Идентификатор комментария.
     * @param string $text Текст комментария.
     * @param int $editorId Пользователь, сохранивший этот текст.
     * @param float $date Момент, когда текст стал текущим; 0 — сейчас.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    private static function add($commentId, $text, $editorId, $date = 0)
    {
        self::buildAndSaveToDbV2([
            'INSERT' => [
                'commentId' => (int)$commentId,
                'text' => (string)$text,
                'editorId' => (int)$editorId,
                'createdAt' => empty($date)
                    ? DateTimeUtils::mysqlDate()
                    : DateTimeUtils::mysqlDate($date),
            ],
            'INTO' => LPMTables::COMMENT_TEXT_SNAPSHOTS,
        ]);
    }

    /**
     * Идентификатор слепка.
     * @var int
     */
    public $id = 0;

    /**
     * Идентификатор комментария.
     * @var int
     */
    public $commentId = 0;

    /**
     * Текст комментария в этом слепке.
     * @var string
     */
    public $text = '';

    /**
     * Пользователь, сохранивший этот текст; 0 — установить его не удалось.
     * @var int
     */
    public $editorId = 0;

    /**
     * Дата фиксации слепка.
     * @var float
     */
    public $createdAt = 0;

    public function __construct($raw = null)
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('id', 'commentId', 'editorId');
        $this->addDateTimeFields('createdAt');

        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }
}
