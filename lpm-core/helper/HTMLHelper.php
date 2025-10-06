<?php
class HTMLHelper
{
    public static $bbTags = ['b', 'i', 'u', 'code'];

    /**
     * Автоматически заменяет url'ы в тексте на HTML ссылки
     * @param  string $text
     * @return string
     */
    public static function linkIt($text)
    {
        return preg_replace(
            ParseTextHelper::URL_PATTERN,
            '<a href="$1" target="_blank">$1</a>$2',
            $text
        );
    }

    /**
     * Автоматически заменяет обертку блоков ` и ``` на теги кода.
     * @param  string $text Текст для подсветки кода в нем.
     * @param  boolean $htmlEncode Определяет, нужно ли заменять html символы на эквиваленты внутри
     *                             блоков кода.
     * @return string Текст, в котором уже подсвечен код.
     * @Deprecated Используйте вместо этого ParsedownExt с включенным LpmCodeHighlight
     */
    public static function codeIt($text, $htmlEncode = true)
    {
        return self::processCode(
            $text,
            function ($matches) use ($htmlEncode) {
                if ($matches[2] === null || trim($matches[2]) === '') {
                    $tag = 'pre';
                    $text = trim($matches[4], "\n\r");
                    if ($htmlEncode) {
                        $text = htmlentities($text);
                    }
                    $className = empty($matches[3]) ? 'no-highlight' : trim($matches[3]);
                    $text = '<code class="' . $className . '">' . $text . '</code>';
                } else {
                    $tag = 'code';
                    $text = $matches[2];
                    if ($htmlEncode) {
                        $text = htmlentities($text);
                    }
                }

                return $matches[1] . '<' . $tag . '>' . $text . '</' . $tag . '>';
            }
        );
    }

    /**
     * Преобразует символы форматирования в HTML код.
     * Поддерживаемое форматирование:
     * - *жирность*
     * - _курсив_
     * - ~зачеркнуто~
     * - __подчеркнуто__
     * - > цитаты
     * - заменяет переносы строки на <br>
     * - преобразует url в ссылки
     * - поддержка задания списков через - (для вложенности следует использовать отступ)
     * - поддержка прочей Markdown разметки (https://ru.wikipedia.org/wiki/Markdown)
     *
     * @param  string $text Текст для форматирования.
     * @param  boolean $safeMode Включает безопасный режим, в котором запрещен сырой HTML.
     * @return string Текст с HTML разметкой форматирования.
     */
    public static function formatIt($text, $safeMode = true)
    {
        $parsedown = new ParsedownExt();
        $parsedown->setBreaksEnabled(true);
        $parsedown->setSafeMode($safeMode);
        $parsedown->setLpmCodeHighlight(true);

        return $parsedown->text($text);
    }

    /**
     * Удаляет все блоки многострочного кода (```), а для инлайн кода (`) только заменяет теги,
     * не помечая его при этом как код.
     * @param  string $text
     * @return string
     */
    public static function stripCode($text)
    {
        return self::processCode(
            $text,
            function ($matches) {
                if (empty($matches[2])) {
                    $text = '';
                } else {
                    $text = htmlentities($matches[2]);
                }
                return $matches[1] . $text;
            }
        );
    }

    /**
     * Возвращает обработанный форматированный текст задачи,
     * который можно выводить на html странице.
     * @return string
     */
    public static function htmlTextForIssue($text)
    {
        $value = $text;
        $value = self::getMarkdownText($value);
        
        return $value;
    }

    /**
     * Возвращает обработанный форматированный текст комментария,
     * который можно выводить на html странице.
     * @return string
     */
    public static function htmlTextForComment($text)
    {
        $value = $text;
        $value = self::getMarkdownText($value);

        // Для совместимости, чтобы старые комменты не поплыли
        $value = self::proceedBBCode($value);

        return $value;
    }

    /**
     * Возвращает форматированный текст в разметке Markdown.
     * @param string $textContent
     * @return string
     */
    public static function getMarkdownText($textContent)
    {
        $textContent = self::normalizeLegacyFencedCode($textContent);
        return self::formatIt($textContent);
    }

    /**
     * Строит строку атрибутов data-* для HTML элемента.
     * @param  array $data Массив ключ-значение для атрибутов.
     * @return string      Строка атрибутов data-*, готовая для вставки в HTML тег.
     */
    public static function buildDataAttributes(array $data)
    {
        $result = '';
        foreach ($data as $key => $value) {
            $result .= ' data-' . $key . '="' . htmlspecialchars($value) . '"';
        }
        return $result;
    }

    private static function processCode($text, $func)
    {
        return preg_replace_callback(
            "/(^|[^`])(?:`([^`\n]{1,})`|```(\w{1,}\s*\n)?([^`].*?[^`])```)/ius",
            $func,
            $text
        );
    }

    private static function proceedBBCode($value)
    {
        $tags = implode('|', self::$bbTags);
        $value = preg_replace("/\[(" . $tags . ")\](.*?)\[\/\\1\]/", "<$1>$2</$1>", $value);
        return $value;
    }

    /**
     * Backward compatibility for legacy fenced code format where opening and/or
     * closing backticks (```) were on the same line as the code content.
     *
     * Transforms:
     *  - "```<code"      -> "```\n<code" (only when not a language spec line)
     *  - "...```\n"      -> "...\n```\n"
     *
     * Keeps standard Markdown intact, including language hints like "```js".
     */
    private static function normalizeLegacyFencedCode($text)
    {
        // Normalize legacy fenced code blocks where opening/closing ```
        // were placed on the same line as code content.
        // Example (legacy): ```<code...>\n...``` -> normalize to standard fences.
        return self::processCode(
            $text,
            function ($matches) {
                $res = $matches[0];

                // support for legacy fenced code blocks where opening/closing ```
                // could be placed on the same line as code content.
                $isFencedCode = ($matches[2] === null || trim($matches[2]) === '');
                if ($isFencedCode) {
                    $lang = empty($matches[3]) ? '' : trim($matches[3]);
                    if (empty($lang)) {
                        $content = $matches[4];
                        $trimmed = trim($content, "\s\r");
                        $isStartAndEndWithNewline = !empty($trimmed) && mb_substr($trimmed, 0, 1) == "\n" && mb_substr($trimmed, -1) == "\n";
                        if (!$isStartAndEndWithNewline) {
                            $res = "```\n" . $content . "\n```";
                        }
                    }
                }

                return $res;
            }
        );
    }
}
