$(document).ready(function () {
    passTest.init();
});

const passTest = {
    currentIssueId: null,
	saveableForm: null,
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
                ],
                close: function( event, ui ) {
                    passTest.saveableForm.clear();
                    passTest.currentIssueId = null;

                    $('#passTestComment').tabs({
                        active: 0
                    });
                    $('#addCommentForm .preview-comment').empty();
                }
            }
        );

        const issueId = typeof issuePage !== 'undefined' ? issuePage.getIssueId() : null;
		const storeKey = issueId ? 'pass-test-comment-' + issueId : 'pass-test-comment';
		passTest.saveableForm = new SaveableCommentForm(
			'#passTestComment .comment-text-field',
			null,
			storeKey,
			null
		);

        passTest.saveableForm.init((_text, _checkboxVal) => {
            passTest.show(issueId, false);
        });
    },
    show: function (issueId, autoText = true) {
        const $el = $("#passTestDialog");

        passTest.currentIssueId = issueId;

        if (autoText) {
            $('#passTestComment .comment-text-field', $el).val('**Прошла тестирование**\n\n');
        }

        $el.dialog('open');
    },
    close: function () {
        const $el = $("#passTestDialog");
        $el.dialog('close');
    },
    save: function () {
        const $el = $("#passTestDialog");

        const comment = $("#passTestComment .comment-text-field", $el).val();
        issuePage.doSomethingAndPostCommentForCurrentIssue(
            (issueId, handler) => srv.issue.passTest(issueId, comment.trim(), handler),
            res => {
                if (res.success)
                    passTest.close();
            });
    },
}