/**
 * Авторизация и регистрация
 */
// по открытию страницы сразу убираем форму регистрации
$(document).ready(
	function () {
		if ((/#reg/i).test(window.location)) {
			$("#authForm").hide();
			$('#authForm > div.validateError').html('');
			if ($('#registrationForm > div.validateError').html() != '') {
				$('#registrationForm > div.validateError').show();
			}
		} else {
			$("#registrationForm").hide();
			$('#registrationForm > div.validateError').html('');
			if ($('#authForm > div.validateError').html() != '') {
				$('#authForm > div.validateError').show();
			}
		}
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

	if (!(/^([a-z0-9!"№;%:?*()_\+=\-~\/\\<{}\[\]]){1,24}$/i).test($('input[name=pass]', "#registrationForm").val())) {
		errors.push('Введён недопустимый пароль - используйте латинские буквы, цифры или знаки');
	}

	var nick = $('input[name=nick]', "#registrationForm").val();
	if (nick != '' && !(/^([a-z0-9._-]){3,64}$/i).test(nick)) {
		errors.push('Введён недопустимый Ник - используйте латинские буквы, цифры и знаки: ".", "-" или "_".');
	}

	$('#registrationForm > div.validateError').html(errors.join('<br/>'));

	if (errors.length == 0) {
		$('#registrationForm > div.validateError').hide();
		return true;
	} else {
		$('#registrationForm > div.validateError').show();
		return false;
	}
};