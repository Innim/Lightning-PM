/**
 * Страница просмотра проекта (список задач)
 */
$(document).ready(
    function () {
        states.addState($("#projectView"), '', issuePage.sortDefault);
        states.addState($("#projectView"), 'last-created', issuePage.handleLastCreatedSort);
        states.addState($("#projectView"), 'test-priority', issuePage.handleTestPrioritySort);
        states.addState($("#projectView"), 'test-stale', issuePage.handleTestStaleSort);
        states.addState($("#projectView"), 'filter:#', issuePage.handleFilterState);

        if (window.location.hash == '#issue-view')
            window.location.hash = '';

        issuePage.applySortFromHash();
        issuePage.updateStat();
    }
);