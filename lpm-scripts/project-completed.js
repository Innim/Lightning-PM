/**
 * Страница просмотра проекта (просмотр завершенных задач)
 */
$(document).ready(
    function () {
        states.addState($("#projectView"));
        states.addState($("#issueForm"), 'add-issue', issueForm.handleAddState);

        states.updateView();
        issuePage.updateStat();
    }
);