<?php
/**
 * Псевдонимы методов классов PagePrinter и PageConstructor,
 * для использования в шаблонах
 */

/**
 * Экранирует текст для безопасной вставки в HTML.
 * @param  string $text Текст в исходном виде.
 * @return string Текст, безопасный для вывода в HTML.
 */
function lpm_escape($text)
{
    return HTMLHelper::escape($text);
}

/**
 * Распечатывает title страницы
 */
function lpm_print_title()
{
    PagePrinter::title();
}

/**
* Распечатывает заголовок сайта
*/
function lpm_print_site_title()
{
    PagePrinter::siteTitle();
}

/**
* Распечатывает подзаголовок сайта
*/
function lpm_print_site_subtitle()
{
    PagePrinter::siteSubTitle();
}

/**
 * Распечатывает img логотип сайта
 */
function lpm_print_logo_img()
{
    PagePrinter::logoImg();
}

/**
 * Распечатывает версию
 */
function lpm_print_version()
{
    PagePrinter::version();
}

/**
 * Возвращает URL страницы изменений
 */
function lpm_get_changelog_url()
{
    return PageConstructor::getChangelogUrl();
}

/**
 * Распечатывает копирайты
 */
function lpm_print_copyrights()
{
    PagePrinter::copyrights();
}

/**
 * Распечатывает название продукта
 */
function lpm_print_product_name()
{
    PagePrinter::productName();
}

/**
 * Распечатывает ссылку на репозиторий проекта на GitHub
 */
function lpm_print_github_link()
{
    PagePrinter::githubLink();
}

/**
 * Распечатывает основной стиль
 */
function lpm_print_css_links()
{
    PagePrinter::cssLinks();
}

/**
 * Распечатывает inline-скрипт с глобальными настройками клиента (`window.lpmOptions`).
 */
function lpm_print_js_options()
{
    PagePrinter::jsOptions();
}

/**
 * Распечатывает ссылки на js файлы
 */
function lpm_print_scripts()
{
    PagePrinter::jsScripts();
}

/**
 * Распечатывает ссылки на js файлы модулей.
 */
function lpm_print_script_module()
{
    PagePrinter::jsModuleScripts();
}

/**
 * Распечатывает Open Graph мету.
 */
function lpm_print_open_graph_meta()
{
    PagePrinter::openGraphMeta();
}

/**
 * Выводит список пользователей
 */
function lpm_print_users_list()
{
    return PagePrinter::usersList();
}


/**
 * Распечатывает заголовок страницы
 */
function lpm_print_header()
{
    PagePrinter::header();
}

/**
 * Распечатывает текущие ошибки
 */
function lpm_print_errors()
{
    PagePrinter::errors();
}

/**
 * Распечатывает основной контент страницы
 */
function lpm_print_page_content()
{
    PagePrinter::pageContent();
}

/**
* Распечатывает задачи
*/
function lpm_print_issues($list)
{
    return PagePrinter::issues($list);
}

/**
* Распечатывает форму добавления/редактирования задачи для текущего проекта
*/
function lpm_print_issue_form($project, $issue = null, $input = null, $isHidden = null)
{
    return PagePrinter::issueForm($project, $issue, $input, $isHidden);
}

/**
* Распечатывает отображение отдельного комментария.
*/
function lpm_print_comment(Comment $comment, $showIssueLink = false)
{
    return PagePrinter::comment($comment, $showIssueLink);
}

/**
 * Распечатывает текст комментария.
 * @param string $htmlText Форматированный текст для отображения.
 */
function lpm_print_comment_text($htmlText)
{
    return PagePrinter::commentText($htmlText);
}

/**
 * Распечатывает поле ввода текста комментария.
 * @param string $id Идентификатор html элемента.
 */
function lpm_print_comment_input_text($id)
{
    return PagePrinter::commentInputText($id);
}

function lpm_print_comment_files(Comment $comment)
{
    return PagePrinter::commentFiles($comment);
}

/**
* Распечатывает задачу
*/
function lpm_print_issue_view()
{
    return PagePrinter::issueView();
}

/**
 * Распечатывает список проектов
 */
