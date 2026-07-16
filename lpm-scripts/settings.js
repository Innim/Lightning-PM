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
});

function saveSettings(form) {
    const data = {
        title: form.querySelector('#title').value,
        subtitle: form.querySelector('#subtitle').value,
        allowRegistration: form.querySelector('#allowRegistration').checked ? 1 : 0,
        cookieExpire: form.querySelector('#cookieExpire').value,
        fromEmail: form.querySelector('#fromEmail').value,
        fromName: form.querySelector('#fromName').value,
        emailSubscript: form.querySelector('#emailSubscript').value,
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
