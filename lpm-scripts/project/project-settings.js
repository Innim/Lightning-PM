/**
 * Страница настроек проекта
 */
$(function () {
    $('button#setProjectSettings').on('click', () => {
        const scrumCheck = $("#scrumCheckbox").prop('checked');
        const scrum = scrumCheck ? 1 : 0;

        const gitlabProjectIds = $('#gitlabProjectIds').val();
        if (gitlabProjectIds) {
            const gitlabProjectIdsArr = gitlabProjectIds.split(',').map(Number);
            if (gitlabProjectIdsArr.some((id) => !id || id < 0 || !Number.isInteger(id))) {
                messages.alert('Невалидный ID проекта в GitLab');
                return;
            }
        }

        srv.project.setProjectSettings(
            $('input#projectId').val(),
            scrum,
            $('#slackСhannel').val(),
            $('#gitlabGroupId').val(),
            gitlabProjectIds,
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
