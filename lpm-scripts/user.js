$(document).ready(
    function () {
        $('form#editUser').on('submit', function (event) {
            event.preventDefault();
            let userId = $('#editUser input[name=userId]').val();
            let slackName = $('#editUser input[name=slackName]').val();
            preloader.show();
            srv.users.setSlackName(userId, slackName, function (res) {
                preloader.hide();
                if (res.success) {
                    $('#user > div.validateError').hide();
                    messages.info('Сохранено');
                } else {
                    $('#user > div.validateError').html(res.error).show();
                }
            });
        });
    }
);