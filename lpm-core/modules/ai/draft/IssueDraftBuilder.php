<?php
/**
 * Сборка черновика задачи по свободному описанию и скриншотам.
 *
 * Отвечает за то, какие данные уходят в модель, за проверку условий,
 * при которых черновик доступен, и за приведение ответа модели
 * к значениям полей формы задачи.
 *
 * Черновик не кэшируется: он предлагается пользователю для правки и
 * становится задачей только после того, как тот сохранит форму, поэтому
 * у него нет слепка исходных данных (в отличие от {@see IssueSummaryBuilder}) —
 * обесценивать нечего.
 *
 * Пример использования:
 * <code>
 * if (IssueDraftBuilder::isAvailableFor($project)) {
 *     $images = IssueDraftBuilder::parseImages($dataUris);
 *     $result = IssueDraftBuilder::generate($project, $text, $images);
 *     $desc = IssueDraftBuilder::toDescText($result['draft']);
 * }
 * </code>
 */
class IssueDraftBuilder
{
    /** Максимальная длина свободного описания, передаваемого модели */
    const TEXT_MAX_LENGTH = 10000;

    /** Сколько изображений можно приложить к одному запросу */
    const MAX_IMAGES = 4;

    /**
     * Предельный суммарный размер приложенных изображений в мегабайтах.
     *
     * Ограничение задано на сумму, а не на каждое изображение: модель
     * принимает запрос целиком, и его размер определяет именно она.
     */
    const MAX_IMAGES_TOTAL_SIZE_MB = 10;

    /**
     * Максимальная длина названия задачи.
     * Совпадает с ограничением поля названия в форме задачи.
     */
    const NAME_MAX_LENGTH = 255;

    /** Температура генерации: черновик должен быть предсказуемым */
    const TEMPERATURE = 0.3;

    /**
     * Ограничение длины ответа.
     *
     * Задано с запасом: в лимит входят и токены рассуждений модели.
     */
    const MAX_OUTPUT_TOKENS = 4000;

    /** Названия типов задачи в ответе модели */
    const TYPE_DEVELOP = 'develop';
    const TYPE_BUG = 'bug';
    const TYPE_SUPPORT = 'support';

    /**
     * Системная инструкция для модели.
     *
     * Формат задачи здесь не описан: он берётся из правил оформления
     * (см. {@see LPMOptions::getIssueGuidelines()}), которые дописываются
     * к инструкции в {@see self::buildSystemInstruction()}.
     */
    const SYSTEM_INSTRUCTION = <<<TEXT
Ты помогаешь завести задачу в трекере команды разработки.
Пользователь описывает проблему или пожелание своими словами и может
приложить скриншоты. Ты превращаешь это в черновик задачи, который автор
затем правит и сохраняет.

Правила:
- отвечай на русском языке;
- опирайся только на переданные текст и изображения;
- со скриншота снимай конкретику: точный текст ошибки, коды, названия
  экранов и полей — переноси их в описание дословно;
- не пересказывай очевидное с картинки и не описывай её оформление.
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
     * Определяет, доступен ли черновик задачи в проекте: настроена ли
     * интеграция с ИИ и включён ли черновик в настройках проекта.
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

