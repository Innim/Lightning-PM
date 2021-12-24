<?php
/**
 * Псевдонимы методов классов PagePrinter и PageConstructor,
 * для использования в шаблонах
 */

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
 * Распечатывает основной стиль
 */
function lpm_print_css_links()
{
    PagePrinter::cssLinks();
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
function lpm_print_issue_form($project, $issue = null, $input = null)
{
    return PagePrinter::issueForm($project, $issue, $input);
}

/**
* Распечатывает отображение отдельного комментария.
*/
function lpm_print_comment(Comment $comment)
{
    return PagePrinter::comment($comment);
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
 * Распечатывает вывод таблицы Scrum доски.
 * @param $stickers
 * @param bool $addProjectName
 * @param bool $addClearBoard
 */
function lpm_print_table_scrum_board($stickers, $addProjectName = false, $addClearBoard = false)
{
    return PagePrinter::tableScrumBoard($stickers, $addProjectName, $addClearBoard);
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
function lpm_print_scrum_board_filters()
{
    return PagePrinter::sprintScrumBoardFilters();
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
 * Возвращает тестера проекта
 */
function lpm_get_project_tester()
{
    return PageConstructor::getProjectTester();
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
