/**
 * Страница просмотра проекта (просмотр задач, добавлени задачи)
 */

// по открытию страницы сразу убираем форму регистрации
$(document).ready(
  function () {
      states.addState( $("#issueForm"  ), 'add-issue');
    states.addState($("#projectView"));
    states.addState($("#projectView"), 'only-my', issuePage.showIssues4Me);
    states.addState($("#projectView"), 'last-created', issuePage.showLastCreated);
    states.addState($("#projectView"), 'by-user:#', issuePage.showIssuesByUser);
    states.addState($("#issueForm"  ), 'copy-issue:#', issuePage.addIssueBy);
    states.addState($("#issueForm"  ), 'finished-issue:#', issuePage.finishedIssueBy);

    if (window.location.hash == '#issue-view')
      window.location.hash = '';

    states.updateView();
    if ($('#issueForm > div.validateError' ).html() != '') {
      $('#issueForm > div.validateError' ).show();
    } 

    issuePage.updateStat();
  }
);