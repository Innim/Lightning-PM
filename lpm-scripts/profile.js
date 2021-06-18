$(document).ready(
    function () {
        $('#issueView .comments form.add-comment').hide();

        states.addState($("#profileInfo"), '', profilePage.onShowInfo);
        states.addState($("#changePass"), 'changepass', profilePage.onChangePass);
        //states.addState( $("#" ), 'edit', profilePage. );
        states.addState($("#userSettings"), 'settings', profilePage.onShowSettings);
    }
);

var profilePage = {};
profilePage.showInfo = function () {
    window.location.hash = '';
    states.updateView();
    return false;
};

profilePage.showSetting = function () {
    window.location.hash = 'settings';
    states.updateView();
    return false;
};

profilePage.changePass = function () {
    window.location.hash = 'changepass';
    states.updateView();
    return false;
}

profilePage.onShowInfo = function () {
    $('#profilePanel > h3').text('Информация');
};

profilePage.onShowSettings = function () {
    $('#profilePanel > h3').text('Настройки');
};

profilePage.onChangePass = function () {
    $('#profilePanel > h3').text('Смена пароля');
};


profilePage.validatePass = function () {
    var errors = [];

    if ($('input[name=newPass]', "#changePass").val() != $('input[name=repeatPass]', "#changePass").val()) {
        errors.push('Пароли не совпадают');
    }

    if (!(/^([a-z0-9!"№;%:?*()_\+=\-~\/\\<{}\[\]]){1,24}$/i).test($('input[name=newPass]', "#changePass").val())) {
        errors.push('Введён недопустимый пароль - используйте латинские буквы, цифры или знаки');
    }

    $('#changePass > div.validateError').html(errors.join('<br/>'));

    if (errors.length == 0) {
        $('#changePass > div.validateError').hide();
        return true;
    } else {
        $('#changePass > div.validateError').show();
        return false;
    }
}

profilePage.saveNewPass = function () {
    if (!profilePage.validatePass()) {
        return false;
    }
    else {
        preloader.show();
        srv.profile.newPass(
            $("#changePass form input[name=curentPass]").val(),
            $("#changePass form input[name=newPass]").val(),
            function (res) {
                preloader.hide();
                if (res.success) {
                    $('#changePass > div.validateError').hide();
                    messages.info('Сохранено');
                } else {
                    $('#changePass > div.validateError').html(res.error + '<br/>');
                    $('#changePass > div.validateError').show();
                    //srv.err(res);
                }
            }
        );
    }

}

profilePage.saveEmailPref = function () {
    preloader.show();

    srv.profile.emailPref(
        $("#userSettings form input[name=seAddIssue]").is(':checked'),
        $("#userSettings form input[name=seEditIssue]").is(':checked'),
        $("#userSettings form input[name=seIssueState]").is(':checked'),
        $("#userSettings form input[name=seIssueComment]").is(':checked'),
        function (res) {
            //btn.disabled = false;
            preloader.hide();
            if (res.success) {
                if ($("#userSettings form input[name=seAddIssue]").is(':checked'))
                    $("#userSettings form input[name=seAddIssue]").attr('checked', 'checked');
                else
                    $("#userSettings form input[name=seAddIssue]").removeAttr('checked');

                if ($("#userSettings form input[name=seEditIssue]").is(':checked'))
                    $("#userSettings form input[name=seEditIssue]").attr('checked', 'checked');
                else
                    $("#userSettings form input[name=seEditIssue]").removeAttr('checked');

                if ($("#userSettings form input[name=seIssueState]").is(':checked'))
                    $("#userSettings form input[name=seIssueState]").attr('checked', 'checked');
                else
                    $("#userSettings form input[name=seIssueState]").removeAttr('checked');

                if ($("#userSettings form input[name=seIssueComment]").is(':checked'))
                    $("#userSettings form input[name=seIssueComment]").attr('checked', 'checked');
                else
                    $("#userSettings form input[name=seIssueComment]").removeAttr('checked');

                messages.info('Сохранено');
            } else {
                srv.err(res);
                $("#userSettings form button[type=submit]").click();
            }
        }
    );
    return false;
};