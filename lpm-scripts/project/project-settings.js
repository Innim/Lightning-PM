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

    // Счётчик длины контекста проекта: длина ограничена, потому что контекст
    // уходит в каждый запрос к ИИ. Поля нет, если интеграция с ИИ не настроена.
    const $aiContext = $('#aiContext');
    if ($aiContext.length) {
        const limit = Number($aiContext.attr('maxlength'));
        const updateAiContextCounter = () => {
            $('#aiContextCounter').text($aiContext.val().length + ' / ' + limit);
        };

        $aiContext.on('input', updateAiContextCounter);
        // Сброс формы возвращает исходное значение уже после события.
        $aiContext.closest('form').on('reset', () => setTimeout(updateAiContextCounter, 0));
        updateAiContextCounter();
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

    // Подсказка и проверка канала оповещений Slack.
    // Имя приложения известно только Slack, поэтому подсказку забираем
    // отдельным запросом — открытие страницы его не ждёт.
    const $slackChannel = $('#slackChannel');
    const $slackHint = $('#slackChannelHint');
    const $slackCheckResult = $('#slackChannelCheckResult');
    const $slackCheckBtn = $('#checkSlackChannel');
    const slackCheckBtnText = $slackCheckBtn.text().trim();

    // Классы Bootstrap для результата проверки: приглашение приложения в канал
    // модератор делает сам, остальные отказы — повод идти к администратору.
    const SLACK_ALERT_CLASS = {
        ok: 'alert-success',
        okNotificationOff: 'alert-warning',
        notInvited: 'alert-warning',
    };

    // Показывает текст, готовую к копированию команду `/invite` и приписку
    // после неё — команду и приписку выводим, только если они пришли.
    function renderSlackNote($target, message, invite, note) {
        $target.empty().append($('<span>').text(message));

        if (invite) {
            $target.append(' ').append(
                $('<code>')
                    .attr('role', 'button')
                    .attr('title', 'Нажмите, чтобы скопировать')
                    .attr('data-copy', invite)
                    .attr('data-copy-toast', 'Команда скопирована')
                    .text(invite)
            );
        }

        if (note) {
            $target.append(' ').append($('<span>').text(note));
        }

        $target.removeClass('d-none');
    }

    function showSlackCheckResult(alertClass, message, invite) {
        renderSlackNote($slackCheckResult, message, invite, '');
        $slackCheckResult
            .removeClass('alert-success alert-warning alert-danger')
            .addClass(alertClass);
    }

    function hideSlackCheckResult() {
        $slackCheckResult.addClass('d-none');
    }

    if ($slackHint.length) {
        srv.project.getSlackChannelHint((res) => {
            if (!res.success) return;

            renderSlackNote($slackHint, res.message, res.invite, res.note);
        });
    }

    // Результат относится к тому каналу, который был в поле на момент проверки.
    // Стоит его поменять — прежний ответ уже ни о чём не говорит.
    $slackChannel.on('input', hideSlackCheckResult);

    $slackCheckBtn.on('click', () => {
        const channel = $slackChannel.val().trim();
        hideSlackCheckResult();

        if (!channel) {
            showSlackCheckResult('alert-danger', 'Укажите ID канала.', '');
            return;
        }

        $slackCheckBtn.prop('disabled', true).text('Проверяем…');

        srv.project.checkSlackChannel($('#projectId').val(), channel, (res) => {
            $slackCheckBtn.prop('disabled', false).text(slackCheckBtnText);

            if ($slackChannel.val().trim() !== channel) {
                return;
            }

            if (!res.success) {
                showSlackCheckResult(
                    'alert-danger',
                    res.error || 'Ошибка при запросе к серверу',
                    ''
                );
                return;
            }

            showSlackCheckResult(
                SLACK_ALERT_CLASS[res.status] || 'alert-danger',
                res.message,
                res.invite
            );
        });
    });

    // По сбросу формы возвращаем идентификатор в заблокированное состояние.
    $('button#saveProject').closest('form').on('reset', () => {
        hideError();
        hideSlackCheckResult();
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
        // Чекбоксов нет, если интеграция с ИИ не настроена — сервер в этом случае
        // оставляет текущие значения настроек.
        const aiSummary = $('#aiSummaryCheckbox').prop('checked') ? 1 : 0;
        const aiTestChecklist = $('#aiTestChecklistCheckbox').prop('checked') ? 1 : 0;
        const aiIssueDraft = $('#aiIssueDraftCheckbox').prop('checked') ? 1 : 0;
        const aiIssueReview = $('#aiIssueReviewCheckbox').prop('checked') ? 1 : 0;
        const aiContext = $aiContext.length ? $aiContext.val().trim() : '';
        const requireLabels = $('#requireLabelsCheckbox').prop('checked') ? 1 : 0;

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
            $('#slackChannel').val(),
            $('#gitlabGroupId').val(),
            gitlabProjectIds,
            aiSummary,
            aiTestChecklist,
            aiIssueDraft,
            aiIssueReview,
            aiContext,
            requireLabels,
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
