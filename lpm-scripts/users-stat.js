$(document).ready(
    function () {
        usersStatPage.sortUsersBySP();
    }
);

var usersStatPage = {};

usersStatPage.sortUsersBySP = function () {
    var table = $('#users-stat table.users-stat');
    table.find('tr:not(:first)').sort(function(a, b) {
        return $(b).data('doneSp') - $(a).data('doneSp');
    }).appendTo(table);
}