function lpm_print_projects_list($list, $isArchive = false)
{
    return PagePrinter::projectsList($list, $isArchive);
}

/**
 * Возвращает JS строку, представляющую объект.
 */
function lpm_get_js_object($data)
{
    return PagePrinter::toJSObject($data);
}

/**
 * Распечатывает JS скрипт с назначением объекта
 * в указанную JS переменную.
 */
function lpm_print_js_object($name, $data, $addScriptTags = true, $defineLet = true)
{
    return PagePrinter::printJSObject($name, $data, $addScriptTags, $defineLet);
}

/**
 * Распечатывает переменную из параметров POST.
 * Если переменной нет - то пустую строку
 */
function lpm_print_post_var($var, $default = '')
{
    return PagePrinter::postVar($var, $default);
}

/**
 * Распечатывает форму выбора пользователей.
 */
function lpm_print_users_chooser()
{
    return PagePrinter::usersChooser();
}

/**
 * Распечатывает блок состояния миграций схемы БД.
 */
function lpm_print_db_migrations()
{
    return PagePrinter::dbMigrations();
}

/**
 * Распечатывает список видео.
 */
function lpm_print_video_list($videoLinks)
{
    return PagePrinter::videoList($videoLinks);
}

/**
 * Распечатывает вывод конкретного видео.
 */
function lpm_print_video_item($video)
{
    return PagePrinter::videoItem($video);
}

/**
 * Распечатывает список прикрепленных изображений.
 */
function lpm_print_image_list($imageLinks)
{
    return PagePrinter::imageList($imageLinks);
}

/**
 * Распечатывает вывод конкретного прикрепленного изображения.
 */
function lpm_print_image_item($image)
{
    return PagePrinter::imageItem($image);
}

/**
 * Распечатывает форму экспорта задач в Excel.
 */
function lpm_print_issues_export_to_excel()
{
    return PagePrinter::issuesExportToExcel();
}

/**
 * Печатает компонент быстрого перехода к задаче по номеру в проекте.
 */
function lpm_print_goto_issue($project)
{
    return PagePrinter::gotoIssue($project);
}

/**
 * Печатает скрытое поле с токеном страницы для формы.
 */
function lpm_print_csrf_field()
{
    return PagePrinter::csrfField();
}

/**
 * Печатает меню выбора сортировки списка задач.
 */
function lpm_print_issues_sort($list)
{
    return PagePrinter::issuesSort($list);
}

/**
 * Печатает бейдж с давностью последней активности по задаче в тесте.
 */
function lpm_print_issue_test_age($issue)
{
    return PagePrinter::issueTestAge($issue);
}

/**
 * Печатает блок связанных задач для страницы задачи.
 */
function lpm_print_issue_linked($issue)
{
    return PagePrinter::issueLinked($issue);
}

/**
 * Печатает участника задачи: аватар и ссылку на его страницу.
 */
function lpm_print_issue_user($user, $withSp = false)
{
    return PagePrinter::issueUser($user, $withSp);
}

/**
 * Печатает группу участников задачи (исполнители, тестеры, мастеры).
 */
function lpm_print_issue_participants($issue, $userId, $role, $label)
{
    return PagePrinter::issueParticipants($issue, $userId, $role, $label);
}

/**
 * Печатает ссылку быстрого добавления текущего пользователя
 * к участникам задачи в указанной роли (member|tester|master).
 */
function lpm_print_issue_add_me($issue, $userId, $role)
{
    return PagePrinter::issueAddMe($issue, $userId, $role);
}

/**
 * Печатает блок ИИ-сводки обсуждения задачи.
 */
function lpm_print_ai_issue_summary($issue, $summary, $sourceHash, array $comments)
{
    return PagePrinter::aiIssueSummary($issue, $summary, $sourceHash, $comments);
}

/**
 * Печатает ссылку запуска чек-листа тестирования.
 */
function lpm_print_ai_test_checklist_link($published)
{
    return PagePrinter::aiTestChecklistLink($published);
}

/**
 * Печатает разметку диалога чек-листа тестирования.
 */
