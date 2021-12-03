<?php
/**
 * Расширение класса Parsedown для работы в проекте.
 *
 * Добавлено:
 * - выделение жирным с помощью одной *звездочки*
 * - зачеркивание с помощью одной ~тильды~
 */
class ParsedownExt extends Parsedown
{
    private $_strongRegex;
    private $_delRegex;
    private $_underlineRegex;
    private $_userLinkRegex;
    private $_taskLinkRegex;

    public function __construct()
    {
        array_unshift($this->InlineTypes['*'], 'Strong');
        // Нам подходит EM по звездочке
        $this->_strongRegex = $this->EmRegex['*'];
        # $this->_strongRegex = '/\*([^\s][^*\n]*?)\*/';
         
        $this->InlineTypes['~'][] = 'Del';
        $this->_delRegex = '/^~(?=\S)(.+?)(?<=\S)~/';

        array_unshift($this->InlineTypes['_'], 'Underline');
        // Нам подходит Strong по __
        $this->_underlineRegex = $this->StrongRegex['_'];

        array_unshift($this->InlineTypes['['], 'UserLink');
        $this->_userLinkRegex = '/^\[(@.*?)]\(user:([0-9]+)\)/';

        array_unshift($this->InlineTypes['['], 'IssueLink');
        $this->_taskLinkRegex = '/^\[(#\d*?)]\((.*?)\)/';
    }

    protected function inlineStrong($Excerpt)
    {
        return $this->inline($Excerpt, $this->_strongRegex, 'strong');
    }

    protected function inlineUnderline($Excerpt)
    {
        if (!isset($Excerpt['text'][1]) || $Excerpt['text'][1] !== '_') {
            return;
        }

        return $this->inline($Excerpt, $this->_underlineRegex, 'u');
    }

    protected function inlineDel($Excerpt)
    {
        return $this->inline($Excerpt, $this->_delRegex, 'del');
    }

    protected function inlineUserLink($Excerpt)
    {
        if (preg_match($this->_userLinkRegex, $Excerpt['text'], $matches)) {
            $url = UserPage::getUrlFor($matches[2]);
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'handler' => 'line',
                    'nonNestables' => array('Url', 'Link'),
                    'text' => $matches[1],
                    'attributes' => [
                        'href' => $url,
                        'class' => 'user-link',
                    ],
                ],
            ];
        }
    }

    protected function inlineIssueLink($Excerpt)
    {
        if (preg_match($this->_taskLinkRegex, $Excerpt['text'], $matches)) {
            $issueId = mb_substr($matches[1], 1);
            $project = Project::$currentProject;
            $issue = Issue::loadByIdInProject($project->id, $issueId);
            $images = $issue->getImages();
            $imageUrl = empty($images) ? null : $images[0]->getSource();
            if (!empty($issue)) {
                return [
                    'extent' => strlen($matches[0]),
                    'element' => [
                        'name' => 'a',
                        'handler' => 'line',
                        'nonNestables' => array('Url', 'Link'),
                        'text' => $matches[1],
                        'attributes' => [
                            'href' => $matches[2],
                            'data-issue-id' => $issue->getID(),
                            'data-tooltip' => 'issue',
                            'data-img' => $imageUrl,
                            'title' => $issue->getName(),
                        ],
                    ],
                ];
            }
        }
    }

    private function inline($Excerpt, $regex, $name)
    {
        if (!isset($Excerpt['text'][1])) {
            return;
        }

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
