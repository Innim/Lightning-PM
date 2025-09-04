// Compact "Go to issue by #" widget logic
// Visible on project/issue/scrum pages; hidden elsewhere

(function () {

    function normalizeId(val) {
        if (!val) return null;
        var s = String(val).trim();
        // allow formats like "#123" or "123"
        s = s.replace(/[^0-9]/g, '');
        if (!s) return null;
        var n = parseInt(s, 10);
        return Number.isFinite(n) && n > 0 ? n : null;
    }

    function init() {
        var groups = document.querySelectorAll('.goto-issue-component .input-group[data-project-id]');
        if (!groups || groups.length === 0) return;

        groups.forEach(function(group){
            var projectId = parseInt(group.getAttribute('data-project-id'));
            var input = group.querySelector('.goto-issue-input');
            var btn = group.querySelector('.goto-issue-btn');

            function doNavigate() {
                var id = normalizeId(input.value);
                if (!id) {
                    showError('Укажите номер задачи');
                    return;
                }

                srv.issue.loadByIdInProject(id, projectId, function (res) {
                    if (res && res.success && res.issue && res.issue.url) {
                        redirectTo(res.issue.url);
                    } else {
                        srv.err(res || {});
                    }
                });
            }

            btn.addEventListener('click', function () { doNavigate(); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    doNavigate();
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    // Hide any open collapse parent if exists
                    var collapse = input.closest('.collapse');
                    if (collapse && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                        var c = bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false });
                        c.hide();
                    }
                    input.blur();
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
