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

            function save() {
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
            }

            let roleChanged = $role.length && $role.val() !== String($role.data('current'));
            let settingAdmin = roleChanged && $role.val() === String(lpmOptions.roles.admin);
            if (settingAdmin) {
                let username = $('<span>').text($role.data('username')).html();
                lpm.dialog.confirm({
                    title: 'Подтверждение',
                    text: 'Вы действительно хотите дать пользователю <strong>' + username
                        + '</strong> права администратора?',
                    onYes: save,
                    onNo: function () {
                        $role.val(String($role.data('current')));
                    }
                });
            } else {
                save();
            }
        });
    }
);
