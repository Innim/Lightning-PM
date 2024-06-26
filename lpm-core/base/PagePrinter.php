<?php
class PagePrinter
{
    public static function title()
    {
        echo self::getPC()->getTitle();
    }
    
    public static function header()
    {
        echo self::getPC()->getHeader();
    }
    
    public static function siteTitle()
    {
        echo LPMOptions::getInstance()->title;
    }
    
    public static function siteSubTitle()
    {
        echo LPMOptions::getInstance()->subtitle;
    }
    
    public static function logoImg()
    {
        if (LPMOptions::getInstance()->logo != '') {
            echo '<img src="' . LPMOptions::getInstance()->logo . '" ' .
                      'title="' . LPMOptions::getInstance()->title .'" ' .
                      'alt="' . LPMOptions::getInstance()->title .'"/>';
        }
    }
    
    public static function version()
    {
        echo VERSION;
    }
    
    public static function copyrights()
    {
        echo '<a href="' . LPMBase::AUTHOR_SITE . '" target="_blank">' . LPMBase::AUTHOR . '</a> &copy; 20' . COPY_YEAR;
        $nowYear = DateTimeUtils::date(DateTimeFormat::YEAR_NUMBER_2_DIGITS);
        if ($nowYear > COPY_YEAR) {
            echo '-' . $nowYear;
        }
    }
    
    public static function productName()
    {
        echo LPMBase::PRODUCT_NAME;
    }
    
    public static function cssLinks()
    {
        self::cssLink('jquery-ui-1.12.1.min');
        self::cssLink('highlightjs-styles/default');
        self::cssLink('font-awesome5/css/fontawesome-all.min');
        self::cssLink('tribute');
        self::cssLink('bootstrap.min');
        self::cssLink('bootstrap-reset');
        self::cssLink('vue-multiselect.min');
        self::cssLink('main');
    }
    
    public static function errors()
    {
        echo implode(', ', LightningEngine::getInstance()->getErrors());
    }
    
    public static function issues($list)
    {
        PageConstructor::includePattern('issues', compact('list'));
    }
    
    public static function issueForm($project, $issue, $input, $isHidden)
    {
        PageConstructor::includePattern('issue-form', compact('project', 'issue', 'input', 'isHidden'));
    }
    
    public static function issueView()
    {
        PageConstructor::includePattern('issue');
    }
    
    public static function projectsList($list, $isArchive = false)
    {
        PageConstructor::includePattern('projects-list', compact('list', 'isArchive'));
    }
    
    public static function usersList()
    {
        PageConstructor::includePattern('users-list');
    }
    
    public static function usersChooser()
    {
        PageConstructor::includePattern('users-chooser');
    }
    
    public static function comment(Comment $comment, $showIssueLink = false)
    {
        PageConstructor::includePattern('comment', compact('comment', 'showIssueLink'));
    }
    
    /**
     * Распечатывает текст комментария.
     * @param string $htmlText Форматированный текст для отображения.
     */
    public static function commentText($htmlText)
    {
        PageConstructor::includePattern('comment-text', compact('htmlText'));
    }
    
    /**
     * Распечатывает поле ввода текста комментария.
     * @param string $id Идентификатор html элемента.
     */
    public static function commentInputText($id)
    {
        PageConstructor::includePattern('comment-input-text', compact('id'));
    }
    
    /**
     * Распечатывает список видео.
     * @param  array $videoLinks Список объектов с данными ссылок на видео.
     */
    public static function videoList($videoLinks)
    {
        PageConstructor::includePattern('entity-video-list', compact('videoLinks'));
    }
    
    /**
     * Распечатывает вывод видео.
     * @param  array $video Объект с данными ссылок на видео.
     */
    public static function videoItem($video)
    {
        PageConstructor::includePattern('entity-video-item', compact('video'));
    }
    
    /**
     * Распечатывает список прикрепленных изображений.
     * @param  array $videoLinks Список объектов с данными ссылок на видео.
     */
    public static function imageList($imageLinks)
    {
        PageConstructor::includePattern('entity-image-list', compact('imageLinks'));
    }
    
