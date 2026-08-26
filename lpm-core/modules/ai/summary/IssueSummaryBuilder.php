<?php
/**
 * Сборка краткой сводки обсуждения задачи с помощью ИИ.
 *
 * Отвечает за то, какие данные задачи уходят в модель, за проверку условий,
 * при которых сводка вообще имеет смысл, и за слепок исходных данных
 * (sourceHash), по которому определяется, не устарела ли готовая сводка.
 *
 * Пример использования:
 * <code>
 * if (IssueSummaryBuilder::isAvailableFor($issue, $comments, $userId)) {
 *     $hash = IssueSummaryBuilder::sourceHash($issue, $comments);
 *     $result = IssueSummaryBuilder::generate($issue, $comments);
 *     // $result['summary'], $result['model'], $result['usage']
 * }
 * </code>
 */
class IssueSummaryBuilder
{
    /**
     * Версия запроса к модели.
     *
     * Входит в sourceHash, обесценивая все сохранённые сводки. Текст запроса
     * хэшируется целиком, поэтому увеличивать версию нужно при изменениях,
     * которые в этот текст не попадают: схема ответа, параметры генерации,
     * формат разделов сводки.
     */
    const PROMPT_VERSION = 1;

    /** Минимальное количество содержательных комментариев, при котором сводка полезна */
    const MIN_COMMENTS_COUNT = 2;

    /** Минимальная суммарная длина текста задачи, при которой сводка полезна */
    const MIN_SOURCE_LENGTH = 1500;

    /** Сколько первых комментариев обсуждения передаётся модели */
    const HEAD_COMMENTS_COUNT = 3;

    /** Сколько последних комментариев обсуждения передаётся модели */
    const TAIL_COMMENTS_COUNT = 40;

    /** Максимальная длина текста одного комментария, передаваемого модели */
    const COMMENT_MAX_LENGTH = 2000;

    /** Максимальная длина описания задачи, передаваемого модели */
    const DESC_MAX_LENGTH = 6000;

    /** Температура генерации: сводка должна быть предсказуемой */
    const TEMPERATURE = 0.2;

    /**
     * Ограничение длины ответа.
     *
     * Задано с запасом: в лимит входят и токены рассуждений модели.
     */
    const MAX_OUTPUT_TOKENS = 2000;

    /** Системная инструкция для модели */
    const SYSTEM_INSTRUCTION = <<<TEXT
Ты помогаешь участникам команды разработки быстро разобраться в задаче трекера.
По описанию задачи и обсуждению в комментариях ты составляешь краткую сводку
для человека, который подключается к задаче в середине работы.

Правила:
- отвечай на русском языке;
- опирайся только на переданные данные, не додумывай факты, названия и решения;
- пиши по существу, без вводных фраз, оценок и пересказа очевидного;
- учитывай хронологию: более поздние комментарии отменяют более ранние договорённости;
- если данных для какого-то раздела нет, оставляй его пустым, а не придумывай.
TEXT;

    /**
     * Определяет, является ли комментарий содержательным, то есть написанным
     * человеком, а не созданным системой автоматически.
     * @param Comment $comment Комментарий задачи.
     * @return bool
     */
    public static function isMeaningfulComment(Comment $comment)
    {
        return empty($comment->issueComment) || !$comment->issueComment->isAutoComment();
    }

    /**
     * Считает количество содержательных комментариев.
     * @param array<Comment> $comments Комментарии задачи.
     * @return int
     */
    public static function countMeaningful(array $comments)
    {
        return count(self::filterMeaningful($comments));
    }

