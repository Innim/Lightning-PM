<?php
class HTMLHelper {
	/**
	 * Автоматически заменяет url'ы в тексте на HTML ссылки
	 * @param  string $text 
	 * @return string
	 */
	public static function linkIt($text) {
    	return preg_replace(ParseTextHelper::URL_PATTERN,
            '<a href="$1" target="_blank">$1</a>$2', $text);
	}

	/**
	 * Автоматически заменяет обертку блоков ` и ``` на теги кода.
	 * @param  string $text Текст для подсветки кода в нем.
     * @param  boolean $htmlEncode Определяет, нужно ли заменять html символы на эквиваленты внутри
     *                             блоков кода.
	 * @return string Текст, в котром уже подсвечен код.
	 */
	public static function codeIt($text, $htmlEncode = true) {
        return self::processCode($text,
    		function ($matches) use ($htmlEncode) {
    			if (empty($matches[2])) {
    				$tag = 'pre';
                    $text = trim($matches[4], "\n\r");
                    if ($htmlEncode)
    				    $text = htmlentities($text);
    				$classname = empty($matches[3]) ? 'nohighlight' : trim($matches[3]); 
    				$text = '<code class="' . $classname . '">' . $text . '</code>';
    			} else {
    				$tag = 'code';
                    $text = $matches[2];
                    if ($htmlEncode)
                        $text = htmlentities($text);
    			}

    			return $matches[1] . '<' . $tag . '>' . $text . '</' . $tag . '>';
    		});
	}

    /**
     * Преобразует символы форматирования в HTML код.
     * Поддерживаемое форматирование:
     * - *жирность*
     * - _курсив_
     * - ~зачеркнуто~
     * - __подчеркнуто__
     * - > цитаты
     * - заменяет переносы строки на br
     * - преобразует url в ссылки
     * - поддержка задания списков через - (для вложенности следующет использовать отступ)
     * - поддержка прочей Markdown разметки (https://ru.wikipedia.org/wiki/Markdown)
     *
     * Текст внутри блока кода игнорируется (надо вызвать после codeIt()).
     * @param  string $text Текст для форматирования.
     * @return string Текст с HTML разметкой форматирования.
     * 
     */
    public static function formatIt($text) {
        $parsedown = new ParsedownExt();
        $parsedown->setBreaksEnabled(true);

        return $parsedown->text($text);
    }

    /**
     * Удаляет все блоки многострочного кода (```), а для инлайн кода (`) только заменяет теги,
     * не помечая его при этом как код.
     * @param  string $text 
     * @return string
     */
    public static function stripCode($text) {
        return self::processCode($text,
            function ($matches) {
                if (empty($matches[2])) {
                    $text = '';
                } else {
                    $text = htmlentities($matches[2]);
                }
                return $matches[1] . $text;
            });
    }

    private static function processCode($text, $func) {
        return preg_replace_callback(
            "/(^|[^`])(?:`([^`\n]{1,})`|```(\w{1,}\s*\n)?([^`].*?[^`])```)/ius",
            $func, $text);
    }
}