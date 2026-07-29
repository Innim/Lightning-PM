/**
 * Страница настроек проекта
 */
$(function () {
    // Флаг для показа тоста после редиректа (uid изменился — был полный переход).
    const SAVED_FLAG = 'projectSettingsSaved';
    const currentUid = $('#projectUid').val().trim().toLowerCase();

    if (window.sessionStorage.getItem(SAVED_FLAG)) {
        window.sessionStorage.removeItem(SAVED_FLAG);
        lpm.toast.show('Сохранено');
    }

    // Ошибка сохранения блокирует сохранение, поэтому показываем её баннером
    // вверху формы и прокручиваем к нему — иначе на длинной форме её не видно.
    function showError(message) {
        $('#projectError').text(message).removeClass('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideError() {
        $('#projectError').addClass('d-none');
    }

    // Возвращает поле идентификатора в исходное заблокированное состояние.
    function lockUid() {
        $('#projectUid').prop('disabled', true);
        $('a#editProjectUid').removeClass('d-none');
    }

    // Идентификатор по умолчанию заблокирован. Разблокируем только после
    // подтверждения — смена uid ломает все старые ссылки на задачи.
    $('a#editProjectUid').on('click', (e) => {
        e.preventDefault();
        if (!$('#projectUid').prop('disabled')) {
            return;
        }

        lpm.dialog.confirm({
            title: 'Изменение идентификатора',
            text: 'При изменении uid проекта перестанут работать все старые ссылки '
                + 'на задачи. Вы точно хотите изменить uid?',
            onYes: () => {
                $('#projectUid').prop('disabled', false).trigger('focus');
                // Прячем через d-none, а не .hide(): jQuery hide/show перехватывается
                // событиями Bootstrap-модалки confirm-диалога.
                $('a#editProjectUid').addClass('d-none');
            },
        });
    });

    // По сбросу формы возвращаем идентификатор в заблокированное состояние.
    $('button#saveProject').closest('form').on('reset', () => {
        hideError();
        lockUid();
    });

    $('button#saveProject').on('click', () => {
        hideError();

        const name = $('#projectName').val().trim();
        const desc = $('#projectDesc').val().trim();
        const uid = $('#projectUid').val().trim().toLowerCase();

        if (!name || !desc || !uid) {
            showError('Заполнены не все поля');
            return;
        }

        if (!lpm.validators.projectUid(uid)) {
            showError('В идентификаторе допустимы латинские буквы (a-z), цифры и дефис');
            return;
        }

        const scrum = $('#scrumCheckbox').prop('checked') ? 1 : 0;

        const gitlabProjectIds = $('#gitlabProjectIds').val();
        if (gitlabProjectIds) {
            const gitlabProjectIdsArr = gitlabProjectIds.split(',').map(Number);
            if (gitlabProjectIdsArr.some((id) => !id || id < 0 || !Number.isInteger(id))) {
                showError('Невалидный ID проекта в GitLab');
                return;
            }
        }

        srv.project.saveProject(
            $('#projectId').val(),
            uid,
            name,
            desc,
            scrum,
            $('#slackСhannel').val(),
            $('#gitlabGroupId').val(),
            gitlabProjectIds,
            function (res) {
                if (!res.success) {
                    showError(res.error || 'Ошибка при запросе к серверу');
                    return;
                }

                // uid мог измениться — вместе с ним меняется URL страницы и все
                // ссылки на ней. Делаем полный редирект на новый адрес, а тост
                // «Сохранено» показываем уже на перезагруженной странице.
                if (res.uid !== currentUid) {
                    window.sessionStorage.setItem(SAVED_FLAG, '1');
                    redirectTo(res.url);
                    return;
                }

                lockUid();
                lpm.toast.show('Сохранено');
            }
        );
    });
});
