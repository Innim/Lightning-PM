<?php
/**
 * Проверка качества постановки задачи: хватает ли в ней контекста, чтобы
 * задачу можно было взять в работу без уточнений.
 *
 * Отвечает за то, какие данные формы уходят в модель, за проверку условий,
 * при которых проверка доступна, и за приведение ответа модели к разбору
 * постановки: вывод, список пробелов с уточняющими вопросами и — по желанию
 * модели — улучшенная формулировка описания.
 *
 * Результат проверки не кэшируется: это подсказка автору по тексту, который
 * тот прямо сейчас правит в форме, поэтому у неё нет слепка исходных данных
 * (в отличие от {@see IssueSummaryBuilder}) — обесценивать нечего.
 *
 * Пример использования:
 * <code>
 * if (IssueReviewBuilder::isAvailableFor($project)) {
 *     $result = IssueReviewBuilder::generate($project, $name, $type, $desc);
 *     $desc = IssueReviewBuilder::toDescText($result['review']);
 * }
 * </code>
 */
class IssueReviewBuilder
{
    /**
     * Максимальная длина названия задачи, передаваемого модели.
     * Совпадает с ограничением поля названия в форме задачи.
     */
    const NAME_MAX_LENGTH = 255;

    /** Максимальная длина описания задачи, передаваемого модели */
    const DESC_MAX_LENGTH = 20000;

    /** Температура генерации: разбор постановки должен быть предсказуемым */
    const TEMPERATURE = 0.3;

    /**
     * Ограничение длины ответа.
     *
     * Задано с запасом: в лимит входят и токены рассуждений модели.
     */
    const MAX_OUTPUT_TOKENS = 4000;

    /** Названия типов задачи в запросе к модели */
    const TYPE_DEVELOP = 'develop';
    const TYPE_BUG = 'bug';
    const TYPE_SUPPORT = 'support';

    /**
     * Системная инструкция для модели.
     *
     * Что считается полной постановкой, здесь не описано: это берётся
     * из правил оформления (см. {@see LPMOptions::getIssueGuidelines()}),
     * которые дописываются к инструкции в {@see self::buildSystemInstruction()}.
     */
    const SYSTEM_INSTRUCTION = <<<TEXT
Ты помогаешь автору задачи в трекере команды разработки довести постановку
до состояния, в котором задачу можно взять в работу без уточнений.

Тебе дают название, тип и описание задачи — так, как автор написал их прямо
сейчас в форме. Ты отвечаешь автору: чего в постановке не хватает и что нужно
уточнить, чтобы исполнитель не пришёл с вопросами.

Правила:
- отвечай на русском языке, обращайся к автору задачи;
- опирайся только на переданный текст задачи; не додумывай функциональность,
  названия экранов, кнопок и настроек, версии и окружение;
- пробел — это то, без чего исполнитель не сможет начать работу или сдаст
  не тот результат, который задумал автор; замечания к стилю и формулировкам
  пробелом не считаются;
- к каждому пробелу задавай ровно один конкретный вопрос автору, ответ
  на который этот пробел закрывает;
- не требуй разделов и данных, которые для задачи этого типа не нужны;
- если постановки достаточно, так и скажи и не выдумывай пробелы ради объёма;
- улучшенную формулировку описания предлагай, только если можешь собрать её
  из уже переданных данных: не отвечай в ней на собственные вопросы, не
  придумывай недостающее и не выбрасывай подробности, приведённые автором.
  Если добавить нечего, оставь её пустой.
TEXT;

    /** Заголовок блока правил оформления в системной инструкции */
    const GUIDELINES_HEADER = 'Правила оформления задачи, принятые в команде:';

    /**
     * Ошибка, текст которой показывается пользователю.
     *
     * У AiException localizedMessage по умолчанию общий («Ошибка при обращении
     * к ИИ»), и это верно для сбоя модели — её внутренности пользователю
     * не нужны. Но ввод пользователь может исправить сам, поэтому для проверок
     * ввода текст ошибки задаётся явно.
     *
     * @param string $message Текст ошибки для пользователя.
     * @return AiException
     */
    private static function inputError($message)
    {
        return new AiException($message, 0, $message);
    }

