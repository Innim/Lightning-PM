<?php
/**
 * Сборка чек-листа тестирования задачи с помощью ИИ.
 *
 * Отвечает за то, какие данные задачи уходят в модель, за проверку условий,
 * при которых чек-лист доступен, и за приведение ответа модели к тексту
 * комментария.
 *
 * Чек-лист не кэшируется: он составляется как черновик, который пользователь
 * правит и публикует комментарием, поэтому у него нет слепка исходных данных
 * (в отличие от {@see IssueSummaryBuilder}) — обесценивать нечего.
 *
 * Пример использования:
 * <code>
 * if (IssueTestChecklistBuilder::isAvailableFor($issue, $userId)) {
 *     $mrs = IssueTestChecklistBuilder::collectMergeRequests($comments);
 *     $result = IssueTestChecklistBuilder::generate($issue, $comments, $mrs);
 *     $text = IssueTestChecklistBuilder::toCommentText($result['checklist']);
 * }
 * </code>
 */
class IssueTestChecklistBuilder
{
    /** Заголовок, с которого начинается опубликованный чек-лист */
    const COMMENT_TITLE = 'Чек-лист тестирования';

    /** Сколько первых комментариев обсуждения передаётся модели */
    const HEAD_COMMENTS_COUNT = 3;

    /** Сколько последних комментариев обсуждения передаётся модели */
    const TAIL_COMMENTS_COUNT = 40;

    /** Максимальная длина текста одного комментария, передаваемого модели */
    const COMMENT_MAX_LENGTH = 2000;

    /** Максимальная длина описания задачи, передаваемого модели */
    const DESC_MAX_LENGTH = 6000;

    /** Сколько merge request'ов задачи передаётся модели */
    const MAX_MERGE_REQUESTS = 3;

    /** Максимальная длина описания merge request'а, передаваемого модели */
    const MR_DESC_MAX_LENGTH = 1500;

    /**
     * Порог, до которого модели передаются пути изменённых файлов.
     *
     * У крупного merge request'а список файлов перестаёт указывать на суть
     * изменений, поэтому передаётся только их количество.
     */
    const MAX_CHANGED_FILES = 30;

    /** Температура генерации: чек-лист должен быть предсказуемым */
    const TEMPERATURE = 0.3;

    /**
     * Ограничение длины ответа.
     *
     * Задано с запасом: в лимит входят и токены рассуждений модели.
     */
    const MAX_OUTPUT_TOKENS = 4000;

    /**
     * Пояснения к уточнению статуса задачи по его коду (@see IssueSubstatus).
     *
     * Говорят тестировщику, первый это проход тестирования или повторный.
     */
    const SUBSTATUS_NOTES = [
        IssueSubstatus::BACKLOG => 'задача не на доске, лежит в бэклоге проекта; в тестировании сейчас не находится',
        IssueSubstatus::TODO => 'задача на доске, к работе ещё не приступили; в тестировании сейчас не находится',
        IssueSubstatus::IN_PROGRESS => 'задача на доске, работа идёт; в тестировании сейчас не находится',
        IssueSubstatus::UNDER_TESTING => 'задача в тесте, её проверяют прямо сейчас',
        IssueSubstatus::PASS_TEST => 'задача в тесте и уже прошла проверку — предстоит повторная',
    ];

    /** Системная инструкция для модели */
    const SYSTEM_INSTRUCTION = <<<TEXT
Ты помогаешь команде разработки принимать готовые задачи трекера.
По описанию задачи, обсуждению в комментариях и данным merge request'ов
ты составляешь чек-лист ручного тестирования для тестировщика,
который к работе над задачей не подключался.

Правила:
- отвечай на русском языке;
- опирайся только на переданные данные, не додумывай функциональность,
  названия экранов, настроек и кнопок;
- каждый пункт — одна проверка: что сделать и какой результат считать верным;
- начинай с основного сценария задачи, затем граничные случаи и соседнюю
  функциональность, которую изменения могли задеть;
- учитывай хронологию: более поздние комментарии отменяют более ранние
  договорённости, проверять нужно итоговое решение;
- на каком этапе задача сейчас, смотри по её статусу, а не по отметкам
  в комментариях: если проверка по ней уже проходила, составляй чек-лист
  как для повторной;
- если при тестировании уже находили проблемы, включи их перепроверку;
- данные merge request'а бывают неинформативными: название повторяет имя ветки,
  описание пустое или содержит случайный текст коммита. В этом случае не строй
  по ним пункты — используй лишь как подсказку о затронутых областях;
- не выдумывай пункты ради объёма: короткий и точный список лучше длинного;
- не включай проверки, которые нельзя выполнить руками через интерфейс.
TEXT;

