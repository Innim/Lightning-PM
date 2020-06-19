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
                    issuePage.scumColUpdateInfo();
                } else {
                    srv.err(res);
                }
            });
        }
    },
};