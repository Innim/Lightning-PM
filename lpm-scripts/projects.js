
/**
 * Список проектов и добавление нового
 */
// по открытию страницы сразу убираем форму регистрации
$(document).ready(
  function ()
  {
    //$("#registrationForm").hide();
	  
	  if (( /#add-project/i ).test( window.location )) {
		  $("#projectsList").hide();
		  if ($('#addProjectForm > div.validateError' ).html() != '') {
			  $('#addProjectForm > div.validateError' ).show();
		  } 
	  } else {
		  $("#addProjectForm").hide();
		  $('#addProjectForm > div.validateError' ).html( '' );
	  }
  }
);

function showAddProjectForm() {
	$("#addProjectForm").show();
	$("#projectsList").hide();
	if (!( /#add/i ).test( window.location )) {	    
	    window.location.hash = 'add-project';
	}
};

function showProjectsList() {
	$("#addProjectForm").hide();
	$("#projectsList").show();
	window.location.hash = '';
};

function validateAddProj() {
	var errors = [];
	
	if ((/^([0-9]){4}\-([0-9]){2}\-([0-9]){2}$/i).test( ('input[name=completeDate]', "#addIssueForm" ).val() )) {
		errors.push( 'Недопустимый формат даты. Требуется формат ГГГГ-ММ-ДД' );
	}
	
	$('#addIssueForm > div.validateError' ).html( errors.join( '<br/>' ) );
	
	if (errors.length == 0) {
		$('#addIssueForm > div.validateError' ).hide();
		return true;
	} else {
		$('#addIssueForm > div.validateError' ).show();
		return false;
	}
};

function setIsArchive( e ){
	var parent = e.currentTarget.parentElement;
	var projectId = $('input[name=projectId]', parent).attr('value');
	var value = ($("a", parent).hasClass('archive btn')) ? true : false;
	srv.projects.setIsArchive( projectId , value, reload = function(){
		location.reload();
	});
};