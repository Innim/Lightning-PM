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

        $issueUrlPattern = OwnUrlHelper::getIssueUrlPattern();
        $this->_taskLinkRegex = '/^\[([^\]]*)\]\((' . $issueUrlPattern . ')\)/';
    }

    // Make Markdown tables look good with Bootstrap styles
    // Adds Bootstrap table classes to generated <table> elements
    protected function blockTable($Line, array $Block = null)
    {
        $Block = parent::blockTable($Line, $Block);

        if ($Block && isset($Block['element']) && is_array($Block['element'])) {
            if (!isset($Block['element']['attributes'])) {
                $Block['element']['attributes'] = [];
            }

            // Apply Bootstrap table styles for better visibility
            $tableClasses = 'table table-striped table-bordered';

            if (isset($Block['element']['attributes']['class']) && $Block['element']['attributes']['class']) {
                $Block['element']['attributes']['class'] .= ' ' . $tableClasses;
            } else {
                $Block['element']['attributes']['class'] = $tableClasses;
            }

            // Highlight table header with Bootstrap contextual color
            if (isset($Block['element']['text'][0])
                && isset($Block['element']['text'][0]['name'])
                && $Block['element']['text'][0]['name'] === 'thead') {
                if (!isset($Block['element']['text'][0]['attributes'])) {
                    $Block['element']['text'][0]['attributes'] = [];
                }

                $theadClasses = 'table-secondary';
                if (isset($Block['element']['text'][0]['attributes']['class'])
                    && $Block['element']['text'][0]['attributes']['class']) {
                    $Block['element']['text'][0]['attributes']['class'] .= ' ' . $theadClasses;
                } else {
                    $Block['element']['text'][0]['attributes']['class'] = $theadClasses;
                }
            }
        }

        return $Block;
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
                count($matches) == 5) {
            $text = $matches[1];
            $url = $matches[2];
            $projectUid = $matches[3];
            $issueId = (int) $matches[4];
            
            if (!empty($projectUid) && !empty($issueId)) {
                try {
                    // here we have a potential vulnerability, because we can get info about any issue,
                    // even if user has no access to it. But we already allow to get info about any issue
                    // as open graph meta for any link, so it's not a problem.
                    if ($project = Project::load($projectUid)) {
                        if ($issue = Issue::loadByIdInProject($project->id, $issueId)) {
                            $images = $issue->getImages();
                            $imageUrl = empty($images) ? null : $images[0]->getSource();
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
                                        'data-id-in-project' => $issue->getIdInProject(),
                                        'data-tooltip' => 'issue',
                                        'data-img' => $imageUrl,
                                        'title' => $issue->getName(),
                                    ],
                                ],
                            ];
                        }
                    }
                } catch(Exception $e) {}
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
