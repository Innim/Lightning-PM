<?php
/**
 * Расширение класса Parsedown для работы в проекте.
 *
 * Добавлено:
 * - выделение жирным с помощью одной *звездочки*
 * - зачеркивание с помощью одной ~тильды~
 */
class ParsedownExt extends Parsedown {
    private $_strongRegex;
    private $_delRegex;

    function __construct() {
        array_unshift($this->InlineTypes['*'], 'Strong');
        // Нам подходит EM по звездочке
        $this->_strongRegex = $this->EmRegex['*'];
        # $this->_strongRegex = '/\*([^\s][^*\n]*?)\*/';
         
        $this->InlineTypes['~'][] = 'Del';
        $this->_delRegex = '/^~(?=\S)(.+?)(?<=\S)~/';
    }

    protected function inlineStrong($Excerpt) {
        return $this->inline($Excerpt, $this->_strongRegex, 'strong');
    }

    protected function inlineDel($Excerpt) {
        return $this->inline($Excerpt, $this->_delRegex, 'del');
    }

    private function inline($Excerpt, $regex, $name) {
        if (!isset($Excerpt['text'][1]))
            return;

        if (preg_match($regex, $Excerpt['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => $name,
                    'text' => $matches[1],
                    'handler' => 'line',
                ],
            ];
        }
    }
}