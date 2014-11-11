$(document).ready(
    function () {


    });

var usersPage = {};

usersPage.lockUser = function (e) {

    var parent = e.currentTarget.parentElement;

    var userId = $('input[name=userId]', parent).attr('value');

    var isLock = ($(parent).parent('tr').hasClass('active-user')) ? true : false;

    srv.users.lockUser(
        userId,
        isLock,
        function (res) {
            if (res.success) {                
                if (isLock)
                    $(parent).parent('tr').
                    addClass('locked-user').
                    removeClass('active-user').
                    appendTo('.users-list > tbody');
                else {
                    $(parent).parent('tr').
                    addClass('active-user').
                    removeClass('locked-user').
                    prependTo('.users-list > tbody');
                }
                
            } else {
                srv.err(res);
            }
        }
        );
}

//usersPage.unlockUser = function (e) {
//    var parent = e.currentTarget.parentElement;

//    var userId = $('input[name=userId]', parent).attr('value');

//    srv.users.lockUser(
//        userId,
//        false,
//        function (res) {
//            if (res.success) {

//                $(".users-list > tbody > tr:has( td > input[name=userId][value=" + userId + "])").
//                addClass('active-user').
//                removeClass('locked-user').
//                prependTo('.users-list > tbody');

//            } else {
//                srv.err(res);
//            }
//        }
//        );
//}