    /**
     * Определяет, доступна ли проверка постановки в проекте: настроена ли
     * интеграция с ИИ и включена ли проверка в настройках проекта.
     *
     * Права здесь не проверяются — точки входа, принимающие идентификатор
     * проекта извне, должны дополнительно требовать права на проект.
     *
     * @param Project $project Проект.
     * @return bool
     */
    public static function isAvailableFor(Project $project)
    {
        if (!AiIntegration::getInstance()->isAvailable()) {
            return false;
        }

        return (bool)$project->aiIssueReview;
    }

    /**
     * Проверяет постановку задачи, обращаясь к модели.
     *
     * @param Project $project Проект, в котором заводится или правится задача.
     * @param string $name Название задачи из формы.
     * @param int $type Тип задачи из формы (одна из констант Issue::TYPE_*).
     * @param string $desc Описание задачи из формы.
     * @return array Результат проверки:
     * <code>[
     *     'review' => [
     *         'ready' => bool,
     *         'summary' => string,
     *         'gaps' => [['title' => string, 'question' => string]],
     *         'desc' => string
     *     ],
     *     'model' => string,
     *     'usage' => AiUsage|null
     * ]</code>
     * @throws AiException Если проверять нечего, обращение к модели
     * не удалось, ответ оборван или его не удалось разобрать.
     */
    public static function generate(Project $project, $name, $type, $desc)
    {
        $name = trim((string)$name);
        $desc = trim((string)$desc);

        if ($name === '' && $desc === '') {
            throw self::inputError('Заполните название или описание задачи');
        }

        $prompt = self::buildPrompt($project, $name, $type, $desc);

        $request = AiRequest::text($prompt, self::buildSystemInstruction())
            ->setTemperature(self::TEMPERATURE)
            ->setMaxOutputTokens(self::MAX_OUTPUT_TOKENS)
            ->setResponseSchema(self::getResponseSchema());

        $response = AiIntegration::getInstance()->getAdapter()->generate($request);
        $usage = $response->getUsage();

        LPMLog::info('Проверена постановка задачи', LPMLog::CH_AI, [
            'projectId' => $project->getID(),
            'nameLength' => mb_strlen($name),
            'descLength' => mb_strlen($desc),
            'model' => $response->getModel(),
            'finishReason' => $response->getFinishReason(),
            'usage' => $usage === null ? null : $usage->toArray(),
        ]);

        if (!$response->isComplete()) {
            throw new AiException('Модель не смогла проверить постановку задачи');
        }

        return [
            'review' => self::parseReview($response->getJson()),
            'model' => $response->getModel(),
            'usage' => $usage,
        ];
    }

    /**
     * Возвращает из разбора постановки улучшенное описание задачи в разметке
     * Markdown или пустую строку, если модель не предложила своей формулировки.
     *
     * Это предложение: в форму оно попадает только по отдельному действию
     * пользователя и заменяет введённое им описание.
     *
     * @param array $review Разбор постановки (см. {@see self::generate()}).
     * @return string
     */
    public static function toDescText(array $review)
    {
        return mb_substr($review['desc'], 0, Issue::DESC_MAX_LEN);
    }

