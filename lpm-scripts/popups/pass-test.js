$(document).ready(function () {
    passTest.init();
});

const passTest = {
    currentIssueId: null,
    saveableForm: null,
    init: function () {
        const $el = $("#passTestDialog");

        $('[data-action="ok"]', $el).on('click', () => {
            passTest.save();
        });

        $el.on('hidden.bs.modal', () => {
            passTest.saveableForm.clear();
            passTest.currentIssueId = null;
            comments.clearFiles($el);

            $('#passTestComment').tabs({ active: 0 });
            $('.preview-comment', $el).empty();
        });

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

        bootstrap.Modal.getOrCreateInstance($el[0]).show();
    },
    close: function () {
        const $el = $("#passTestDialog");
        const instance = bootstrap.Modal.getInstance($el[0]);
        if (instance) instance.hide();
    },
    save: function () {
        const $el = $("#passTestDialog");

        const comment = $("#passTestComment .comment-text-field", $el).val();
        const files = comments.getFiles($el);
        issuePage.doSomethingAndPostCommentForCurrentIssue(
            (issueId, handler) => srv.issue.passTest(issueId, comment.trim(), files, handler),
            res => {
                if (res.success)
                    passTest.close();
            });
    },
}
