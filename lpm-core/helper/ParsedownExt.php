<?php
/**
 * Расширение класса Parsedown для работы в проекте.
 *
 * Добавлено:
 * - выделение жирным с помощью одной *звездочки*
 * - зачеркивание с помощью одной ~тильды~
 * - список задач: строки `- [ ] текст` показываются галочками,
 *   если разбор запущен через textWithTaskList()
 */
class ParsedownExt extends Parsedown
{
    /**
     * Возвращает HTML разметку текста, в котором строки вида `- [ ] текст`
     * и `- [x] текст` показаны галочками.
     *
     * У каждой галочки в разметке указаны номер её строки в исходном тексте
     * и строка, на которую эту строку надо заменить при переключении, поэтому
     * для переключения галочки разбирать разметку заново не нужно.
     *
     * @param  string $text Текст в разметке Markdown.
     * @return string Разметка, готовая для вывода на HTML странице.
     */
    public static function textWithTaskList($text)
    {
        $parsedown = self::create();
        // Пункты ищем в исходном тексте: нормализация старого формата блоков
        // кода добавляет строки, а номера строк пунктов должны совпадать
        // с текстом, который правит пользователь
        $parsedown->_taskListItems = self::parseTaskList($text);

        return $parsedown->text(HTMLHelper::normalizeLegacyFencedCode($text));
    }

    /**
     * Создаёт парсер с настройками, принятыми в проекте.
     *
     * Настройки повторяют HTMLHelper::formatIt() - текст, разобранный этим
     * парсером, выглядит так же, как остальной текст в проекте.
     *
     * @return ParsedownExt
     */
    private static function create()
    {
        $parsedown = new ParsedownExt();
        $parsedown->setBreaksEnabled(true);
        $parsedown->setSafeMode(true);
        $parsedown->setLpmCodeHighlight(true);

        return $parsedown;
    }