    /**
     * Определяет, доступен ли чек-лист тестирования для задачи: настроена ли
     * интеграция с ИИ, включён ли чек-лист в проекте и может ли пользователь
     * редактировать задачу.
     *
     * Статус задачи здесь не проверяется — момент, когда чек-лист уместен,
     * задаёт интерфейс: кнопка показывается у задачи на проверке.
     *
     * Доступ проверяется только на уровне задачи (Issue::checkEditPermit()),
     * поэтому точки входа, принимающие идентификатор задачи извне, должны
     * дополнительно требовать права на чтение проекта.
     *
     * @param Issue $issue Задача.
     * @param int $userId Идентификатор пользователя.
     * @return bool
     */
    public static function isAvailableFor(Issue $issue, $userId)
    {
        if (!AiIntegration::getInstance()->isAvailable()) {
            return false;
        }

        $project = $issue->getProject();
        if (empty($project) || !$project->aiTestChecklist) {
            return false;
        }

        return $issue->checkEditPermit($userId);
    }

    /**
     * Определяет, публиковался ли уже чек-лист тестирования в задаче.
     * @param array<Comment> $comments Все комментарии задачи.
     * @return bool
     */
    public static function isPublished(array $comments)
    {
        foreach ($comments as $comment) {
            if (!empty($comment->issueComment) && $comment->issueComment->isTestChecklist()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Собирает данные merge request'ов задачи, упомянутых в её комментариях.
     *
     * Обращается к GitLab, поэтому вызывается только по действию пользователя.
     * Данные собираются по мере возможности: если интеграция недоступна или
     * запрос не удался, merge request просто не попадёт в результат.
     *
     * @param array<Comment> $comments Все комментарии задачи.
     * @return array Данные merge request'ов в порядке упоминания.
     */
    public static function collectMergeRequests(array $comments)
    {
        $gitlab = LightningEngine::getInstance()->gitlab();
        if (!$gitlab->isAvailableForUser()) {
            return [];
        }

        // Один и тот же MR обычно упомянут в нескольких комментариях, поэтому
        // ссылки отбираются и обрезаются до обращения к GitLab.
        $urls = [];
        foreach (self::filterMeaningful($comments) as $comment) {
            foreach (ParseTextHelper::findLinks($comment->text) as $url) {
                if ($gitlab->isMRUrl($url)) {
                    $urls[$url] = true;
                }
            }
        }

        // Комментарии упорядочены от старых к новым, поэтому в хвосте — самые
        // свежие merge request'ы: именно они описывают актуальные изменения.
        $urls = array_slice(array_keys($urls), -self::MAX_MERGE_REQUESTS);

        $result = [];
        foreach ($urls as $url) {
            try {
                $mr = $gitlab->getMR($url);
            } catch (\Exception $e) {
                LPMLog::exception($e, LPMLog::CH_AI, ['mrUrl' => $url]);
                continue;
            }

            if (empty($mr)) {
                continue;
            }

            $changes = $gitlab->getMRChangedFiles(
                $mr->targetProjectId,
                $mr->internalId,
                self::MAX_CHANGED_FILES
            );

            $result[] = [
                'title' => (string)$mr->title,
                'description' => (string)$mr->description,
                'sourceBranch' => (string)$mr->sourceBranch,
                'targetBranch' => (string)$mr->targetBranch,
                'state' => $mr->isDraft() ? 'черновик' : (string)$mr->state,
                'changedFilesCount' => $changes === null ? null : $changes['count'],
                'changedFiles' => $changes === null ? [] : $changes['files'],
            ];
        }

        return $result;
    }

    /**
     * Формирует чек-лист тестирования задачи, обращаясь к модели.
     *
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Все комментарии задачи.
     * @param array $mergeRequests Данные merge request'ов
     * (см. {@see self::collectMergeRequests()}).
     * @return array Результат генерации:
     * <code>[
     *     'checklist' => ['items' => [['title' => string, 'expected' => string]],
     *                     'notes' => string[]],
     *     'model' => string,
     *     'usage' => AiUsage|null
     * ]</code>
     * @throws AiException Если обращение к модели не удалось, ответ оборван
     * или его не удалось разобрать.
     */
    public static function generate(Issue $issue, array $comments, array $mergeRequests = [])
    {
        $meaningful = self::filterMeaningful($comments);
        $omitted = 0;
        $prompt = self::buildPrompt($issue, $meaningful, $mergeRequests, $omitted);

        if ($omitted > 0) {
            LPMLog::info('Обсуждение задачи усечено для чек-листа', LPMLog::CH_AI, [
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

        LPMLog::info('Сформирован чек-лист тестирования задачи', LPMLog::CH_AI, [
            'issueId' => $issue->getID(),
            'commentsCount' => count($meaningful),
            'mergeRequestsCount' => count($mergeRequests),
            'promptLength' => mb_strlen($prompt),
            'model' => $response->getModel(),
            'finishReason' => $response->getFinishReason(),
            'usage' => $usage === null ? null : $usage->toArray(),
        ]);

        if (!$response->isComplete()) {
            throw new AiException('Модель не смогла составить чек-лист тестирования');
        }

        return [
            'checklist' => self::parseChecklist($response->getJson()),
            'model' => $response->getModel(),
            'usage' => $usage,
        ];
    }

    /**
     * Собирает из чек-листа текст комментария в разметке Markdown.
     *
     * Это черновик: пользователь правит его перед публикацией.
     *
     * @param array $checklist Разделы чек-листа (см. {@see self::generate()}).
     * @return string
     */
    public static function toCommentText(array $checklist)
    {
        $lines = ['### ' . self::COMMENT_TITLE, ''];

        $number = 0;
        foreach ($checklist['items'] as $item) {
            $number++;
            $line = $number . '. **' . $item['title'] . '**';
            if ($item['expected'] !== '') {
                $line .= ' — ' . $item['expected'];
            }
            $lines[] = $line;
        }

        if (!empty($checklist['notes'])) {
            $lines[] = '';
            $lines[] = '**На что обратить внимание:**';
            $lines[] = '';
            foreach ($checklist['notes'] as $note) {
                $lines[] = '- ' . $note;
            }
        }

        $lines[] = '';
        $lines[] = '_Черновик чек-листа составлен ИИ._';

        return implode("\n", $lines);
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
                'items' => [
                    'type' => 'array',
                    'description' => 'Пункты чек-листа в порядке проверки',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Что проверить, одно предложение',
                            ],
                            'expected' => [
                                'type' => 'string',
                                'description' => 'Какой результат считать верным, одно предложение',
                            ],
                        ],
                        'required' => ['title', 'expected'],
                    ],
                ],
                'notes' => [
                    'type' => 'array',
                    'description' => 'На что обратить внимание помимо пунктов; пустой список, если таких заметок нет',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['items', 'notes'],
        ];
    }

    /**
     * Приводит ответ модели к ожидаемой структуре чек-листа.
     * @param array $data Разобранный ответ модели.
     * @return array
     * @throws AiException Если в ответе нет ни одного пункта.
     */
    private static function parseChecklist(array $data)
    {
        $items = [];
        foreach ((array)(isset($data['items']) ? $data['items'] : []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            if ($title === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'expected' => isset($item['expected']) ? trim((string)$item['expected']) : '',
            ];
        }

        if (empty($items)) {
            throw new AiException('Модель вернула пустой чек-лист тестирования');
        }

        return [
            'items' => $items,
            'notes' => self::parseList(isset($data['notes']) ? $data['notes'] : []),
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
            if (empty($comment->issueComment) || !$comment->issueComment->isAutoComment()) {
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
     * Собирает текст запроса к модели: данные задачи, контекст проекта,
     * merge request'ы и обсуждение.
     *
     * Длинное обсуждение усекается — передаются только первые и последние
     * комментарии, факт усечения указывается в запросе.
     *
     * @param Issue $issue Задача.
     * @param array<Comment> $comments Содержательные комментарии в хронологическом порядке.
     * @param array $mergeRequests Данные merge request'ов.
     * @param int $omitted Сюда записывается количество опущенных комментариев.
     * @return string
     */
    private static function buildPrompt(Issue $issue, array $comments, array $mergeRequests, &$omitted = 0)
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

        $text = "Составь чек-лист тестирования по задаче.\n\n" . self::buildIssueBlock($issue);

        $context = AiProjectContext::block($issue->getProject());
        if ($context !== '') {
            $text .= "\n\n" . $context;
        }

        if (!empty($mergeRequests)) {
            $text .= "\n\nMerge request'ы задачи:\n";
            foreach ($mergeRequests as $mr) {
                $text .= "\n" . self::buildMergeRequestBlock($mr) . "\n";
            }
        }

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
            AiIssueContext::statusLine($issue, self::SUBSTATUS_NOTES),
            'Автор: ' . ($issue->author ? $issue->author->getPlainShortName() : ''),
        ];

        // У задачи в тесте пройденная проверка уже названа в статусе — отдельно
        // добавляются только незакрытые замечания, их статус не уточняет.
        if ($issue->isTesting()) {
            if ($issue->isChangesRequested) {
                $lines[] = 'Тестирование: ранее найдены проблемы, требовались правки';
            }
        } elseif ($issue->hasPassTestMark) {
            $lines[] = 'Тестирование: уже пройдено ранее';
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
     * Формирует блок одного merge request'а.
     * @param array $mr Данные merge request'а.
     * @return string
     */
    private static function buildMergeRequestBlock(array $mr)
    {
        $lines = [$mr['sourceBranch'] . ' → ' . $mr['targetBranch'] . ' (' . $mr['state'] . ')'];

        if ($mr['title'] !== '') {
            $lines[] = 'Название: ' . $mr['title'];
        }

        if ($mr['description'] !== '') {
            $lines[] = 'Описание: ' . self::truncate($mr['description'], self::MR_DESC_MAX_LENGTH);
        }

        if ($mr['changedFilesCount'] !== null) {
            $lines[] = 'Изменено файлов: ' . $mr['changedFilesCount'];
            if (!empty($mr['changedFiles'])) {
                $lines[] = 'Файлы:' . "\n" . implode("\n", $mr['changedFiles']);
            }
        }

        return implode("\n", $lines);
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
            case IssueCommentType::TEST_CHECKLIST:
                return 'ранее составленный чек-лист тестирования';
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
