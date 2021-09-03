/**
 * Страница просмотра проекта (просмотр задач, добавлени задачи)
 */

// по открытию страницы сразу убираем форму регистрации
$(document).ready(
    function () {
        states.addState($("#projectView"));
        states.addState($("#projectView"), 'only-my', issuePage.showIssues4Me);
        states.addState($("#projectView"), 'last-created', issuePage.showLastCreated);
        states.addState($("#projectView"), 'by-user:#', issuePage.showIssuesByUser);
        states.addState($("#issueForm"), 'add-issue', issueForm.handleAddState);
        states.addState($("#issueForm"), 'copy-issue:#', issueForm.handleAddIssueByState);
        states.addState($("#issueForm"), 'finished-issue:#:#', issueForm.handleAddFinishedIssueByState);

        if (window.location.hash == '#issue-view')
            window.location.hash = '';

        if ($('#issueForm > div.validateError').html() != '') {
            $('#issueForm > div.validateError').show();
        }

        issuePage.updateStat();
    }
);