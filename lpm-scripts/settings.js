/**
 * Страница настроек приложения (администратор).
 */
$(function () {
    const form = document.getElementById('settingsForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        saveSettings(form);
    });

    form.querySelectorAll('textarea[data-default-text]').forEach(function (field) {
        field.addEventListener('input', function () {
            refreshDefaultState(form, field);
        });
    });

    form.querySelectorAll('[data-default-reset]').forEach(function (button) {
        button.addEventListener('click', function () {
            const field = form.querySelector('#' + button.dataset.defaultReset);
            field.value = field.dataset.defaultText;
            refreshDefaultState(form, field);
            field.focus();
        });
    });
});

/**
 * Обновляет отметку о том, что действует для настройки — умолчание или своё
 * значение, — и доступность возврата к умолчанию.
 *
 * Отметка отражает содержимое поля, а не факт правки: и совпадающий
 * с умолчанием, и пустой текст оставляют настройку умолчанием. Вернуть
 * умолчание можно и из пустого поля — текст в нём при этом появится.
 */
function refreshDefaultState(form, field) {
    const text = normalizeSettingText(field.value);
    const defaultText = normalizeSettingText(field.dataset.defaultText);
    const isDefaultText = text === defaultText;

    const badge = form.querySelector('[data-default-state="' + field.id + '"]');
    const isDefault = isDefaultText || text === '';
    badge.textContent = isDefault ? 'По умолчанию' : 'Своё значение';
    badge.classList.toggle('bg-secondary', isDefault);
    badge.classList.toggle('bg-primary', !isDefault);

    form.querySelector('[data-default-reset="' + field.id + '"]').disabled = isDefaultText;
}

/**
 * Приводит текст настройки к виду, в котором его сравнивает с умолчанием сервер.
 */
function normalizeSettingText(text) {
    return text.replace(/\r\n/g, '\n').trim();
}

function saveSettings(form) {
    const data = {
        title: form.querySelector('#title').value,
        subtitle: form.querySelector('#subtitle').value,
        allowRegistration: form.querySelector('#allowRegistration').checked ? 1 : 0,
        cookieExpire: form.querySelector('#cookieExpire').value,
        fromEmail: form.querySelector('#fromEmail').value,
        fromName: form.querySelector('#fromName').value,
        emailSubscript: form.querySelector('#emailSubscript').value,
        issueDescTemplate: form.querySelector('#issueDescTemplate').value,
        issueGuidelines: form.querySelector('#issueGuidelines').value,
        newIssueView: form.querySelector('#newIssueView').checked ? 1 : 0,
    };

    const submitBtn = form.querySelector('button[type=submit]');
    submitBtn.disabled = true;

    srv.admin.saveSettings(data, function (res) {
        submitBtn.disabled = false;
        if (res.success) {
            lpm.toast.show('Настройки сохранены');
        } else {
            showError(res.error || 'Ошибка при сохранении настроек');
        }
    });
}
