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
});