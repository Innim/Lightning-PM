<?php
/**
 * Контекст предметной области проекта, общий для всех запросов к ИИ.
 *
 * Модель не знает, что за продукт ведётся в проекте, на чём он написан и что
 * означают принятые в команде слова. Этот кусок запроса собирается здесь один
 * раз для всех билдеров ({@see IssueSummaryBuilder}, {@see IssueDraftBuilder},
 * {@see IssueTestChecklistBuilder}), чтобы новый билдер получал контекст,
 * добавив одну строку.
 *
 * Пример использования:
 * <code>
 * $block = AiProjectContext::block($issue->getProject());
 * if ($block !== '') {
 *     $text .= "\n\n" . $block;
 * }
 * </code>
 */
class AiProjectContext
{
    /** Заголовок блока контекста в тексте запроса */
    const PROMPT_TITLE = 'Контекст проекта (о продукте, стеке и принятых в команде терминах)';

    /**
     * Приводит введённый пользователем контекст к виду, в котором он хранится:
     * без обрамляющих пробелов и переводов строки.
     *
     * @param mixed $text Введённое значение.
     * @return string
     */
    public static function normalize($text)
    {
        return trim((string)$text);
    }

    /**
     * Проверяет, укладывается ли контекст в допустимый размер.
     *
     * Контекст уходит в каждый запрос к модели, поэтому его длина ограничена
     * (AI_PROJECT_CONTEXT_MAX_LENGTH).
     *
     * @param string $text Нормализованный контекст (см. {@see self::normalize()}).
     * @return bool
     */
    public static function isValid($text)
    {
        return mb_strlen((string)$text) <= AI_PROJECT_CONTEXT_MAX_LENGTH;
    }

    /**
     * Собирает блок запроса с контекстом проекта.
     *
     * Результат зависит только от переданного проекта: блок входит в текст
     * запроса, по которому считается sourceHash кэшируемых артефактов.
     *
     * @param Project|false|null $project Проект, для которого строится запрос.
     * @return string Блок текста запроса или пустая строка, если контекст
     * не задан либо проект не загружен — тогда в запросе не появляется
     * ни строки о контексте.
     */
    public static function block($project)
    {
        if (empty($project)) {
            return '';
        }

        $context = self::normalize($project->aiContext);
        if ($context === '') {
            return '';
        }

        // Обрезаем на случай, если ограничение ввели позже сохранённого
        // значения: длина контекста определяет стоимость каждого запроса.
        $context = mb_substr($context, 0, AI_PROJECT_CONTEXT_MAX_LENGTH);

        return self::PROMPT_TITLE . ":\n" . $context;
    }
}
