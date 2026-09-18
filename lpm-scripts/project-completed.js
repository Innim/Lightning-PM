/**
 * Страница просмотра проекта (просмотр завершенных задач)
 */
$(document).ready(
    function () {
        states.addState($("#projectView"), '', issuePage.sortDefault);
        states.addState($("#projectView"), 'last-created', issuePage.handleLastCreatedSort);

        issuePage.applySortFromHash();
        issuePage.updateStat();
    }
);