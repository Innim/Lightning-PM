/**
 * Страница просмотра проекта (просмотр завершенных задач)
 */
$(document).ready(
    function () {
        states.addState( $("#issueForm"  ), 'add-issue');
        states.addState($("#projectView"));

        states.updateView();
        issuePage.updateStat();
    }
);