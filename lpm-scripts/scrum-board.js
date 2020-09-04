$(document).ready(
    function () {
        document.querySelectorAll('.name-project').forEach(function (e) {
            if (e.scrollWidth > e.offsetWidth) {
                e.setAttribute('title', e.textContent)
            }
        });

        addTarget.init();
        $('.target-btn').click(function () {
            addTarget.open();
        });

        if ($('.text-target').text()) {
            $('.title-target').removeClass('hidden');
        }
    }
);

let scrumBoard = {
    changeScrumState: function (e) {
        var $control = $(e.currentTarget);
        var $sticker = $control.parents('.scrum-board-sticker');
        var issueId = $sticker.data('issueId');
        var curState = $sticker.data('stickerState');

        // Определяем следующий стейт
        var state;
        if ($control.hasClass('sticker-control-done'))
            state = 4;
        else if ($control.hasClass('sticker-control-prev'))
            state = curState - 1;
        else if ($control.hasClass('sticker-control-next'))
            state = curState + 1;
        else if ($control.hasClass('sticker-control-archive'))
            state = 5;
        else if ($control.hasClass('sticker-control-remove'))
            state = 0;
        else
            return;

        preloader.show();
        srv.issue.changeScrumState(issueId, state, function (res) {
            preloader.hide();
            if (res.success) {
                $sticker.attr('data-sticker-state', state);
                // Перевешиваем стикер
                $sticker.remove();
                var colName;
                switch (state) {
                    case 1: colName = 'todo'; break;
                    case 2: colName = 'in_progress'; break;
                    case 3: colName = 'testing'; break;
                    case 4: colName = 'done'; break;
                }

                if (colName) {
                    $('.scrum-board-col.col-' + colName).append($sticker);
                }
                issuePage.scumColUpdateInfo();
            }
        });
    },
    takeIssue: function (e) {
        var $control = $(e.currentTarget);
        var $sticker = $control.parents('.scrum-board-sticker');
        var issueId = $sticker.data('issueId');
        preloader.show();
        srv.issue.takeIssue(issueId, function (res) {
            preloader.hide();
            if (res.success) {
                $sticker.addClass('mine');
                $('.sticker-issue-members', $sticker).text(res.memberName);
                issuePage.scumColUpdateInfo();
            }
        });
    },
    changeSPVisibility: function (value) {
        if (value)
            $('#scrumBoard').removeClass('hide-sp');
        else
            $('#scrumBoard').addClass('hide-sp');
    },
    clearBoard: function () {
        if (confirm('Убрать все стикеры с доски?')) {
            let projectId = $('#scrumBoard').data('projectId');
            preloader.show();
            srv.issue.removeStickersFromBoard(projectId, function (res) {
                preloader.hide();
                if (res.success) {
                    $('#scrumBoard .scrum-board-table .scrum-board-sticker').remove();
                    $('.title-target').addClass('hidden');
                    $('.text-target').children().remove();;
                    $('.input-target').val('');
                    addTarget.incNumSprint($('.ui-dialog-title'));
                    issuePage.scumColUpdateInfo();
                } else {
                    srv.err(res);
                }
            });
        }
    },
};

const addTarget = {
    init: function () {
        $('#addTarget').dialog(
            {
                dialogClass: 'modal-target-sprint',
                autoOpen: false,
                modal: true,
                width: 540,
                height: 394,
                closeText: 'Закрыть',
                resizable: false,
                buttons: [
                    {
                        text: 'Сохранить',
                        click: function () {
                            addTarget.save();
                        }
                    },
                    {
                        text: 'Отмена',
                        click: function () {
                            addTarget.close();
                        }
                    }
                ]
            }
        );
    },
    open: function () {
        $('#addTarget').dialog('open');
    },
    close: function () {
        $('#addTarget').dialog('close');
    },
    save: function () {
        const textTarget = $('.input-target').val();
        const projectId = $('#scrumBoard').data('project-id');

        srv.project.setTargetSprint(projectId, textTarget, function (res) {
            if (res) {
                $('.text-target').html(res.targetHTML);
                $('.input-target').val(res.targetText);

                if (!res.targetText) {
                    $('.title-target').addClass('hidden');
                } else {
                    $('.title-target').removeClass('hidden');
                }
            }
        });
        addTarget.close();
    },
    incNumSprint: function (element) {
        let numSprint = element.text().replace(/\D/g,'');
        element.text(`Цели спринта #${++numSprint}`);
    }
}