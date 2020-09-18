$(document).ready(function () {
    passTest.init();
});

const passTest = {
    currentIssueId: null,
    init: function () {
        $("#passTestDialog").dialog(
            {
                autoOpen: false,
                modal: true,
                resizable: false,
                width: 700,
                buttons: [
                    {
                        text: "OK",
                        click: function () {
                            passTest.save();
                        }
                    },
                    {
                        text: "Отмена",
                        click: function () {
                            passTest.close();
                        }
                    }
                ]
            }
        );
    },
    show: function (issueId) {
        const $el = $("#passTestDialog");

        passTest.currentIssueId = issueId;

        $('#passTestComment', $el).val('Прошла тестирование\n\n');

        $el.dialog('open');
    },
    close: function () {
        passTest.currentIssueId = null;

        const $el = $("#passTestDialog");
        $("#passTestComment", $el).val('');
        $el.dialog('close');
    },
    save: function () {
        const $el = $("#passTestDialog");

        preloader.show();
        const comment = $("#passTestComment", $el).val();
        issuePage.doSomethingAndPostCommentForCurrentIssue(
            (issueId, handler) => srv.issue.passTest(issueId, comment.trim(), handler),
            res => {
                preloader.hide();
                if (res.success)
                    passTest.close();
            });
    },
}