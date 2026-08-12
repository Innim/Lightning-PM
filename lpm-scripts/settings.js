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

    const applyBtn = document.getElementById('applyDbMigrations');
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            lpm.dialog.confirm({
                title: 'Применить миграции?',
                text: 'Схема базы данных будет изменена. Отменить это нельзя — убедитесь,'
                    + ' что есть резервная копия. Большие изменения надёжнее применять из консоли.',
                yesLabel: 'Применить',
                onYes: applyDbMigrations
            });
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

function applyDbMigrations() {
    const btn = document.getElementById('applyDbMigrations');
    const errorBox = document.getElementById('dbMigrationsError');
    const label = btn.textContent;

    errorBox.classList.add('d-none');
    btn.disabled = true;
    btn.textContent = 'Применяем…';

    srv.admin.applyDbMigrations(function (res) {
        if (res.success) {
            // Состояние миграций отрисовано на сервере — перезагружаем,
            // чтобы показать результат целиком, а не пересобирать список в JS.
            location.reload();
            return;
        }

        btn.disabled = false;
        btn.textContent = label;
        errorBox.textContent = res.error || 'Не удалось применить миграции';
        errorBox.classList.remove('d-none');
    });
}
