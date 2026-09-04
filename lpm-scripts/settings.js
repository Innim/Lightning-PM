/**
 * Страница настроек приложения (администратор).
 */
$(function () {
    const form = document.getElementById('settingsForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSettings(form);
        });
    }
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