function lpm_print_ai_test_checklist_dialog()
{
    return PagePrinter::aiTestChecklistDialog();
}

/**
 * Печатает кнопку копирования значения в буфер обмена.
 */
function lpm_print_copy_button($value, $toast = 'Скопировано')
{
    return PagePrinter::copyButton($value, $toast);
}

/**
 * Распечатывает вывод таблицы Scrum доски.
 * @param $stickers
 * @param bool $addProjectName
 * @param bool $addClearBoard
 * @param array $freeIssueIds Множество `issueId => true` задач, которые показываются
 *                            свободными; пусто там, где свободных задач не бывает.
 */
function lpm_print_table_scrum_board(
    $stickers,
    $addProjectName = false,
    $addClearBoard = false,
    $freeIssueIds = []
) {
    return PagePrinter::tableScrumBoard($stickers, $addProjectName, $addClearBoard, $freeIssueIds);
}


/**
 * Распечатывает элемент исполнителя задачи в стикере на Scrum доске.
 * @param $member
 */
function lpm_print_table_scrum_board_issue_member(User $member)
{
    return PagePrinter::tableScrumBoardIssueMember($member);
}



/**
 * Распечатывает форму добавления/редактирования цели спринта для текущего проекта.
 * @param $project
 */
function lpm_print_sprint_target_form($project)
{
    return PagePrinter::sprintTargetForm($project);
}

/**
 * Выводит шаблон компонента фильтров Scrum доски.
 */
function lpm_print_scrum_board_filter()
{
    return PagePrinter::scrumBoardFilter();
}

/**
 * Выводит шаблон списка снимков Scrum доски.
 */
function lpm_print_scrum_board_snapshots_list(Project $project, $snapshots)
{
    return PagePrinter::scrumBoardSnapshotsList($project, $snapshots);
}

/**
 * Выводит шаблон компонента фильтров списка задач.
 */
function lpm_print_issue_list_filters($elementId = 'issueListFilter')
{
    return PagePrinter::issueListFilter($elementId);
}

/**
*   Возвращает текущую страницу
*/
function lpm_get_current_page()
{
    return PageConstructor::getCurrentPage();
}

/**
 * Возвращает url приложения
 * @return string
 */
function lpm_get_site_url()
{
    return PageConstructor::getSiteURL();
}

/**
 * Возвращает url базовой текущей страницы
 * @return string
 */
function lpm_get_base_page_url()
{
    return PageConstructor::getBasePageURL();
}

/**
 * Возвращает массив ссылок для главного меню
 * @return array
 */
function lpm_get_main_menu()
{
    return PageConstructor::getMainMenu();
}

/**
 * Возвращает массив ссылок для подменю страницы
 * @return array
 */
function lpm_get_sub_menu()
{
    return PageConstructor::getSubMenu();
}

/**
 * Возвращает массив ссылок для меню пользователя
 * @return array
 */
function lpm_get_user_menu()
{
    return PageConstructor::getUserMenu();
}

/**
 * Возвращает список задач для текущего проекта
 */
function lpm_get_issues_list()
{
    return PageConstructor::getIssuesList();
}

/**
 * Возвращает текущий проект
 */
function lpm_get_project()
{
    return PageConstructor::getProject();
}

/**
 * Возвращает список участников проекта
 */
function lpm_get_project_members()
{
    return PageConstructor::getProjectMembers();
}

/**
 * Возвращает список меток для задачи.
 */
function lpm_get_issue_labels()
{
    return PageConstructor::getIssueLabels();
}

/**
 * Возвращает имена меток задач.
 */
function lpm_get_issue_labels_names()
{
    $labels = PageConstructor::getIssueLabels();
    return array_values(array_map(function ($item) {
        return $item['label'];
    }, $labels));
}

/**
 * Готовит данные к выводу в HTML-атрибут как JSON (`:options='…'` и подобные).
 *
 * Порядок обязателен: сначала `json_encode` сырых данных, потом экранирование
 * готовой строки целиком. При обратном порядке `json_encode` получает `&quot;`
 * вместо кавычки, не экранирует её, а браузер при разборе атрибута
 * возвращает голую кавычку и ломает JSON.
 *
 * Атрибут берётся в одинарные кавычки, поэтому апостроф в данных тоже
 * должен быть экранирован — {@see HTMLHelper::escape()} закрывает обе кавычки.
 *
 * @param  mixed $data Данные для JSON.
 * @return string Значение, готовое к подстановке внутрь атрибута.
 */
