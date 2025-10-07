/**
 * Общие скрипты страницы-раздела Проект
 */
function showMain() {
    window.location.hash = '';
    states.updateView();
}

function openMembersChooser() {
    var memberIds = [];
    var members = $("#projectMembers > ul.users-list > li > input[type=hidden][name=userId]");
    for (var i = 0; i < members.length; i++) {
        memberIds.push(members.eq(i).val());
    }

    ucOpen(memberIds, addMembers);
}

function addMembers(arr) {
    // добавление участников 
    preloader.show();
    srv.project.addMembers(
        $("#projectMembers input[name=projectId]").val(),
        arr,
        function (res) {
            preloader.hide();

            if (res.success) {
                $("#projectMembers > ul.users-list > li").remove();
                var j = 0;
                var ok = false;
                var userId = 0;
                var count = res.members.length;
                var members = $("#projectMembers > ul.users-list > li");
                for (var i = 0; i < members.length; i++) {
                    ok = false;
                    userId = members.eq(i).find('input[type=hidden][name=userId]').val();
                    for (j = 0; j < count; j++) {
                        if (res.members[j].userId.toString() == userId) {
                            res.members[j].splice(j, 1);
                            count--;
                            j--;
                            ok = true;
                        }
                    }
                    if (!ok) members.eq(i).remove();
                }

                var ul = $("#projectMembers > ul.users-list");
                var user;
                for (j = 0; j < count; j++) {
                    user = new User(res.members[j]);
                    ul.append(
                        '<li><span class="user-name">' + user.getLinkedName() + '</span>' +
                        '<input type="hidden" name="userId" value="' + user.userId + '"></li>'
                    );
                }
                // XXX: плохое решение, надо переписать
                location.reload();
            } else {
                srv.err(res);
            }
        }
    );
}