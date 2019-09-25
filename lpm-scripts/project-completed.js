/**
 * Страница просмотра проекта (просмотр завершенных задач)
 */

$(document).ready(
    function () {
        states.addState( $("#projectView") );
        states.addState( $("#issueForm"  ), 'add-issue');

        states.updateView();
        issuePage.updateStat();
    }
);