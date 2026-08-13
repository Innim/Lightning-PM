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

    public static function githubLink()
    {
        echo '<a href="' . LPMBase::GITHUB_URL . '" target="_blank" rel="noopener" ' .
                  'class="text-muted" title="Исходный код на GitHub" aria-label="GitHub">' .
                  '<i class="fa-brands fa-github"></i></a>';
    }
    
    public static function cssLinks()
    {
        self::cssLink('highlightjs-styles/default');
        self::cssLink('font-awesome7/css/all.min');
        self::cssLink('tribute');
        self::cssLink('bootstrap.min');
        self::cssLink('bootstrap-reset');
        self::cssLink('vue-multiselect.min');
        self::cssLink('vanilla-calendar');
        self::cssLink('main');
    }
    
    public static function errors()
    {
        // Текст ошибки может содержать пользовательские данные (например,
        // имя загружаемого файла), поэтому разметкой остаётся только разделитель.
        $errors = array_map(function ($error) {
            return htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        }, LightningEngine::getInstance()->getErrors());

        echo implode('<br>', $errors);
    }
    
    public static function issues($list)
    {
        PageConstructor::includePattern('issues', compact('list'));
    }

    /**
     * Печатает меню выбора сортировки списка задач.
     *
     * Пункты для задач в тесте печатаются, только если такие задачи
     * в списке есть.
     * @param array<Issue> $list Список задач, который будет выведен на странице.
     */
    public static function issuesSort($list)
    {
        $items = [
            ['sort' => '', 'label' => 'По умолчанию'],
            ['sort' => 'last-created', 'label' => 'Последние добавленные'],
        ];

        foreach ($list as $issue) {
            if ($issue->isTesting()) {
                $items[] = ['header' => 'Задачи в тесте'];
                $items[] = ['sort' => 'test-priority', 'label' => 'По приоритету'];
                $items[] = ['sort' => 'test-stale', 'label' => 'Давно без активности'];
                break;
            }
        }

        PageConstructor::includePattern('components/issues-sort', compact('items'));
    }
    
    public static function issueForm($project, $issue, $input, $isHidden)
    {
        PageConstructor::includePattern('issue-form', compact('project', 'issue', 'input', 'isHidden'));
    }
    
    public static function issueView()
    {
        PageConstructor::includePattern('issue');
    }

    /**
     * Печатает бейдж с давностью последней активности по задаче в тесте.
     *
     * Ничего не печатает для задач не в тесте и для тех, по которым
     * активность была недавно.
     * @param Issue $issue Задача.
     */
    public static function issueTestAge(Issue $issue)
    {
        $days = $issue->daysWithoutTestActivity();
        if ($days < ISSUE_TEST_AGE_MIN_DAYS) {
            return;
        }

        $isBug = $issue->isChangesRequested;
        $classes = $days >= ISSUE_TEST_STALE_DAYS ? 'bg-secondary text-white' : 'bg-light text-muted';
        $label = ($isBug ? 'баг ' : 'без активности ') . $days . ' дн';
        $hint = ($isBug ? 'Последний баг: ' : 'Последняя активность: ') . $issue->getTestActivityDate();

        PageConstructor::includePattern(
            'components/issue-test-age',
            compact('issue', 'days', 'classes', 'label', 'hint')
        );
    }

    /**
     * Печатает блок связанных задач для страницы задачи.
     * @param Issue $issue Задача, для которой выводятся связи.
     */
    public static function issueLinked(Issue $issue)
    {
        $linkedIssues = $issue->getLinkedIssues();
        PageConstructor::includePattern('components/issue-linked', compact('issue', 'linkedIssues'));
    }

    /**
     * Печатает блок ИИ-сводки обсуждения задачи.
     * @param Issue $issue Задача.
     * @param IssueSummary $summary Сохранённая сводка или null, если её ещё нет.
     * @param string $sourceHash Слепок текущего состояния задачи.
     * @param int $commentsCount Количество содержательных комментариев.
     */
    public static function aiIssueSummary(Issue $issue, $summary, $sourceHash, $commentsCount)
    {
        $isActual = !empty($summary) && $summary->isActualFor($sourceHash);
        PageConstructor::includePattern(
            'components/ai-issue-summary',
            compact('issue', 'summary', 'commentsCount', 'isActual')
        );
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

    public static function commentFiles(Comment $comment)
    {
        PageConstructor::includePattern('comment-files', compact('comment'));
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
     * Распечатывает компактный компонент перехода к задаче по номеру в проекте.
     * @param Project $project
     */
    public static function gotoIssue(Project $project)
    {
        PageConstructor::includePattern('components/goto-issue', compact('project'));
    }

    /**
     * Распечатывает кнопку копирования значения в буфер обмена.
     * @param string $value Значение, копируемое в буфер обмена.
     * @param string $toast Текст уведомления, показываемого после копирования.
     */
    public static function copyButton($value, $toast = 'Скопировано')
    {
        PageConstructor::includePattern('components/copy-button', compact('value', 'toast'));
    }

    /**
     * Распечатывает ссылку быстрого добавления текущего пользователя
     * к участникам задачи в указанной роли.
     *
     * Ссылка печатается всегда, но скрывается, если пользователь уже назначен в этой роли.
     *
     * @param Issue $issue Задача.
     * @param int $userId Идентификатор текущего пользователя.
     * @param string $role Роль: `member` - исполнитель, `tester` - тестировщик,
     * `master` - мастер.
     */
    public static function issueAddMe(Issue $issue, $userId, $role)
    {
        // Иконки ролей сами по себе читаются как обозначение, поэтому дополняются
        // значком плюса. Исключение - «лапка» исполнителя: она уже означает действие
        // «взять задачу себе» (тот же жест используется на Scrum доске)
        $withPlus = true;

        switch ($role) {
            case 'tester':
                $icon = 'fa-solid fa-flask';
                $title = 'Добавить себя в тестировщики';
                $hidden = $issue->isTester($userId);
                break;
            case 'master':
                $icon = 'fa-solid fa-user-tie';
                $title = 'Добавить себя в мастеры';
                $hidden = $issue->isMaster($userId);
                break;
            case 'member':
            default:
                $role = 'member';
                $icon = 'fa-regular fa-hand-paper';
                $title = 'Добавить себя в исполнители';
                $hidden = $issue->isMember($userId);
                $withPlus = false;
                break;
        }

        PageConstructor::includePattern(
            'components/issue-add-me',
            compact('role', 'icon', 'title', 'hidden', 'withPlus')
        );
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
     * Распечатывает шаблон списка снимков Scrum доски.
     */
    public static function scrumBoardSnapshotsList(Project $project, $snapshots)
    {
        PageConstructor::includePattern('scrum-board-snapshots-list', compact('project', 'snapshots'));
    }

    /**
     * Распечатывает шаблон фильтра в списке задач.
     */
    public static function issueListFilter($elementId = 'issueListFilter')
    {
        PageConstructor::includePattern('issue-list-filter', compact('elementId'));
    }
    
    /**
     * Распечатывает элемент исполнителя задачи в стикере на Scrum доске.
     * @param $member
     */
    public static function tableScrumBoardIssueMember(User $member)
    {
        PageConstructor::includePattern('scrum-board-table-issue-member', compact('member'));
    }

    /**
     * Распечатывает содержимое диалога блокировки задачи.
     * @param User $user Пользователь, который заблокировал задачу.
     * @param UserLock $lock Информация о блокировке задачи.
     */
    public static function dialogContentIssueBlocked(User $user, UserLock $lock)
    {
        PageConstructor::includePattern('dialog-content-issue-blocked', compact('user', 'lock'));
    }

    /*public static function mainCSSLink() {
        self::cssLink( 'main' );
    }*/
    
    /**
     * Распечатывает inline-скрипт с глобальными настройками клиента
     * (`window.lpmOptions`), которые читают JS-скрипты приложения.
     * Печатать нужно до подключения скриптов приложения.
     */
    public static function jsOptions()
    {
        $data = [
            'url' => SITE_URL,
            'themeUrl' => self::getPC()->getThemeUrl(),
            'issueImgsCount' => Issue::MAX_IMAGES_COUNT,
            'issueFilesCount' => Issue::MAX_FILES_COUNT,
            'issueFileMaxSizeMb' => MAX_FILE_SIZE_MB,
            'gitlabUrl' => defined('GITLAB_URL') ? GITLAB_URL : '',
            'videoUrlPatterns' => AttachmentVideoHelper::URL_PATTERNS,
            'imageUrlPatterns' => AttachmentImageHelper::URL_PATTERNS,
            'issueUrlPattern' => OwnUrlHelper::getIssueUrlPattern(),
            'aiRequestTimeout' => AiIntegration::getRequestTimeout(),
            'roles' => [
                'user' => User::ROLE_USER,
                'admin' => User::ROLE_ADMIN,
                'moderator' => User::ROLE_MODERATOR,
            ],
            'issueStatuses' => [
                'inWork' => Issue::STATUS_IN_WORK,
                'test' => Issue::STATUS_WAIT,
                'completed' => Issue::STATUS_COMPLETED,
            ],
        ];
        self::printJSObject('window.lpmOptions', $data, true, false);
    }

    public static function jsScripts()
    {
        $scripts = array_unique(PageConstructor::getUsingScripts());
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