    /**
     * Считает содержательные комментарии, появившиеся после составления сводки.
     *
     * Показывает, насколько сводка отстала от обсуждения. Правки описания
     * и других данных задачи здесь не учитываются, поэтому нулевой результат
     * не означает, что сводка актуальна — это проверяет
     * IssueSummary::isActualFor().
     *
     * @param IssueSummary $summary Сохранённая сводка.
     * @param array<Comment> $comments Все комментарии задачи.
     * @return int
     */
    public static function countNewComments(IssueSummary $summary, array $comments)
    {
        $count = 0;
        foreach (self::filterMeaningful($comments) as $comment) {
            if ($comment->date > $summary->createdAt) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Определяет, доступна ли сводка для задачи: настроена ли интеграция с ИИ,
     * включена ли сводка в проекте, есть ли у пользователя доступ к задаче
     * и достаточно ли в задаче содержания, чтобы сводка экономила чтение.
     *
     * Доступ проверяется только на уровне задачи (Issue::checkViewPermit()),
     * поэтому точки входа, принимающие идентификатор задачи извне, должны
     * дополнительно требовать права на чтение проекта.
     *
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Все комментарии задачи.
     * @param int $userId Идентификатор пользователя.
     * @return bool
     */
    public static function isAvailableFor(Issue $issue, array $comments, $userId)
    {
        if (!AiIntegration::getInstance()->isAvailable()) {
            return false;
        }

        $project = $issue->getProject();
        if (empty($project) || !$project->aiSummary) {
            return false;
        }

        if (!$issue->checkViewPermit($userId)) {
            return false;
        }

        $meaningful = self::filterMeaningful($comments);

        // Сводка нужна там, где обсуждение уже разрослось: задача побывала
        // в тестировании либо в ней есть несколько содержательных комментариев.
        if (!self::hasTestActivity($issue, $meaningful)
            && count($meaningful) < self::MIN_COMMENTS_COUNT
        ) {
            return false;
        }

        return self::getSourceLength($issue, $meaningful) >= self::MIN_SOURCE_LENGTH;
    }

    /**
     * Возвращает слепок данных, уходящих в модель — по нему определяется,
     * соответствует ли сохранённая сводка текущему состоянию задачи.
     *
     * Считается по тому же тексту запроса, который получит модель, поэтому
     * меняется ровно тогда, когда меняется её вход: правка описания, любое
     * поле задачи, попадающее в запрос, добавление, удаление или смена типа
     * комментария, переименование проекта. Изменения, которых модель не видит
     * (например, приоритет), сводку не обесценивают.
     *
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Все комментарии задачи.
     * @return string Хэш длиной 32 символа.
     */
    public static function sourceHash(Issue $issue, array $comments)
    {
        return md5(implode("\n", [
            'v' . self::PROMPT_VERSION,
            self::SYSTEM_INSTRUCTION,
            self::buildPrompt($issue, self::filterMeaningful($comments)),
        ]));
    }

    /**
     * Формирует сводку обсуждения задачи, обращаясь к модели.
     *
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Все комментарии задачи.
     * @return array Результат генерации:
     * <code>[
     *     'summary' => ['summary' => string, 'state' => string,
     *                   'openQuestions' => string[], 'remaining' => string[]],
     *     'model' => string,
     *     'usage' => AiUsage|null
     * ]</code>
     * @throws AiException Если обращение к модели не удалось, ответ оборван
     * или его не удалось разобрать.
     */
    public static function generate(Issue $issue, array $comments)
    {
        $meaningful = self::filterMeaningful($comments);
        $omitted = 0;
        $prompt = self::buildPrompt($issue, $meaningful, $omitted);

        if ($omitted > 0) {
            LPMLog::info('Обсуждение задачи усечено для сводки', LPMLog::CH_AI, [
                'issueId' => $issue->getID(),
                'commentsCount' => count($meaningful),
                'omitted' => $omitted,
            ]);
        }

        $request = AiRequest::text($prompt, self::SYSTEM_INSTRUCTION)
            ->setTemperature(self::TEMPERATURE)
            ->setMaxOutputTokens(self::MAX_OUTPUT_TOKENS)
            ->setResponseSchema(self::getResponseSchema());

        $response = AiIntegration::getInstance()->getAdapter()->generate($request);
        $usage = $response->getUsage();

        LPMLog::info('Сформирована сводка задачи', LPMLog::CH_AI, [
            'issueId' => $issue->getID(),
            'commentsCount' => count($meaningful),
            'promptLength' => mb_strlen($prompt),
            'model' => $response->getModel(),
            'finishReason' => $response->getFinishReason(),
            'usage' => $usage === null ? null : $usage->toArray(),
        ]);

        if (!$response->isComplete()) {
            throw new AiException('Модель не смогла составить сводку задачи');
        }

        return [
            'summary' => self::parseSummary($response->getJson()),
            'model' => $response->getModel(),
            'usage' => $usage,
        ];
    }

    /**
     * Схема ответа модели.
     * @return array
     */
    private static function getResponseSchema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => [
                    'type' => 'string',
                    'description' => 'Суть задачи и обсуждения, 2-4 предложения',
                ],
                'state' => [
                    'type' => 'string',
                    'description' => 'На чём задача остановилась сейчас, 1-2 предложения',
                ],
                'openQuestions' => [
                    'type' => 'array',
                    'description' => 'Вопросы, по которым нет решения; пустой список, если таких нет',
                    'items' => ['type' => 'string'],
                ],
                'remaining' => [
                    'type' => 'array',
                    'description' => 'Что осталось сделать по задаче; пустой список, если ничего',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['summary', 'state', 'openQuestions', 'remaining'],
        ];
    }

    /**
     * Приводит ответ модели к ожидаемой структуре сводки.
     * @param array $data Разобранный ответ модели.
     * @return array
     * @throws AiException Если в ответе нет текста сводки.
     */
    private static function parseSummary(array $data)
    {
        $summary = isset($data['summary']) ? trim((string)$data['summary']) : '';
        if ($summary === '') {
            throw new AiException('Модель вернула пустую сводку задачи');
        }

        return [
            'summary' => $summary,
            'state' => isset($data['state']) ? trim((string)$data['state']) : '',
            'openQuestions' => self::parseList(isset($data['openQuestions']) ? $data['openQuestions'] : []),
            'remaining' => self::parseList(isset($data['remaining']) ? $data['remaining'] : []),
        ];
    }

    /**
     * Приводит список пунктов ответа модели к массиву непустых строк.
     * @param mixed $list Значение из ответа модели.
     * @return array<string>
     */
    private static function parseList($list)
    {
        $result = [];
        foreach ((array)$list as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Отбирает содержательные комментарии в хронологическом порядке.
     * @param array<Comment> $comments Комментарии задачи.
     * @return array<Comment>
     */
    private static function filterMeaningful(array $comments)
    {
        $list = [];
        foreach ($comments as $comment) {
            if (self::isMeaningfulComment($comment)) {
                $list[] = $comment;
            }
        }

        usort($list, function (Comment $a, Comment $b) {
            if ($a->date == $b->date) {
                return $a->id < $b->id ? -1 : ($a->id > $b->id ? 1 : 0);
            }

            return $a->date < $b->date ? -1 : 1;
        });

        return $list;
    }

    /**
     * Суммарная длина текста задачи: описание и содержательные комментарии.
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Содержательные комментарии.
     * @return int Количество символов.
     */
    private static function getSourceLength(Issue $issue, array $comments)
    {
        $length = mb_strlen(trim($issue->desc));
        foreach ($comments as $comment) {
            $length += mb_strlen(trim($comment->getCleanText()));
        }

        return $length;
    }

    /**
     * Определяет, была ли задача в тестировании: сейчас или в прошлом.
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Содержательные комментарии.
     * @return bool
     */
    private static function hasTestActivity(Issue $issue, array $comments)
    {
        if ($issue->isTesting()) {
            return true;
        }

        foreach ($comments as $comment) {
            if (empty($comment->issueComment)) {
                continue;
            }

            if ($comment->issueComment->isPassTest()
                || $comment->issueComment->isRequestChanges()
                || $comment->issueComment->isRequestChangesResolved()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Собирает текст запроса к модели: данные задачи и обсуждение.
     *
     * Длинное обсуждение усекается — передаются только первые и последние
     * комментарии, факт усечения указывается в запросе.
     *
     * Результат не должен зависеть ни от чего, кроме переданных данных:
     * по нему считается sourceHash.
     *
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Содержательные комментарии в хронологическом порядке.
     * @param int $omitted Сюда записывается количество опущенных комментариев.
     * @return string
     */
    private static function buildPrompt(Issue $issue, array $comments, &$omitted = 0)
    {
        $total = count($comments);
        $limit = self::HEAD_COMMENTS_COUNT + self::TAIL_COMMENTS_COUNT;
        $omitted = 0;

        if ($total > $limit) {
            $omitted = $total - $limit;
            $comments = array_merge(
                array_slice($comments, 0, self::HEAD_COMMENTS_COUNT),
                array_slice($comments, -self::TAIL_COMMENTS_COUNT)
            );
        }

        $text = "Составь сводку по задаче.\n\n" . self::buildIssueBlock($issue);

        $text .= "\n\nОбсуждение (комментариев: " . $total . ')';
        if ($omitted > 0) {
            $text .= ', часть обсуждения опущена: показаны первые '
                . self::HEAD_COMMENTS_COUNT . ' и последние '
                . self::TAIL_COMMENTS_COUNT . ' комментариев';
        }
        $text .= ":\n";

        if (empty($comments)) {
            $text .= "\nКомментариев нет.";
        }

        foreach ($comments as $comment) {
            $text .= "\n" . self::buildCommentBlock($comment) . "\n";
        }

        return $text;
    }

    /**
     * Формирует блок с данными самой задачи.
     * @param Issue $issue Задача.
     * @return string
     */
    private static function buildIssueBlock(Issue $issue)
    {
        $lines = [
            'Задача #' . $issue->getIdInProject() . ': ' . $issue->getName(),
            'Проект: ' . $issue->projectName,
            'Тип: ' . $issue->getType(),
            'Статус: ' . $issue->getStatus(),
            'Автор: ' . ($issue->author ? $issue->author->getPlainShortName() : ''),
            'Создана: ' . $issue->getCreateDate(),
        ];

        if ($issue->hasCompleteDate()) {
            $lines[] = 'Плановая дата завершения: ' . $issue->getCompleteDate();
        }

        if ($issue->isCompleted()) {
            $lines[] = 'Завершена: ' . $issue->getCompletedDate();
        }

        if ($issue->isPassTest) {
            $lines[] = 'Тестирование: пройдено';
        } elseif ($issue->isChangesRequested) {
            $lines[] = 'Тестирование: найдены проблемы, требуются правки';
        }

        $desc = trim($issue->desc);
        if ($desc === '') {
            $desc = 'Описание не заполнено.';
        } else {
            $desc = self::truncate($desc, self::DESC_MAX_LENGTH);
        }

        return implode("\n", $lines) . "\n\nОписание:\n" . $desc;
    }

    /**
     * Формирует блок одного комментария обсуждения.
     * @param Comment $comment Комментарий.
     * @return string
     */
    private static function buildCommentBlock(Comment $comment)
    {
        $header = $comment->getDate() . ', ' . $comment->author->getPlainShortName();

        $label = self::getCommentTypeLabel($comment);
        if ($label !== '') {
            $header .= ' [' . $label . ']';
        }

        return $header . ":\n" . self::truncate(trim($comment->getCleanText()), self::COMMENT_MAX_LENGTH);
    }

    /**
     * Возвращает пояснение к особому типу комментария
     * или пустую строку для обычного комментария.
     * @param Comment $comment Комментарий.
     * @return string
     */
    private static function getCommentTypeLabel(Comment $comment)
    {
        if (empty($comment->issueComment)) {
            return '';
        }

        switch ($comment->issueComment->type) {
            case IssueCommentType::PASS_TEST:
                return 'тестирование пройдено';
            case IssueCommentType::REQUEST_CHANGES:
                return 'найдена проблема при тестировании';
            case IssueCommentType::REQUEST_CHANGES_RESOLVED:
                return 'найденная при тестировании проблема закрыта без правок';
            case IssueCommentType::MERGE_REQUEST:
                return 'merge request';
            default:
                return '';
        }
    }

    /**
     * Обрезает текст до указанной длины, отмечая усечение.
     * @param string $text Текст.
     * @param int $maxLength Максимальная длина.
     * @return string
     */
    private static function truncate($text, $maxLength)
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . "\n[...текст сокращён]";
    }
}
