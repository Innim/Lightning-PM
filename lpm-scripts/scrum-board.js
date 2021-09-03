$(document).ready(
    function () {
        document.querySelectorAll('.name-project').forEach(function (e) {
            if (e.scrollWidth > e.offsetWidth) {
                e.setAttribute('title', e.textContent)
            }
        });

        sprintTarget.init();
    }
);

let scrumBoard = {
    changeScrumState: function (e) {
        const $control = $(e.currentTarget);
        const $sticker = $control.parents('.scrum-board-sticker');
        const issueId = $sticker.data('issueId');
        const curState = $sticker.data('stickerState');

        const memberIds = $('.sticker-issue-member', $sticker).map((_, e) => $(e).data('memberId')).get();

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
                    case ScrumStickerState.todo: colName = 'todo'; break;
                    case ScrumStickerState.inProgress: colName = 'in_progress'; break;
                    case ScrumStickerState.testing: colName = 'testing'; break;
                    case ScrumStickerState.done: colName = 'done'; break;
                }

                if (colName) {
                    $('.scrum-board-col.col-' + colName).append($sticker);
                }

                issuePage.scumColUpdateInfo();

                if (curState == ScrumStickerState.todo && state == ScrumStickerState.inProgress && memberIds.length == 0) {
                    scrumBoard.takeIssueBy($sticker);
                }
            }
        });
    },
    takeIssue: function (e) {
        $dialog = $("#takeIssueConfirm");
        const $control = $(e.currentTarget);
        const $sticker = $control.parents('.scrum-board-sticker');

        if ($('.sticker-issue-members', $sticker).children().length > 0) {
            const takeAndClose = (replace) => {
                scrumBoard.takeIssueBy($sticker, replace);
                $dialog.dialog("close");
            }

            $dialog.dialog({
                resizable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: {
                    "Добавить": () => takeAndClose(false),
                    "Заменить": () => takeAndClose(true),
                },
            });
        } else {
            scrumBoard.takeIssueBy($sticker);
        }
    },
    takeIssueBy: function ($sticker, replace = true) {
        const issueId = $sticker.data('issueId');
        preloader.show();
        srv.issue.takeIssue(issueId, replace, function (res) {
            preloader.hide();
            if (res.success) {
                $sticker.addClass('mine');
                const $members = $('.sticker-issue-members', $sticker);
                if (replace) {
                    $members.empty();
                } else if ($members.children().length > 0) {
                    $members.append(', ');
                }
                $members.append(res.memberHtml);
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
                    sprintTarget.setValue('', '');
                    issuePage.scumColUpdateInfo();
                } else {
                    srv.err(res);
                }
            });
        }
    },
};

const sprintTarget = {
    init: function (modalParam) {
        $('#addTarget').dialog({...sprintTarget.defaultParam, ...modalParam});
        $('.target-btn').on('click', () => sprintTarget.open());
        this.updateVisibility();
    },
    setValue: function (text, html) {
        $('.text-target').html(html);
        $('.input-target').val(text);

        sprintTarget.updateVisibility();
    },
    updateVisibility: function () {
        if ($('.text-target').text().trim()) {
            $('.title-target').show();
        } else {
            $('.title-target').hide();
        }
    },
    open: function () {
        $('#addTarget').dialog('open');
    },
    close: function () {
        $('#addTarget').dialog('close');
    },
    save: function () {
        const targetText = $('.input-target').val();
        const projectId = $('#scrumBoard').data('project-id');

        srv.project.setSprintTarget(projectId, targetText, function (res) {
            if (res) {
                sprintTarget.setValue(res.targetText, res.targetHTML);
            }
        });
        sprintTarget.close();
    },
    defaultParam: {
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
                    sprintTarget.save();
                }
            },
            {
                text: 'Отмена',
                click: function () {
                    sprintTarget.close();
                }
            }
        ]
    }
}

const ScrumStickerState = Object.freeze({
    backlog: 0,
    todo: 1,
    inProgress: 2,
    testing: 3,
    done: 4,
    archived: 5,
    deleted: 6,
});
