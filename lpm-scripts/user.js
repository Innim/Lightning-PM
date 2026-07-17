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
                    $('#userView .validateError').hide();
                    lpm.toast.show('Сохранено');
                } else {
                    $('#userView .validateError').html(res.error).show();
                }
            });
        });
    }
);