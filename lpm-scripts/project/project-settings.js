/**
 * Страница настроек проекта
 */
$(function () {
    $('button#setProjectSettings').on('click', () => {
        const scrumCheck = $("#scrumCheckbox").prop('checked');
        const scrum = scrumCheck ? 1 : 0;
        srv.project.setProjectSettings(
            $('input#projectId').val(),
            scrum,
            $('#slackСhannel').val(),
            $('#gitlabGroupId').val(),
            function (res) {
                if (res.success) {
                    messages.info('Сохранено');
                } else {
                    srv.err(res);
                }
            }
        );
    });
});
