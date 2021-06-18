$(document).ready(
    function () {
        states.addState($("#addWorkerList"));
        states.addState($("#addWorkerForm"), 'add-worker');

        if (window.location.hash == '#add-worker') window.location.hash = '';
        if ($('#addWorkerForm > div.validateError').html() != '') {
            $('#addWorkerForm > div.validateError').show();
        }

    }
);

var workersPage = {};

workersPage.showAddForm = function (event) {
    window.location.hash = 'add-worker';
    states.updateView();
    $('#addWorkerForm div.validateError').html('').hide();

    var row = event.currentTarget.parentNode.parentNode;

    $('#addWorkerForm form li:first-child > p').text(row.cells[0].innerText);
    $('#addWorkerForm form input[name=userId]').val($("input[type=hidden]", row).val());
};

function showMain() {
    window.location.hash = '';
    states.updateView();
    // TODO очищать поля формы
};

function addWorker() {
    var hours = $('#addWorkerForm form input[name=hours]').val();
    var comingTime = $('#addWorkerForm form input[name=comingTime]').val();
    var userId = $('#addWorkerForm form input[name=userId]').val();

    var errors = [];
    if (comingTime != '' && !(/^[0-9]{2}:[0-9]{2}$/i).test(comingTime)) {
        errors.push('Неверный формат времени');
    }

    if (errors.length == 0) {
        var btns = $('#addWorkerForm form button');
        btns.attr('disabled', 'disabled');
        // отправка запроса на сервер
        srv.workStudy.addWorker(
            userId,
            hours,
            comingTime,
            function (res) {
                btns.removeAttr('disabled');
                if (res.success) {
                    showMain();
                    var uidInput = $("#addWorkerList > table td > input[type=hidden][value=" + userId + "]");
                    if (uidInput.size() > 0) {
                        var cell = uidInput[0].parentNode;
                        while (cell.hasChildNodes()) {
                            cell.removeChild(cell.childNodes[0]);
                        }
                    }
                } else {
                    srv.err(res);
                }
            }
        );
    } else {
        $('#addWorkerForm div.validateError').html(errors.join('<br/>')).show();
    }

    return false;
};