function lpm_json_attr($data)
{
    return HTMLHelper::escape(json_encode($data));
}

/**
 * Возвращает участников проекта для JS фильтра, сгруппированных по ролям.
 *
 * Группы: «Исполнители» — все участники проекта, «Тестировщики» — только те
 * из них, кто назначен тестировщиком хотя бы на одну задачу проекта.
 * Пустые группы не возвращаются, чтобы в списке не было заголовка без людей.
 * Один и тот же человек в разных группах — это разные опции мультиселекта,
 * поэтому `key` включает роль: `track-by` требует уникальности по всем группам.
 *
 * Имена возвращаются в исходном виде, без экранирования: список уходит в
 * HTML-атрибут через {@see lpm_json_attr()}, который экранирует уже готовый
 * JSON. Экранировать имя заранее нельзя — `json_encode` не распознает в
 * `&quot;` кавычку и не экранирует её, а браузер вернёт её в JSON голой.
 *
 * @return array<array> Группы вида
 *                      `['groupLabel' => string, 'users' => array<array>]`.
 */
function lpm_get_project_members_for_filter()
{
    $users = PageConstructor::getProjectMembers();
    $project = PageConstructor::getProject();
    $testerIds = empty($project)
            ? []
            : array_flip(Member::loadIssueTesterIdsForProject($project->getID()));

    $members = [];
    $testers = [];
    foreach ($users as $user) {
        $userId = $user->getID();
        $name = $user->getPlainName();

        $members[] = [
            'key' => 'm' . $userId,
            'userId' => $userId,
            'role' => 'member',
            'name' => $name,
        ];

        if (isset($testerIds[$userId])) {
            $testers[] = [
                'key' => 't' . $userId,
                'userId' => $userId,
                'role' => 'tester',
                'name' => $name,
            ];
        }
    }

    $groups = [];
    if (!empty($members)) {
        $groups[] = ['groupLabel' => 'Исполнители', 'users' => $members];
    }
    if (!empty($testers)) {
        $groups[] = ['groupLabel' => 'Тестировщики', 'users' => $testers];
    }

    return $groups;
}

/**
 * Возвращает список пользователей
 */
function lpm_get_users_list()
{
    return PageConstructor::getUsersList();
}
/**
 * Возвращает список пользователей
 */
function lpm_get_user_issues()
{
    return PageConstructor::getUserIssues();
}
/**
 * Возвращает список пользователей для выбора
 */
function lpm_get_users_choose_list()
{
    return PageConstructor::getUsersChooseList();
}
/**
 * Возвращает текущего пользователя
 */
function lpm_get_user()
{
    return PageConstructor::getUser();
}
/**
 * Определяет, может ли текущий пользователь создавать проекты
 */
function lpm_can_create_project()
{
    return PageConstructor::canCreateProject();
}
/**
 * Определяет, является ли пользователь модератором
 */
function lpm_is_moderator()
{
    return PageConstructor::isModerator();
}

/**
 * Определяет, является ли текущий пользователь администратором
 */
function lpm_is_admin()
{
    return PageConstructor::isAdmin();
}

/**
 * Определяет, авторизован ли в данный момент пользователь
 */
function lpm_is_auth()
{
    return PageConstructor::isAuth();
}

/**
 * Возвращает текущие ошибки и очищает список
 */
function lpm_get_errors()
{
    return PageConstructor::getErrors();
}
/**
 * Проверяет кто удаляет комментарий.
 */
function lpm_check_delete_comment($authorId, $commentId)
{
    return PageConstructor::checkDeleteComment($authorId, $commentId);
}

/**
 * Возвращает время выполнения в секундах.
 * @return float
 */
function lpm_get_execution_time()
{
    return LightningEngine::getInstance()->getExecutionTimeSec();
}
