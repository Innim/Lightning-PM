$(document).ready(
    function () {
        sprintStatPage.sortMembersBySP();
    }
);

var sprintStatPage = {};

sprintStatPage.sortMembersBySP = function () {
    var table = $('#sprint-stat table.sprint-members-stat');
    table.find('tr:not(:first)').sort(function (a, b) {
        return $(b).data('doneSp') - $(a).data('doneSp');
    }).appendTo(table);
}