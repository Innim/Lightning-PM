/**
 * Страница просмотра проекта (просмотр завершенных задач)
 */
$(document).ready(
    function () {
        states.addState($("#projectView"));
        states.addState($("#issueForm"), 'add-issue', issuePage.onShowAddIssue);

        states.updateView();
        issuePage.updateStat();
    }
);