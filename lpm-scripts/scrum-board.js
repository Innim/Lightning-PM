$(document).ready(
    function () {
        document.querySelectorAll('.name-project').forEach(function (e) {
            if (e.scrollWidth > e.offsetWidth) {
                e.setAttribute('title', e.textContent)
            }
        });
    }
);

let scrumBoard = {
    changeScrumState: function (e) {
        const $control = $(e.currentTarget);
        const $sticker = $control.parents('.scrum-board-sticker');
        const issueId = $sticker.data('issueId');
        const curState = $sticker.data('stickerState');

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
            }
        });
    },
    takeIssue: function (e) {
        const $control = $(e.currentTarget);
        const $sticker = $control.parents('.scrum-board-sticker');
        const issueId = $sticker.data('issueId');
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
                    issuePage.scumColUpdateInfo();
                } else {
                    srv.err(res);
                }
            });
        }
    },
};

const ScrumStickerState = Object.freeze({
    backlog: 0,
    todo: 1,
    inProgress: 2,
    testing: 3,
    done: 4,
    archived: 5,
    deleted: 6,
});