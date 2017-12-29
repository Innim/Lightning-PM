<?php
class HTMLHelper 
{
	/**
	 * Автоматически заменяет url'ы в тексте на HTML ссылки
	 * @param  string $text 
	 * @return string
	 */
	public static function linkIt($text)
	{
    	return preg_replace(
    		"/(https?:\/\/[^<\s]+[[:alnum:]])([^[:alnum:]]*(?:<br ?\/?>)*[^a-zа-я0-9]|\s|$)/iu", 
    		'<a href="$1">$1</a>$2', $text);
	}

	/**
	 * Автоматически заменяет обертку блоков ` и ``` на теги кода 
	 * @param  string $text 
	 * @return string
	 */
	public static function codeIt($text)
	{
    	return preg_replace_callback(
    		"/(^|[^`])(?:`( [^\n]{1,} |[^`\n]{1,})`|```(\w{1,}\s*\n)?([^`].*?[^`])```)/ius",
    		function ($matches) {
    			if (empty($matches[2]))
    			{
    				$tag = 'pre';
    				$text = htmlentities(trim($matches[4], "\n\r"));
    				$classname = empty($matches[3]) ? 'nohighlight' : trim($matches[3]); 
    				$text = '<code class="' . $classname . '">' . $text . '</code>';
    			}
    			else 
    			{
    				$tag = 'code';
    				$text = htmlentities($matches[2]);
    			}
    			return $matches[1] . '<' . $tag . '>' . $text . '</' . $tag . '>';
    		}, $text);
	}
}
?>