    /**
     * Находит в тексте строки-пункты списка задач.
     *
     * Пунктом строку считает TASK_ITEM_LINE_REGEX - то же выражение, по
     * которому разметка решает, какой пункт списка показать галочкой.
     * Строки внутри блоков кода пунктами не считаются.
     *
     * @param  string $text Текст в разметке Markdown.
     * @return array Пункты по порядку следования в тексте, каждый в виде
     *               `['line' => номер строки с нуля, 'from' => строка как она
     *               есть, 'to' => строка с переключенным состоянием]`.
     */
    private static function parseTaskList($text)
    {
        $items = [];
        $fence = null;

        foreach (explode("\n", (string)$text) as $num => $line) {
            $line = rtrim($line, "\r");

            if ($fence !== null) {
                if (self::isCodeFenceEnd($line, $fence)) {
                    $fence = null;
                }
                continue;
            }

            if (preg_match(self::CODE_FENCE_OPEN_REGEX, $line, $open)) {
                // В старом формате блок мог уместиться в одну строку целиком:
                // тогда его закрывает ограждение в конце этой же строки
                $rest = substr($line, strlen($open[0]));
                $fence = self::isCodeFenceEnd($rest, $open[1]) ? null : $open[1];
                continue;
            }

            if (!preg_match(self::TASK_ITEM_LINE_REGEX, $line, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $checked = trim($matches[1][0]) !== '';
            $items[] = [
                'line' => $num,
                'from' => $line,
                'to' => substr_replace($line, $checked ? ' ' : 'x', $matches[1][1], 1),
            ];
        }

        return $items;
    }

    /**
     * Проверяет, закрывает ли строка блок кода, открытый этим ограждением.
     *
     * Закрывает блок ограждение того же вида и не короче открывающего,
     * стоящее в конце строки: на своей строке - как в стандартной разметке,
     * или сразу за кодом - как писали в старом формате. Ограждение, за которым
     * в строке есть ещё текст, блок не закрывает: это код, а не ограждение.
     *
     * @param  string $line  Строка текста.
     * @param  string $fence Ограждение, которым блок открыт.
     * @return bool
     */
    private static function isCodeFenceEnd($line, $fence)
    {
        return preg_match(self::CODE_FENCE_CLOSE_REGEX, $line, $end)
                && $end[1][0] === $fence[0]
                && strlen($end[1]) >= strlen($fence);
    }

    /**
     * Строка-пункт списка задач: маркер списка и состояние `[ ]` / `[x]`.
     */
    private const TASK_ITEM_LINE_REGEX = '/^[ \t]*(?:>[ \t]*)*[*+-][ \t]+\[([ xX])\](?=$|[ \t])/';

    /**
     * Состояние в начале текста пункта - того, что осталось от строки
     * после маркера списка.
     */
    private const TASK_ITEM_STATE_REGEX = '/^\[([ xX])\](?:[ \t]+|$)/';

    /**
     * Ограждение, которым начинается строка: открывает блок кода. Отступ
     * не больше трёх пробелов - с четырёх начинается блок кода с отступом,
     * и ограждение в нём уже просто текст.
     */
    private const CODE_FENCE_OPEN_REGEX = '/^ {0,3}(`{3,}|~{3,})/';

    /**
     * Ограждение, которым строка заканчивается: кандидат на закрытие блока
     * кода (см. isCodeFenceEnd()). Ограждение внутри строки блоком кода
     * не считается - в тексте цели это обычное упоминание.
     */
    private const CODE_FENCE_CLOSE_REGEX = '/(`{3,}|~{3,})[ \t]*$/';

    /**
     * Подсказка у галочки, для которой не нашлось строки в тексте целей.
     */
    private const TASK_ITEM_NO_LINE_TITLE =
            'Этот пункт не удалось связать со строкой текста — отметить его можно только правкой целей';

    private $_strongRegex;
    private $_delRegex;
    private $_underlineRegex;
    private $_userLinkRegex;
    private $_taskLinkRegex;
    private $_useLpmCodeHighlight = false;

    /**
     * Пункты списка задач разбираемого текста по порядку (см. parseTaskList())
     * или `null`, если показывать их галочками не нужно.
     * @var array|null
     */
    private $_taskListItems = null;

    /**
     * Номер пункта в $_taskListItems, с которого ищем строку очередной галочки.
     * @var int
     */
    private $_taskListCursor = 0;

    /**
     * Глубина вложенности списка, который собирается сейчас: 0 - список
     * верхнего уровня.
     * @var int
     */
    private $_taskListDepth = 0;

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

    public function setLpmCodeHighlight($enabled)
    {
        $this->_useLpmCodeHighlight = (bool)$enabled;
        return $this;
    }

    public function text($text)
    {
        // Строки пунктов разбираемый текст разбирает по порядку, поэтому
        // у каждого разбора поиск идёт с начала
        $this->_taskListCursor = 0;
        $this->_taskListDepth = 0;

        return parent::text($text);
    }

    // Пункты списка вида `- [ ] текст` показываем галочками
    protected function blockList($Line)
    {
        return $this->markTaskItem(parent::blockList($Line), $Line);
    }

    protected function blockListContinue($Line, array $Block)
    {
        return $this->markTaskItem(parent::blockListContinue($Line, $Block), $Line);
    }

    /**
     * Отрисовывает пункт списка задач - галочку и текст пункта.
     *
     * Обработчик элемента Parsedown, назначается в markTaskItem().
     *
     * @param  array $lines Строки пункта; состояние `[ ]` / `[x]` ещё стоит
     *                      в начале первой из них.
     * @return string Разметка содержимого пункта.
     */
    protected function taskListItem($lines)
    {
        $itemText = $lines[0];
        $item = $this->takeTaskListItem($itemText);

        $checked = false;
        if (preg_match(self::TASK_ITEM_STATE_REGEX, $itemText, $matches)) {
            $checked = trim($matches[1]) !== '';
            $lines[0] = substr($itemText, strlen($matches[0]));
        }

        // Класс target-check и атрибуты data-target-line* используются JS:
        // по ним щелчок находит строку поля, которую надо заменить
        $attributes = ' class="form-check-input target-check me-1" type="checkbox"'
                . ' aria-label="Выполнено"';

        if ($checked) {
            $attributes .= ' checked';
        }

        if ($item === null) {
            $attributes .= ' disabled';
        } else {
            $attributes .= ' data-target-line="' . $item['line'] . '"'
                    . ' data-target-line-from="' . HTMLHelper::escape($item['from']) . '"'
                    . ' data-target-line-to="' . HTMLHelper::escape($item['to']) . '"';
        }

        $checkbox = '<input' . $attributes . '>';

        if ($item === null) {
            // Выключенная галочка молчит о причине, а подсказка на ней самой
            // не всплывает - события у выключенного поля отключены
            $checkbox = '<span title="' . HTMLHelper::escape(self::TASK_ITEM_NO_LINE_TITLE) . '">'
                    . $checkbox . '</span>';
        }

        return $checkbox . $this->taskListItemContent($lines);
    }

    protected function li($lines)
    {
        // Список, собранный внутри пункта, - вложенный: ему нужен отступ,
        // которого у него не будет вместе с маркерами
        $this->_taskListDepth++;
        $markup = parent::li($lines);
        $this->_taskListDepth--;

        return $markup;
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

    // Use LPM code highlight classes for fenced code opened by backticks
    protected function blockFencedCode($Line)
    {
        $Block = parent::blockFencedCode($Line);

        if (!$this->_useLpmCodeHighlight) {
            return $Block;
        }

        // Apply only for backtick fences; leave tildes as default
        if ($Block && isset($Line['text'][0]) && $Line['text'][0] === '`') {
            if (isset($Block['element'])
                && isset($Block['element']['text'])
                && is_array($Block['element']['text'])) {
                // Parsedown sets class as `language-<lang>` if present
                if (!isset($Block['element']['text']['attributes'])) {
                    $Block['element']['text']['attributes'] = array();
                }

                if (isset($Block['element']['text']['attributes']['class'])) {
                    $cls = $Block['element']['text']['attributes']['class'];
                    if (strpos($cls, 'language-') === 0) {
                        $cls = substr($cls, strlen('language-'));
                    }
                    $cls = trim($cls);
                    if ($cls === '') {
                        $cls = 'no-highlight';
                    }
                    $Block['element']['text']['attributes']['class'] = $cls;
                } else {
                    // No language specified -> use explicit no-highlight
                    $Block['element']['text']['attributes']['class'] = 'no-highlight';
                }
            }
        }

        return $Block;
    }

    protected function blockFencedCodeComplete($Block)
    {
        if ($this->_useLpmCodeHighlight
            && isset($Block['char'])
            && $Block['char'] === '`'
            && isset($Block['element']['text']['text'])) {
            // Trim leading/trailing newlines like our previous implementation
            $Block['element']['text']['text'] = trim($Block['element']['text']['text'], "\n\r");
        }

        return parent::blockFencedCodeComplete($Block);
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

    /**
     * Возвращает содержимое пункта списка задач.
     *
     * В рыхлом списке - когда пункты разделены пустой строкой или внутри
     * пункта есть блок - Parsedown заворачивает содержимое в абзац, а блочный
     * абзац перенёс бы текст под галочку. Поэтому первый абзац пункта
     * разворачивается: текст остаётся на строке галочки.
     *
     * @param  array $lines Строки пункта.
     * @return string Разметка содержимого пункта.
     */
    private function taskListItemContent($lines)
    {
        return preg_replace('/^\s*<p>(.*?)<\/p>/s', '$1', $this->li($lines), 1);
    }

    /**
     * Возвращает строку текста, которой задан отрисовываемый сейчас пункт.
     *
     * Пункты разметки идут в том же порядке, что и строки из parseTaskList(),
     * поэтому поиск идёт только вперёд: одинаковые по тексту пункты получают
     * каждый свою строку. Строка, которую разметка пунктом не показала
     * (например, попавшая в блок кода с отступом), при этом пропускается.
     *
     * @param  string $itemText Текст пункта вместе с состоянием `[ ]` / `[x]`.
     * @return array|null Пункт из parseTaskList() или `null`, если такой
     *                    строки в тексте нет.
     */
    private function takeTaskListItem($itemText)
    {
        for ($i = $this->_taskListCursor; isset($this->_taskListItems[$i]); $i++) {
            $item = $this->_taskListItems[$i];
            if (substr($item['from'], -strlen($itemText)) === $itemText) {
                $this->_taskListCursor = $i + 1;
                return $item;
            }
        }

        return null;
    }

    /**
     * Назначает пункту списка отрисовку галочкой, если его задаёт строка-пункт
     * списка задач.
     *
     * @param  array|null $Block Блок списка, собранный Parsedown.
     * @param  array      $Line  Строка, которой задан последний пункт блока.
     * @return array|null Блок списка.
     */
    private function markTaskItem($Block, $Line)
    {
        if ($this->_taskListItems === null || empty($Block) || !isset($Block['li']['handler'])) {
            return $Block;
        }

        // Строку, дописанную к предыдущему пункту, разбирает вложенный разбор
        // его содержимого - галочку ставит уже он
        if ($Block['li']['handler'] !== 'li' || count($Block['li']['text']) !== 1) {
            return $Block;
        }

        if (!preg_match(self::TASK_ITEM_LINE_REGEX, $Line['text'])) {
            return $Block;
        }

        $Block['li']['handler'] = 'taskListItem';
        // Маркер списка рядом с галочкой был бы вторым маркером, но вместе
        // с маркерами list-unstyled снимает и отступ - вложенному списку
        // его возвращаем, иначе подпункты встанут вровень с пунктами
        $Block['element']['attributes']['class'] = $this->_taskListDepth > 0
                ? 'list-unstyled ps-4'
                : 'list-unstyled';

        return $Block;
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
