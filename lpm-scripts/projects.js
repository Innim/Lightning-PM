
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

	const addProjectForm = $('#addProjectForm form');
	// Отправка формы уже идёт: повторные отправки до её завершения запрещены.
	let addProjectSubmitting = false;

	/**
	 * Переводит форму добавления проекта в состояние отправки и обратно: в этом
	 * состоянии она не принимает новых отправок, кнопка отправки отключена,
	 * а страница закрыта индикатором загрузки.
	 * @param {boolean} value Перевести форму в состояние отправки.
	 */
	const setAddProjectSubmitting = function (value) {
		if (addProjectSubmitting === value) return;

		addProjectSubmitting = value;
		$('button[type=submit]', addProjectForm).prop('disabled', value);

		if (value) preloader.show();
		else preloader.hide();
	};

	addProjectForm.on('submit', function (e) {
		// Пока предыдущая отправка не завершилась, форма не уходит повторно:
		// иначе быстрый повторный Enter или клик создаёт дубль проекта.
		// Отключённой кнопки для этого мало: часть браузеров отправляет форму
		// по Enter, даже когда кнопка отправки отключена.
		// Состояние отправки ставится после проверки полей, чтобы форма
		// с ошибкой валидации осталась рабочей.
		if (addProjectSubmitting || !validateAddProj()) {
			e.preventDefault();
			e.stopImmediatePropagation();
			return false;
		}

		setAddProjectSubmitting(true);
	});

	// Возврат из кеша браузера («Назад») оживляет уже отправленную форму -
	// снимаем с неё состояние отправки, иначе отправить её снова будет нельзя.
	window.addEventListener('pageshow', function (e) {
		if (e.persisted) setAddProjectSubmitting(false);
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
	if (!lpm.validators.projectUid(uid))
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