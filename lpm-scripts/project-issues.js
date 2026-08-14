/**
 * Страница просмотра проекта (просмотр задач, добавления задачи)
 */

// по открытию страницы сразу убираем форму регистрации
$(document).ready(
    function () {
        states.addState($("#projectView"), '', issuePage.sortDefault);
        states.addState($("#projectView"), 'last-created', issuePage.handleLastCreatedSort);
        states.addState($("#projectView"), 'test-priority', issuePage.handleTestPrioritySort);
        states.addState($("#projectView"), 'test-stale', issuePage.handleTestStaleSort);
        states.addState($("#projectView"), 'filter:#', issuePage.handleFilterState);
        states.addState($("#issueForm"), 'add-issue', issueForm.handleAddState);
        states.addState($("#issueForm"), 'copy-issue:#:#', issueForm.handleAddIssueByState);
        states.addState($("#issueForm"), 'finished-issue:#:#', issueForm.handleAddFinishedIssueByState);

        if (window.location.hash == '#issue-view')
            window.location.hash = '';

        if ($('#issueForm > div.validateError').html() != '') {
            $('#issueForm > div.validateError').show();
        }

        issuePage.applySortFromHash();
        issuePage.updateStat();
    }
);