        return (bool)$project->aiIssueDraft;
    }

    /**
     * MIME-типы изображений черновика, которые можно приложить к задаче.
     *
     * Модель принимает больше форматов, чем вложения задачи (например webp),
     * поэтому переносить в форму можно не любое изображение черновика.
     *
     * @return string[]
     */
    public static function getAttachableMimeTypes()
    {
        $types = [];

        foreach (array_keys(LPMImgUpload::getAllowedTypes()) as $imageType) {
            $mimeType = image_type_to_mime_type($imageType);
            if (in_array($mimeType, AiImage::getSupportedMimeTypes(), true)) {
                $types[] = $mimeType;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Разбирает изображения, приложенные пользователем.
     *
     * @param array $dataUris Изображения строками data URI.
     * @return AiImage[]
     * @throws AiException Если изображений слишком много, они не читаются
     * или их суммарный размер больше допустимого.
     */
    public static function parseImages(array $dataUris)
    {
        if (count($dataUris) > self::MAX_IMAGES) {
            throw self::inputError('Можно приложить не больше '
                . self::MAX_IMAGES . ' изображений');
        }

        $maxTotalSize = self::MAX_IMAGES_TOTAL_SIZE_MB * 1024 * 1024;
        $totalSize = 0;

        $images = [];
        foreach ($dataUris as $dataUri) {
            try {
                $image = AiImage::fromDataUri($dataUri);
            } catch (AiException $e) {
                throw self::inputError($e->getMessage());
            }

            $totalSize += $image->getSize();
            if ($totalSize > $maxTotalSize) {
                throw self::inputError('Суммарный размер изображений не должен превышать '
                    . self::MAX_IMAGES_TOTAL_SIZE_MB . ' Мб');
            }

            $images[] = $image;
        }

        return $images;
    }

    /**
     * Составляет черновик задачи, обращаясь к модели.
     *
     * @param Project $project Проект, в котором заводится задача.
     * @param string $text Свободное описание проблемы; может быть пустым,
     * если приложено хотя бы одно изображение.
     * @param AiImage[] $images Приложенные изображения
     * (см. {@see self::parseImages()}).
     * @return array Результат генерации:
     * <code>[
     *     'draft' => ['name' => string, 'type' => int, 'desc' => string],
     *     'model' => string,
     *     'usage' => AiUsage|null
     * ]</code>
     * @throws AiException Если описывать нечего, обращение к модели
     * не удалось, ответ оборван или его не удалось разобрать.
     */
    public static function generate(Project $project, $text, array $images = [])
    {
        $text = trim((string)$text);
        if ($text === '' && empty($images)) {
            throw self::inputError('Опишите задачу или приложите изображение');
        }

        $prompt = self::buildPrompt($project, $text, count($images));

        $request = (new AiRequest())
            ->setSystemInstruction(self::buildSystemInstruction())
            ->addUserMessage($prompt, $images)
            ->setTemperature(self::TEMPERATURE)
            ->setMaxOutputTokens(self::MAX_OUTPUT_TOKENS)
            ->setResponseSchema(self::getResponseSchema());

        $response = AiIntegration::getInstance()->getAdapter()->generate($request);
        $usage = $response->getUsage();

        LPMLog::info('Составлен черновик задачи', LPMLog::CH_AI, [
            'projectId' => $project->getID(),
            'textLength' => mb_strlen($text),
            'imagesCount' => count($images),
            'model' => $response->getModel(),
            'finishReason' => $response->getFinishReason(),
            'usage' => $usage === null ? null : $usage->toArray(),
        ]);

        if (!$response->isComplete()) {
            throw new AiException('Модель не смогла составить черновик задачи');
        }

        return [
            'draft' => self::parseDraft($response->getJson()),
            'model' => $response->getModel(),
            'usage' => $usage,
        ];
    }

    /**
     * Возвращает из черновика текст описания задачи в разметке Markdown.
     *
     * Разделы описания задаёт не этот класс, а правила оформления
     * ({@see LPMOptions::getIssueGuidelines()}), которым следует модель.
     *
     * Это черновик: пользователь правит его перед сохранением задачи.
     *
     * @param array $draft Черновик задачи (см. {@see self::generate()}).
     * @return string
     */
    public static function toDescText(array $draft)
    {
        return mb_substr($draft['desc'], 0, Issue::DESC_MAX_LEN);
    }

    /**
     * Собирает системную инструкцию: общие правила черновика и принятые
     * в команде правила оформления задачи.
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Название задачи: одна короткая строка без точки в конце',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Тип задачи',
                    'enum' => [self::TYPE_DEVELOP, self::TYPE_BUG, self::TYPE_SUPPORT],
                ],
                'desc' => [
                    'type' => 'string',
                    'description' => 'Описание задачи в разметке Markdown,'
                        . ' оформленное по правилам из инструкции',
                ],
            ],
            'required' => ['name', 'type', 'desc'],
        ];
    }

    /**
     * Приводит ответ модели к значениям полей формы задачи.
     * @param array $data Разобранный ответ модели.
     * @return array
     * @throws AiException Если в ответе нет названия задачи.
     */
    private static function parseDraft(array $data)
    {
        $name = isset($data['name']) ? trim((string)$data['name']) : '';
        if ($name === '') {
            throw new AiException('Модель не предложила название задачи');
        }

        $desc = isset($data['desc']) ? trim((string)$data['desc']) : '';

        return [
            'name' => mb_substr($name, 0, self::NAME_MAX_LENGTH),
            'type' => self::parseType(isset($data['type']) ? $data['type'] : ''),
            'desc' => mb_substr($desc, 0, Issue::DESC_MAX_LEN),
        ];
    }

    /**
     * Приводит тип задачи из ответа модели к константам Issue::TYPE_*.
     *
     * Неизвестное значение считается разработкой: это тип задачи
     * по умолчанию, и пользователь всё равно правит его в форме.
     *
     * @param mixed $value Значение из ответа модели.
     * @return int
     */
    private static function parseType($value)
    {
        switch (strtolower(trim((string)$value))) {
            case self::TYPE_BUG:
                return Issue::TYPE_BUG;
            case self::TYPE_SUPPORT:
                return Issue::TYPE_SUPPORT;
            default:
                return Issue::TYPE_DEVELOP;
        }
    }

    /**
     * Собирает текст запроса к модели.
     *
     * @param Project $project Проект, в котором заводится задача.
     * @param string $text Свободное описание проблемы.
     * @param int $imagesCount Количество приложенных изображений.
     * @return string
     */
    private static function buildPrompt(Project $project, $text, $imagesCount)
    {
        $lines = [
            'Составь черновик задачи по описанию пользователя.',
            '',
            'Проект: ' . $project->name,
        ];

        $context = AiProjectContext::block($project);
        if ($context !== '') {
            $lines[] = '';
            $lines[] = $context;
        }

        if ($imagesCount > 0) {
            // Модель должна знать, что изображения — часть постановки,
            // а не иллюстрация к ней.
            $lines[] = 'К описанию приложено изображений: ' . $imagesCount
                . '. Это скриншоты, относящиеся к задаче.';
        }

        $lines[] = '';
        $lines[] = 'Описание пользователя:';
        $lines[] = $text === ''
            ? 'Текста нет — вся постановка на приложенных изображениях.'
            : self::truncate($text, self::TEXT_MAX_LENGTH);

        return implode("\n", $lines);
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