    /**
     * Собирает системную инструкцию: общие правила проверки и принятые
     * в команде правила оформления задачи, по которым постановка и оценивается.
     * @return string
     */
    private static function buildSystemInstruction()
    {
        $guidelines = trim(LPMOptions::getIssueGuidelines());
        if ($guidelines === '') {
            return self::SYSTEM_INSTRUCTION;
        }

        return self::SYSTEM_INSTRUCTION . "\n\n" . self::GUIDELINES_HEADER . "\n" . $guidelines;
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
                'ready' => [
                    'type' => 'boolean',
                    'description' => 'Можно ли взять задачу в работу без уточнений',
                ],
                'summary' => [
                    'type' => 'string',
                    'description' => 'Вывод о постановке одной фразой, обращённой к автору',
                ],
                'gaps' => [
                    'type' => 'array',
                    'description' => 'Пробелы постановки в порядке важности;'
                        . ' пустой список, если постановки достаточно',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Чего не хватает, одно предложение',
                            ],
                            'question' => [
                                'type' => 'string',
                                'description' => 'Уточняющий вопрос автору,'
                                    . ' ответ на который закрывает этот пробел',
                            ],
                        ],
                        'required' => ['title', 'question'],
                    ],
                ],
                'desc' => [
                    'type' => 'string',
                    'description' => 'Улучшенная формулировка описания в разметке Markdown,'
                        . ' оформленная по правилам из инструкции;'
                        . ' пустая строка, если улучшать нечего',
                ],
            ],
            'required' => ['ready', 'summary', 'gaps', 'desc'],
        ];
    }

    /**
     * Приводит ответ модели к разбору постановки.
     *
     * Задача считается готовой к работе, только если модель не нашла ни одного
     * пробела: иначе вывод противоречил бы списку под ним.
     *
     * @param array $data Разобранный ответ модели.
     * @return array
     * @throws AiException Если модель не сказала о постановке ничего.
     */
    private static function parseReview(array $data)
    {
        $gaps = self::parseGaps(isset($data['gaps']) ? $data['gaps'] : []);
        $summary = isset($data['summary']) ? trim((string)$data['summary']) : '';

        if ($summary === '' && empty($gaps)) {
            throw new AiException('Модель не вернула результат проверки постановки');
        }

        $desc = isset($data['desc']) ? trim((string)$data['desc']) : '';

        return [
            'ready' => empty($gaps) && !empty($data['ready']),
            'summary' => $summary,
            'gaps' => $gaps,
            'desc' => mb_substr($desc, 0, Issue::DESC_MAX_LEN),
        ];
    }

    /**
     * Приводит список пробелов из ответа модели к массиву пар
     * «чего не хватает» — «уточняющий вопрос».
     *
     * Пробел без описания отбрасывается: вопрос без него автору ни о чём
     * не говорит.
     *
     * @param mixed $list Значение из ответа модели.
     * @return array
     */
    private static function parseGaps($list)
    {
        $gaps = [];
        foreach ((array)$list as $gap) {
            if (!is_array($gap)) {
                continue;
            }

            $title = isset($gap['title']) ? trim((string)$gap['title']) : '';
            if ($title === '') {
                continue;
            }

            $gaps[] = [
                'title' => $title,
                'question' => isset($gap['question']) ? trim((string)$gap['question']) : '',
            ];
        }

        return $gaps;
    }

    /**
     * Собирает текст запроса к модели.
     *
     * @param Project $project Проект, в котором заводится или правится задача.
     * @param string $name Название задачи.
     * @param int $type Тип задачи (одна из констант Issue::TYPE_*).
     * @param string $desc Описание задачи.
     * @return string
     */
    private static function buildPrompt(Project $project, $name, $type, $desc)
    {
        $lines = [
            'Проверь постановку задачи.',
            '',
            'Проект: ' . $project->name,
        ];

        $context = AiProjectContext::block($project);
        if ($context !== '') {
            $lines[] = '';
            $lines[] = $context;
        }

        $lines[] = '';
        $lines[] = 'Тип задачи: ' . self::typeName($type);
        $lines[] = 'Название: ' . ($name === ''
            ? 'не заполнено'
            : self::truncate($name, self::NAME_MAX_LENGTH));
        $lines[] = '';
        $lines[] = 'Описание:';
        $lines[] = $desc === ''
            ? 'Описание не заполнено.'
            : self::truncate($desc, self::DESC_MAX_LENGTH);

        return implode("\n", $lines);
    }

    /**
     * Возвращает название типа задачи для запроса к модели.
     *
     * Типы названы так же, как в правилах оформления задачи, — модель
     * сверяет постановку именно с ними.
     *
     * @param int $type Тип задачи (одна из констант Issue::TYPE_*).
     * @return string
     */
    private static function typeName($type)
    {
        switch ((int)$type) {
            case Issue::TYPE_BUG:
                return self::TYPE_BUG;
            case Issue::TYPE_SUPPORT:
                return self::TYPE_SUPPORT;
            default:
                return self::TYPE_DEVELOP;
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
