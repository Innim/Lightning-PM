<?php
/**
 * Слепок содержимого задачи.
 *
 * Каждая запись — содержимое задачи на момент, когда оно стало текущим:
 * один слепок создаётся при создании задачи и по одному на каждое
 * сохранение задачи через форму — в том числе когда содержимое не менялось.
 * Благодаря этому текст задачи, затёртый случайной правкой, остаётся
 * в истории и может быть восстановлен.
 *
 * Слепок хранит только то, что задаётся формой задачи: название, описание,
 * тип, оценку и плановую дату завершения. Приоритет и статус в него
 * не входят: их меняют в обход формы (стрелки в списке задач, перетаскивание
 * на Scrum доске, смена статуса), нового слепка это не создаёт — так же, как
 * не меняет {@see Issue::$revision}, — и слепок хранил бы их устаревшими.
 *
 * Состав вложений (изображения и файлы) в слепок не входит:
 * они сохраняются отдельно от содержимого задачи и удаляются физически.
 *
 * Не путать с {@see Issue::$revision} — это метка текущего состояния
 * содержимого для контроля одновременного редактирования, а не история.
 */
class IssueContentSnapshot extends LPMBaseObject
{
    /**
     * Значение {@see $editorId} для слепка, автора которого установить нельзя.
     */
    const UNKNOWN_EDITOR_ID = 0;

    /**
     * Фиксирует текущее содержимое задачи новым слепком.
     *
     * Вызывается сразу после записи содержимого задачи в БД — тогда слепок
     * повторяет то, что реально сохранено. Сбой записи истории намеренно
     * не прерывает сохранение задачи: ошибка только пишется в лог,
     * чтобы пользователь не потерял правку из-за проблем с историей.
     *
     * @param int $issueId Идентификатор задачи.
     * @param int $editorId Пользователь, сохранивший это содержимое.
     * @return bool Записан ли слепок.
     */
    public static function record($issueId, $editorId)
    {
        try {
            $issue = Issue::load($issueId);
            if (empty($issue)) {
                LPMLog::error('Не удалось сохранить слепок содержимого задачи: задача не найдена', LPMLog::CH_APP, [
                    'issueId' => (int)$issueId,
                ]);
                return false;
            }

            self::add($issue, $editorId);
            return true;
        } catch (\Throwable $e) {
            LPMLog::exception($e, LPMLog::CH_APP, ['issueId' => (int)$issueId]);
            return false;
        }
    }

    /**
     * Фиксирует текущее содержимое задачи базовым слепком,
     * если история задачи ещё пуста.
     *
     * Нужно для задач, созданных до появления истории: их первая правка иначе
     * затёрла бы содержимое, от которого не осталось бы ни одного слепка.
     * Вызывается до записи изменений, автор такого слепка неизвестен
     * ({@see UNKNOWN_EDITOR_ID}).
     *
     * Как и {@see record()}, не бросает исключений.
     *
     * @param int $issueId Идентификатор задачи.
     * @return bool Записан ли базовый слепок.
     */
    public static function recordBaseline($issueId)
    {
        try {
            if (self::hasSnapshots($issueId)) {
                return false;
            }

            $issue = Issue::load($issueId);
            if (empty($issue)) {
                return false;
            }

            self::add($issue, self::UNKNOWN_EDITOR_ID);
            return true;
        } catch (\Throwable $e) {
            LPMLog::exception($e, LPMLog::CH_APP, ['issueId' => (int)$issueId]);
            return false;
        }
    }

    /**
     * Загружает слепки содержимого задачи, начиная с самого свежего.
     *
     * @param int $issueId Идентификатор задачи.
     * @param int $limit Максимальное количество слепков; null — все.
     * @return array<IssueContentSnapshot>
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function loadListByIssue($issueId, $limit = null)
    {
        $hash = [
            'SELECT' => '*',
            'FROM' => LPMTables::ISSUE_CONTENT_SNAPSHOTS,
            'WHERE' => [
                'issueId' => (int)$issueId,
            ],
            'ORDER BY' => '`id` DESC',
        ];

        if (!empty($limit)) {
            $hash['LIMIT'] = (int)$limit;
        }

        return self::loadAndParseV2($hash, __CLASS__);
    }

    /**
     * Проверяет, есть ли у задачи хотя бы один сохранённый слепок.
     *
     * @param int $issueId Идентификатор задачи.
     * @return bool
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function hasSnapshots($issueId)
    {
        $res = self::loadFromDV2([
            'SELECT' => '1',
            'FROM' => LPMTables::ISSUE_CONTENT_SNAPSHOTS,
            'WHERE' => [
                'issueId' => (int)$issueId,
            ],
            'LIMIT' => 1,
        ]);

        return $res->num_rows > 0;
    }

    /**
     * Записывает содержимое задачи новым слепком.
     *
     * @param Issue $issue Задача с актуальным содержимым.
     * @param int $editorId Пользователь, сохранивший это содержимое.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    private static function add(Issue $issue, $editorId)
    {
        self::buildAndSaveToDbV2([
            'INSERT' => [
                'issueId' => (int)$issue->id,
                'name' => (string)$issue->name,
                'desc' => (string)$issue->desc,
                'type' => (int)$issue->type,
                'hours' => (float)$issue->hours,
                'completeDate' => empty($issue->completeDate)
                    ? null
                    : DateTimeUtils::mysqlDate($issue->completeDate),
                'editorId' => (int)$editorId,
                'createdAt' => DateTimeUtils::mysqlDate(),
            ],
            'INTO' => LPMTables::ISSUE_CONTENT_SNAPSHOTS,
        ]);
    }

    /**
     * Идентификатор слепка.
     * @var int
     */
    public $id = 0;

    /**
     * Идентификатор задачи.
     * @var int
     */
    public $issueId = 0;

    /**
     * Название задачи в этом слепке.
     * @var string
     */
    public $name = '';

    /**
     * Описание задачи в этом слепке.
     * @var string
     */
    public $desc = '';

    /**
     * Тип задачи (см. Issue::TYPE_*).
     * @var int
     */
    public $type = 0;

    /**
     * Оценка: нормочасы, а для Scrum проектов — story points.
     * @var float
     */
    public $hours = 0;

    /**
     * Плановая дата завершения задачи; 0, если не задана.
     * @var float
     */
    public $completeDate = 0;

    /**
     * Пользователь, сохранивший это содержимое.
     * {@see UNKNOWN_EDITOR_ID}, если установить его нельзя.
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

        $this->_typeConverter->addIntVars('id', 'issueId', 'type', 'editorId');
        $this->_typeConverter->addFloatVars('hours');
        $this->addDateTimeFields('completeDate', 'createdAt');

        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }
}
