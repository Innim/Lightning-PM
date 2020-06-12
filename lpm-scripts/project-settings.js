/**
 * Страница настроек проекта
 */
$(document).ready(
    function () {
        $('button#setProjectSettings').click(function () {
            let scrumCheck = $("#scrumCheckbox").prop('checked');
            var scrum = scrumCheck ? 1 : 0;
            srv.project.setProjectSettings(
                $('input#projectId').val(),
                scrum,
                $('#slackСhannel').val(),
                function (res) {
                    if (res.success) {
                        messages.info('Сохранено');
                    } else {
                        srv.err(res);
                    }
                }
            );
        });
    }
);