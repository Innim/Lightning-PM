$(document).ready(
    function () {
        states.addState(null);
        states.addState(null, 'filter:#', issuePage.handleFilterState);

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

        const applyState = function () {
            preloader.show();
            srv.issue.changeScrumState(issueId, state, function (res) {
                preloader.hide();
                if (!res.success) {
                    srv.err(res);
                    return;
                }

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

                issuePage.scrumColUpdateInfo();

                if (curState == ScrumStickerState.todo && state == ScrumStickerState.inProgress && memberIds.length == 0) {
                    scrumBoard.takeIssueBy($sticker);
                }
            });
        };

        if (state == 4) {
            lpm.dialog.confirm({
                text: 'Завершить задачу?',
                yesLabel: 'Завершить',
                onYes: applyState
            });
        } else {
            applyState();
        }
    },
    takeIssue: function (e) {
        const $control = $(e.currentTarget);
        const $sticker = $control.parents('.scrum-board-sticker');

        if ($('.sticker-issue-members', $sticker).children().length > 0) {
            lpm.dialog.show({
                content: $("#takeIssueConfirm").html(),
                primaryBtn: "Добавить",
                onPrimary: () => scrumBoard.takeIssueBy($sticker, false),
                secondaryBtn: "Заменить",
                onSecondary: () => scrumBoard.takeIssueBy($sticker, true),
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
                issuePage.scrumColUpdateInfo();
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
        const projectId = $('#scrumBoard').data('projectId');

        const transferCols = ['col-todo', 'col-in_progress'];
        const columnsSelector = '#scrumBoard .scrum-board-table .scrum-board-col';

        const doClear = function (transfer) {
            preloader.show();
            srv.issue.removeStickersFromBoard(projectId, transfer, function (res) {
                preloader.hide();
                if (res.success) {
                    let $elements = $(columnsSelector);
                    if (transfer) {
                        transferCols.forEach(col => $elements = $elements.not('.' + col));
                    }
                    $elements = $elements.find('.scrum-board-sticker');

                    $elements.remove();
                    sprintTarget.setValue('', '');
                    issuePage.scrumColUpdateInfo();
                } else {
                    srv.err(res);
                }
            });
        }

        lpm.dialog.confirm({
            text: 'Убрать все стикеры с доски?',
            yesLabel: 'Убрать',
            onYes: function () {
                // Если в «TO DO»/«В работе» есть задачи — уточняем, переносить ли их
                // на новый спринт. Окно откроется после закрытия предыдущего.
                if (transferCols.some(col => $(columnsSelector + '.' + col + ' .scrum-board-sticker').size() > 0)) {
                    lpm.dialog.show({
                        title: 'Очистка SCRUM доски',
                        text: 'Перенести задачи из колонок «TO DO» и «В работе» на новый спринт?',
                        primaryBtn: 'Перенести',
                        secondaryBtn: 'Не переносить',
                        onPrimary: function () { doClear(true); },
                        onSecondary: function () { doClear(false); },
                    });
                } else {
                    doClear(false);
                }
            }
        });
    },
};

const sprintTarget = {
    init: function () {
        const $el = $('#addTarget');
        $('[data-action="save"]', $el).on('click', () => sprintTarget.save());
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
            $('.text-target').show();
        } else {
            $('.title-target').hide();
            $('.text-target').hide();
        }
    },
    open: function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addTarget')).show();
    },
    close: function () {
        const instance = bootstrap.Modal.getInstance(document.getElementById('addTarget'));
        if (instance) instance.hide();
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