    /**
     * Распечатывает вывод конкретного прикрепленного изображения.
     * @param  array $image Объект с данными ссылки изображения.
     */
    public static function imageItem($image)
    {
        PageConstructor::includePattern('entity-image-item', compact('image'));
    }

    /**
     * Распечатывает форму экспорта задач в Excel.
     */
    public static function issuesExportToExcel()
    {
        PageConstructor::includePattern('issues-export-to-excel');
    }

    /**
     * Распечатывает таблицу Scrum доски.
     * @param $stickers
     * @param bool $addProjectName
     * @param bool $addClearBoard
     */
    public static function tableScrumBoard($stickers, $addProjectName = false, $addClearBoard = false)
    {
        PageConstructor::includePattern('scrum-board-table', compact('stickers', 'addProjectName', 'addClearBoard'));
    }
    
    /**
     * Распечатывает шаблон целей спринта.
     */
    public static function sprintTargetForm($project)
    {
        PageConstructor::includePattern('scrum-board-target-sprint', compact('project'));
    }

    /**
     * Распечатывает шаблон фильтра Scrum-доски.
     */
    public static function scrumBoardFilter()
    {
        PageConstructor::includePattern('scrum-board-filter');
    }

    /**
     * Распечатывает шаблон фильтра в списке задач.
     */
    public static function issueListFilter()
    {
        PageConstructor::includePattern('issue-list-filter');
    }
    

    /**
     * Распечатывает элемент исполнителя задачи в стикере на Scrum доске.
     * @param $member
     */
    public static function tableScrumBoardIssueMember(User $member)
    {
        PageConstructor::includePattern('scrum-board-table-issue-member', compact('member'));
    }

    /*public static function mainCSSLink() {
        self::cssLink( 'main' );
    }*/
    
    public static function jsScripts()
    {
        $scripts = PageConstructor::getUsingScripts();
        foreach ($scripts as $scriptFileName) {
            self::jsScriptLink($scriptFileName);
        }
    }

    public static function jsModuleScripts()
    {
        $modules = PageConstructor::getUsingJSModules();
        foreach ($modules as $moduleFileName) {
            self::jsScriptModule($moduleFileName);
        }
    }

    /**
     * Возвращает JS строку, представляющую объект.
     */
    public static function toJSObject($data)
    {
        $str = addcslashes(json_encode($data), '"\\');
        return 'JSON.parse("' . $str . '")';
    }

    /**
     * Распечатывает JS скрипт с назначением объекта
     * в указанную JS переменную.
     */
    public static function printJSObject($name, $data, $addScriptTags = true, $defineLet = true)
    {
        $right = $defineLet ? 'let ' . $name : $name;
        $left = self::toJSObject($data);
        if ($addScriptTags) {
            echo '<script>';
        }

        echo <<<JS
    $right = $left;
JS;
        if ($addScriptTags) {
            echo '</script>';
        }
    }
    
    public static function openGraphMeta()
    {
        $data = self::getPC()->getOpenGraph();
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                self::openGraph($key, $value);
            }
        }
    }
    
    public static function pageContent()
    {
        LightningEngine::getInstance()->getCurrentPage()->printContent();
    }
    
    public static function postVar($var, $default = '')
    {
        echo isset($_POST[$var]) ? $_POST[$var] : $default;
    }
    
    public static function jsRedirect($url)
    {
        echo '<script type="text/javascript">redirectTo("' . $url . '");</script>';
    }
    
    private static function jsScriptLink($file)
    {
        echo '<script type="text/javascript" src="' .
             self::getPC()->getJSLink($file) .
             '"></script>';
    }

    private static function jsScriptModule($file)
    {
        echo '<script type="module" src="' .
            self::getPC()->getJSLink($file) .
            '"></script>';
    }
    
    private static function cssLink($file)
    {
        echo '<link rel="stylesheet" href="' .
             self::getPC()->getCSSLink($file) .
             '" type="text/css">';
    }
    
    private static function openGraph($property, $content)
    {
        echo '<meta property="og:' . $property . '" content="' . str_replace('"', '', $content) . '" />';
    }
    
    /**
     * @return PageConstructor
     */
    private static function getPC()
    {
        return LightningEngine::getInstance()->getConstructor();
    }
}
