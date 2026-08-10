/**
 * Блок ИИ-сводки обсуждения на странице задачи.
 *
 * Сводка составляется только по клику пользователя: результат общий для всех,
 * поэтому первый запрос может занять до минуты, а последующие открытия страницы
 * берут готовую сводку с сервера.
 */
$(function () {
    // Блок целиком перерисовывается ответом сервера, поэтому обработчик делегированный.
    $(document).on('click', '.ai-summary .ai-summary-action', function () {
        const button = $(this);
        if (button.prop('disabled')) {
            return;
        }

        const block = button.closest('.ai-summary');
        const label = button.html();

        button.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
            + 'Собираем сводку…'
        );

        srv.ai.issueSummary(block.data('issue-id'), function (res) {
            if (!res.success) {
                button.prop('disabled', false).html(label);
                showError(res.error || 'Не удалось получить сводку задачи');
                return;
            }

            block.replaceWith(res.html);
        });
    });
});
