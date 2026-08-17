/**
 * Страница статуса приложения (администратор).
 */
$(function ($) {
    $('#cacheFlushButton').on('click', () => {
        srv.admin.flushCache((res) => {
            if (res.success) {
                messages.info('Кэш успешно сброшен');
            } else {
                showError('Ошибка при попытке сбросить кэш');
            }
        });
    });

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
