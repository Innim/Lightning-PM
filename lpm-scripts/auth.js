/**
 * Авторизация и регистрация
 */
// по открытию страницы сразу убираем форму регистрации
$(document).ready(
	function () {
		// Регистрация может быть отключена в настройках - тогда формы нет
		if ($("#registrationForm").length === 0) {
			$("#authForm").show();
			if ($('#authForm div.validateError').html() != '') {
				$('#authForm div.validateError').show();
			}
			return;
		}

		if ((/#reg/i).test(window.location)) {
			$("#authForm").hide();
			$('#authForm div.validateError').html('');
			if ($('#registrationForm div.validateError').html() != '') {
				$('#registrationForm div.validateError').show();
			}
		} else {
			$("#registrationForm").hide();
			$('#registrationForm div.validateError').html('');
			if ($('#authForm div.validateError').html() != '') {
				$('#authForm div.validateError').show();
			}
		}

		const regForm = $("#registrationForm form");
		// Отправка формы уже идёт: повторные отправки до её завершения запрещены.
		let regSubmitting = false;

		/**
		 * Переводит форму регистрации в состояние отправки и обратно: в этом
		 * состоянии она не принимает новых отправок, кнопка отправки отключена,
		 * а страница закрыта индикатором загрузки.
		 * @param {boolean} value Перевести форму в состояние отправки.
		 */
		const setRegSubmitting = function (value) {
			if (regSubmitting === value) return;

			regSubmitting = value;
			$('button[type=submit]', regForm).prop('disabled', value);

			if (value) preloader.show();
			else preloader.hide();
		};

		regForm.on('submit', function (e) {
			// Пока предыдущая отправка не завершилась, форма не уходит повторно:
			// иначе быстрый повторный Enter или клик создаёт дубль учётной записи.
			// Отключённой кнопки для этого мало: часть браузеров отправляет форму
			// по Enter, даже когда кнопка отправки отключена.
			// Состояние отправки ставится после проверки полей, чтобы форма
			// с ошибкой валидации осталась рабочей.
			if (regSubmitting || !validateReg()) {
				e.preventDefault();
				e.stopImmediatePropagation();
				return false;
			}

			setRegSubmitting(true);
		});

		// Возврат из кеша браузера («Назад») оживляет уже отправленную форму -
		// снимаем с неё состояние отправки, иначе отправить её снова будет нельзя.
		window.addEventListener('pageshow', function (e) {
			if (e.persisted) setRegSubmitting(false);
		});
	}
);

function showRegistration() {
	$("#registrationForm").show();
	$("#authForm").hide();
};

function showAuth() {
	$("#registrationForm").hide();
	$("#authForm").show();

};

function validateReg() {
	var errors = [];

	if ($('input[name=pass]', "#registrationForm").val() != $('input[name=repass]', "#registrationForm").val()) {
		errors.push('Пароли не совпадают');
	}

	const passError = lpm.validators.password($('input[name=pass]', "#registrationForm").val());
	if (passError) {
		errors.push(passError);
	}

	var nick = $('input[name=nick]', "#registrationForm").val();
	if (nick != '' && !(/^([a-z0-9._-]){3,64}$/i).test(nick)) {
		errors.push('Введён недопустимый Ник - используйте латинские буквы, цифры и знаки: ".", "-" или "_".');
	}

	$('#registrationForm div.validateError').html(errors.join('<br/>'));

	if (errors.length == 0) {
		$('#registrationForm div.validateError').hide();
		return true;
	} else {
		$('#registrationForm div.validateError').show();
		return false;
	}
};