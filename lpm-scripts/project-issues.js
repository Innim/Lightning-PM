/**
 * Страница просмотра проекта (просмотр задач, добавлени задачи)
 */

// по открытию страницы сразу убираем форму регистрации
$(document).ready(
  function () {
      states.addState( $("#projectView") );
      states.addState( $("#projectView"), 'only-my', issuePage.showIssues4Me);
      states.addState( $("#projectView"), 'last-created', issuePage.showLastCreated);
      states.addState( $("#projectView"), 'by-user:#', issuePage.showIssuesByUser);
      states.addState( $("#issueForm"  ), 'add-issue');
      states.addState( $("#issueForm"  ), 'copy-issue:#', issuePage.addIssueBy);
      states.addState( $("#issueForm"  ), 'finished-issue:#', issuePage.finishedIssueBy);
      //states.addState( $("#issueView"  ), 'issue-view' );
      
      //if ( window.location.search += 'iid=' + issueId;
      if (window.location.hash == '#issue-view') window.location.hash = '';
      
    //$("#registrationForm").hide();
      states.updateView();
	//  if (( /#add-issue/i ).test( window.location )) {
		//  $("#projectView").hide();
		  if ($('#issueForm > div.validateError' ).html() != '') {
			  $('#issueForm > div.validateError' ).show();
		  } 
	  //} else {
		  //$("#issueForm").hide();
		  //$('#issueForm > div.validateError' ).html( '' );
	  //}	  

	  issuePage.updateStat();
  }
);