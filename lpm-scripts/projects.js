
/**
 * Список проектов и добавление нового
 */
// по открытию страницы сразу убираем форму регистрации
$(document).ready(
	function () {
		//$("#registrationForm").hide();

		if ((/#add-project/i).test(window.location)) {
			$("#projectsList").hide();
			if ($('#addProjectForm > div.validateError').html() != '') {
				$('#addProjectForm > div.validateError').show();
			}
		} else {
			$("#addProjectForm").hide();
			$('#addProjectForm > div.validateError').html('');
		}
	}
);

function showAddProjectForm() {
	$("#addProjectForm").show();
	$("#projectsList").hide();
	if (!(/#add/i).test(window.location)) {
		window.location.hash = 'add-project';
	}
};

function showProjectsList() {
	$("#addProjectForm").hide();
	$("#projectsList").show();
	window.location.hash = '';
};

function fixedProject(e) {
	const parent = e.target;
	const projectId = $('input[name=projectId]', parent).val();
	console.log(projectId);
};

function validateAddProj() {
	let errors = [];
	let form = $('#addProjectForm');

	if ($('textarea[name=desc]', form).val() === '')
		errors.push('Нужно дать описание проекта');

	let uid = $('input[name=uid]', form).val();
	if (!(/^(([a-zA-Z0-9]){1}([a-zA-Z0-9\-]){0,254})$/u).test(uid))
		errors.push('В идентификаторе допустимы строчные буквы (a-z), цифры и дефис');

	let errorDisplay = $('div.validateError', form);
	errorDisplay.html(errors.join('<br/>'));

	if (errors.length == 0) {
		errorDisplay.hide();
		return true;
	} else {
		errorDisplay.show();
		return false;
	}
};

function setIsArchive(e) {
	var parent = e.currentTarget.parentElement;
	var projectId = $('input[name=projectId]', parent).val();
	var value = ($("a", parent).hasClass('archive btn')) ? true : false;
	srv.projects.setIsArchive(projectId, value, reload = function () {
		location.reload();
	});
};