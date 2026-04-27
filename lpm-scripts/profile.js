$(document).ready(
    function () {
        $('#issueView .comments form.add-comment').hide();

        states.addState($("#profileInfo"), '', profilePage.onShowInfo);
        states.addState($("#changePass"), 'changepass', profilePage.onChangePass);
        //states.addState( $("#" ), 'edit', profilePage. );
        states.addState($("#userSettings"), 'settings', profilePage.onShowSettings);
        states.addState($("#apiKeysSettings"), 'api-keys', profilePage.onShowApiKeys);
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

profilePage.showApiKeys = function () {
    window.location.hash = 'api-keys';
    states.updateView();
    return false;
};

profilePage.onShowInfo = function () {
    $('#profilePanel > h3').text('Информация');
};

profilePage.onShowSettings = function () {
    $('#profilePanel > h3').text('Настройки');
};

profilePage.onShowApiKeys = function () {
    $('#profilePanel > h3').text('API ключи');
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
            $("#changePass form input[name=currentPass]").val(),
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

    const $form = $("#userSettings form");
    const emailPrefs = ['seAddIssue', 'seEditIssue', 'seIssueState', 'seIssueComment',
        'seAddIssueForPM', 'seEditIssueForPM', 'seIssueStateForPM', 'seIssueCommentForPM'];

    const data = {};
    emailPrefs.forEach(pref => {
        const $checkbox = $(`input[name=${pref}]`, $form);
        if ($checkbox.length > 0) {
            data[pref] = $checkbox.is(':checked');
        }
    });

    srv.profile.emailPref(
        data,
        function (res) {
            preloader.hide();
            if (res.success) {
                emailPrefs.forEach(pref => {
                    const $checkbox = $(`input[name=${pref}]`, $form);
                    if ($checkbox.is(':checked')) {
                        $checkbox.attr('checked', 'checked');
                    } else {
                        $checkbox.removeAttr('checked');
                    }
                });

                messages.info('Сохранено');
            } else {
                srv.err(res);
            }
        }
    );
    return false;
};

profilePage.createApiKey = function () {
    preloader.show();
    srv.profile.createApiKey('', function (res) {
        preloader.hide();
        if (!res.success) {
            srv.err(res);
            return;
        }

        profilePage.upsertApiKey(res.key);
        $('#apiKeyValue').val(res.token).trigger('focus').trigger('select');
        $('#apiKeyResult').removeClass('d-none');
        messages.info('Сохранено');
    });
};

profilePage.revokeApiKey = function (keyId) {
    preloader.show();
    srv.profile.revokeApiKey(keyId, function (res) {
        preloader.hide();
        if (!res.success) {
            srv.err(res);
            return;
        }

        profilePage.removeApiKey(keyId);
        $('#apiKeyValue').val('');
        $('#apiKeyResult').addClass('d-none');
        messages.info('Сохранено');
    });
};

profilePage.upsertApiKey = function (key) {
    const id = parseInt(key.id, 10);
    let $row = $('#apiKeyRow-' + id);
    const html = `
        <tr id="apiKeyRow-${id}">
            <td>${key.preview}</td>
            <td>${key.created}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="profilePage.revokeApiKey(${id});">Отозвать</button>
            </td>
        </tr>
    `;

    if ($row.length > 0) {
        $row.replaceWith(html);
    } else {
        $('#apiKeysEmpty').addClass('d-none');
        $('#apiKeysTable').removeClass('d-none');
        $('#apiKeysTable tbody').prepend(html);
    }
};

profilePage.removeApiKey = function (keyId) {
    $('#apiKeyRow-' + keyId).remove();
    if ($('#apiKeysTable tbody tr').length === 0) {
        $('#apiKeysTable').addClass('d-none');
        $('#apiKeysEmpty').removeClass('d-none');
    }
};

profilePage.copyApiKey = function () {
    const $input = $('#apiKeyValue');
    const value = $input.val();
    if (!value) {
        return false;
    }

    const input = $input[0];
    input.focus();
    input.select();

    try {
        document.execCommand('copy');
        messages.info('Ключ скопирован');
    } catch (e) {
        console.error(e);
    }

    return false;
};
