$(document).ready(
    function () {
        // projectsStatPage.sortProjectsBySP();
    }
);

var projectsStatPage = {};

projectsStatPage.sortProjectsBySP = function () {
    var table = $('#projects-stat table.projects-stat');
    table.find('tr:not(:first)').sort(function (a, b) {
        return $(b).data('doneSp') - $(a).data('doneSp');
    }).appendTo(table);
}