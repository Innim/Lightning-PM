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
}
?>