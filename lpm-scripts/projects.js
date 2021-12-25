
/**
 * Список проектов и добавление нового
 */
 $(function ($) {
	let isSending = false;

	if ((/#add-project/i).test(window.location)) {
		$("#projectsList").hide();
		if ($('#addProjectForm > div.validateError').html() != '') {
			$('#addProjectForm > div.validateError').show();
		}
	} else {
		$("#addProjectForm").hide();
		$('#addProjectForm > div.validateError').html('');
	}

	//Фиксация проекта в списке проектов.
	$('.project-fix').on('click', function () {
		if (!isSending) {
			const self = $(this);
			const projectId = self.parents('.project-list-item').data('projectId');
			const fixed = self.data('fixed')

			isSending = true;
			srv.projects.setIsFixed(projectId, !fixed, function () {
				location.reload();
			});
		}
	});

	$('.project-archive-btn, .project-restore-btn').on('click', function () {
		const self = $(this);
		const projectId = self.parents('.project-list-item').data('projectId');
		const value = self.hasClass('project-archive-btn');
		srv.projects.setIsArchive(projectId, value, function () {
			location.reload();
		});
	});
 });

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