$(document).ready(
    function () {
        let $error = $('#userView .validateError');

        function showError(msg) {
            preloader.hide();
            $error.html(msg).show();
        }

        function done() {
            preloader.hide();
            $error.hide();
            lpm.toast.show('Сохранено');
        }

        $('form#editUser').on('submit', function (event) {
            event.preventDefault();
            let userId = $('#editUser input[name=userId]').val();
            let slackName = $('#editUser input[name=slackName]').val();
            let $role = $('#editUser select[name=role]');
            preloader.show();
            srv.users.setSlackName(userId, slackName, function (res) {
                if (!res.success) {
                    showError(res.error);
                    return;
                }
                if ($role.length && $role.val() !== String($role.data('current'))) {
                    srv.users.setRole(userId, $role.val(), function (roleRes) {
                        if (roleRes.success) {
                            $role.data('current', $role.val());
                            done();
                        } else {
                            showError(roleRes.error);
                        }
                    });
                } else {
                    done();
                }
            });
        });
    }
);
