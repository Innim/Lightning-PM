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
        $host = LightningEngine::getHost();
        $protocols = ['http', 'https'];
        $this->_taskLinkRegex = '/^\[(#\d*?)]\(('.
                implode('|', $protocols).'):\/\/'.$host.'(.*?)\)/';
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
        if (preg_match($this->_taskLinkRegex, $Excerpt['text'], $matches) &&
                count($matches) == 4) {
            $protocol = $matches[2];
            $path = $matches[3];
            $text = $matches[1];
            $issueId = mb_substr($text, 1);
            $pathParts = explode('/', $path);
            if (count($pathParts) > 1) {
                $projectId = $pathParts[2];
                try {
                    $project = Project::load($projectId);
                    $issue = Issue::loadByIdInProject($project->id, $issueId);
                    $images = $issue->getImages();
                    $imageUrl = empty($images) ? null : $images[0]->getSource();
                    $url = $protocol . '://' . LightningEngine::getHost() . $path;
                    if (!empty($issue)) {
                        return [
                            'extent' => strlen($matches[0]),
                            'element' => [
                                'name' => 'a',
                                'handler' => 'line',
                                'nonNestables' => array('Url', 'Link'),
                                'text' => $text,
                                'attributes' => [
                                    'href' => $url,
                                    'data-issue-id' => $issue->getID(),
                                    'data-tooltip' => 'issue',
                                    'data-img' => $imageUrl,
                                    'title' => $issue->getName(),
                                ],
                            ],
                        ];
                    }
                } catch (Exception $e) {}
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
