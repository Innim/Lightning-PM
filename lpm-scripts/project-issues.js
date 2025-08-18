/**
 * Страница просмотра проекта (просмотр задач, добавления задачи)
 */

// по открытию страницы сразу убираем форму регистрации
$(document).ready(
    function () {
        states.addState($("#projectView"));
        states.addState($("#projectView"), 'only-my', issuePage.handleOnlyMeFilter);
        states.addState($("#projectView"), 'last-created', issuePage.handleLastCreatedSort);
        states.addState($("#projectView"), 'by-user:#', issuePage.handleShowIssuesByUser);
        states.addState($("#projectView"), 'tags:#', issuePage.handleTagsFilterState);
        states.addState($("#issueForm"), 'add-issue', issueForm.handleAddState);
        states.addState($("#issueForm"), 'copy-issue:#:#', issueForm.handleAddIssueByState);
        states.addState($("#issueForm"), 'finished-issue:#:#', issueForm.handleAddFinishedIssueByState);

        if (window.location.hash == '#issue-view')
            window.location.hash = '';

        if ($('#issueForm > div.validateError').html() != '') {
            $('#issueForm > div.validateError').show();
        }

        issuePage.updateStat();
    }
);