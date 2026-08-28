<?php
/**
 * Событие журнала задачи: кто и когда что с ней сделал.
 *
 * Журнал хранит состояния, которые можно менять и снимать, - в отличие
 * от комментариев, которые остаются в ленте навсегда. Записи журнала
 * не удаляются: снятие состояния - это тоже событие.
 * @see IssueEventType
 */
class IssueEvent extends LPMBaseObject
{
    /**
     * Записывает событие в журнал задачи.
     * @param  int    $issueId  Идентификатор задачи.
     * @param  string $type     Тип события (@see IssueEventType).
     * @param  float  $userId   Идентификатор пользователя, совершившего событие.
     * @param  string $data     Дополнительные данные события.
     * @return IssueEvent Записанное событие.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    public static function create($issueId, $type, $userId, $data = null)
    {
        $fields = [
            'issueId'  => (int)$issueId,
            'type'     => (string)$type,
            'userId'   => (float)$userId,
            'date'     => DateTimeUtils::mysqlDate(),
            'data'     => (string)$data,
        ];

        self::buildAndSaveToDbV2([
            'INSERT' => $fields,
            'INTO'   => LPMTables::ISSUE_EVENT,
        ]);

        return new IssueEvent($fields);
    }

    /**
     * Возвращает описание запроса, определяющего текущее состояние задачи
     * по журналу: последнее событие среди перечисленных типов.
     *
     * Это единственное определение правила: его используют и общая выборка
     * задач (подзапросом), и точечная загрузка {@see loadLast()}.
     * @param  int|\GMFramework\DBColumn $issueId Идентификатор задачи либо
     *                                            колонка с ним - для подзапроса.
     * @param  array  $types  Типы событий, среди которых ищется последнее.
     * @param  string $select Список полей выборки. Для подзапроса поле должно
     *                        быть ровно одно.
     * @return array Описание запроса для конструктора.
     */
    public static function getLastSqlHash($issueId, array $types, $select = '`ev`.`type`')
    {
        return [
            'SELECT' => $select,
            'FROM'   => LPMTables::ISSUE_EVENT,
            'AS'     => 'ev',
            'WHERE'  => [
                '`ev`.`issueId`' => $issueId,
                '`ev`.`type`'    => $types,
            ],
            // События одной секунды разводим по id: иначе последнее
            // определялось бы произвольно
            'ORDER BY' => ['`ev`.`date` DESC', '`ev`.`id` DESC'],
            'LIMIT'    => 1,
        ];
    }

    /**
     * Загружает последнее событие задачи среди указанных типов.
     * @param  int   $issueId Идентификатор задачи.
     * @param  array $types   Типы событий, среди которых ищется последнее.
     * @return IssueEvent|null Событие или null, если таких событий нет.
     */
    public static function loadLast($issueId, array $types)
    {
        return self::loadAndParseSingleV2(
            self::getLastSqlHash((int)$issueId, $types, '*'),
            __CLASS__
        );
    }

    /**
     * Идентификатор события.
     * @var float
     */
    public $id = 0;

    /**
     * Идентификатор задачи.
     * @var int
     */
    public $issueId = 0;

    /**
     * Тип события.
     * @var string
     * @see IssueEventType
     */
    public $type = '';

    /**
     * Идентификатор пользователя, совершившего событие.
     * @var float
     */
    public $userId = 0;

    /**
     * Дата и время события.
     * @var float
     */
    public $date = 0;

    /**
     * Дополнительные данные события.
     * @var string
     */
    public $data = '';

    public function __construct($raw = null)
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('issueId');
        $this->_typeConverter->addFloatVars('id', 'userId');
        $this->addDateTimeFields('date');

        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }
}
