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

    const setupHooksBtn = document.getElementById('setupGitlabWebhooks');
    if (setupHooksBtn) {
        setupHooksBtn.addEventListener('click', function () {
            lpm.dialog.confirm({
                title: 'Настроить вебхуки?',
                text: 'Во всех репозиториях, с которыми работал таск, будет заведён или обновлён'
                    + ' вебхук на адрес ' + setupHooksBtn.dataset.hookUrl
                    + ' — убедитесь, что этот адрес доступен из GitLab.',
                yesLabel: 'Настроить',
                onYes: setupGitlabWebhooks
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

function setupGitlabWebhooks() {
    const btn = document.getElementById('setupGitlabWebhooks');
    const errorBox = document.getElementById('gitlabWebhooksError');
    const resultBox = document.getElementById('gitlabWebhooksResult');
    const label = btn.textContent;

    errorBox.classList.add('d-none');
    resultBox.classList.add('d-none');
    btn.disabled = true;
    btn.textContent = 'Настраиваем…';

    srv.admin.setupGitlabWebhooks(function (res) {
        btn.disabled = false;
        btn.textContent = label;

        if (!res.success) {
            errorBox.textContent = res.error || 'Не удалось настроить вебхуки';
            errorBox.classList.remove('d-none');
            return;
        }

        if (res.failed && res.failed.length) {
            showFailedWebhooks(errorBox, res.failed);
        }

        if (!res.total) {
            resultBox.textContent = 'Репозиториев, с которыми таск работал, пока нет — настраивать нечего';
            resultBox.classList.remove('d-none');
            return;
        }

        // Зелёная плашка «0 из 43» рядом со списком отказов читается как успех
        if (!res.succeeded) {
            return;
        }

        resultBox.textContent = 'Вебхук настроен в репозиториях: ' + res.succeeded + ' из ' + res.total;
        resultBox.classList.remove('d-none');
    });
}

/**
 * Показывает репозитории, на которых настройка не удалась.
 *
 * Причины приходят из ответа GitLab, поэтому список собирается узлами,
 * а не разметкой строкой.
 */
function showFailedWebhooks(errorBox, failed) {
    errorBox.textContent = '';

    const title = document.createElement('div');
    title.className = 'fw-bold';
    title.textContent = 'Не удалось настроить: ' + failed.length;
    errorBox.appendChild(title);

    const list = document.createElement('ul');
    list.className = 'small mb-0';
    failed.forEach(function (item) {
        const li = document.createElement('li');
        li.textContent = 'Репозиторий ' + item.repositoryId + ' — ' + item.message;
        list.appendChild(li);
    });
    errorBox.appendChild(list);

    errorBox.classList.remove('d-none');
}
