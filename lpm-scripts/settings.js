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
        allowRegistration: form.querySelector('#allowRegistration').checked ? 1 : 0,
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
