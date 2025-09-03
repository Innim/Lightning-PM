// Compact "Go to issue by #" widget logic
// Visible on project/issue/scrum pages; hidden elsewhere

(function () {
    function getProjectId() {
        // project list and completed issues
        var el = document.querySelector('#projectView[data-project-id]');
        if (el) return parseInt(el.getAttribute('data-project-id')) || null;

        // scrum board
        el = document.querySelector('#scrumBoard[data-project-id]');
        if (el) return parseInt(el.getAttribute('data-project-id')) || null;

        // issue page (hidden input in form)
        var hidden = document.querySelector('#issueProjectID');
        if (hidden) return parseInt(hidden.value) || null;

        return null;
    }

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
        var container = document.getElementById('gotoIssue');
        if (!container) return;

        var projectId = getProjectId();
        if (!projectId) {
            container.style.display = 'none';
            return;
        }

        container.style.display = '';

        var toggle = container.querySelector('.goto-issue-toggle');
        var input = container.querySelector('.goto-issue-input');
        var btn = container.querySelector('.goto-issue-btn');

        function doNavigate() {
            var id = normalizeId(input.value);
            if (!id) {
                if (typeof showError === 'function') showError('Укажите номер задачи');
                else alert('Укажите номер задачи');
                return;
            }

            srv.issue.loadByIdInProject(id, projectId, function (res) {
                if (res && res.success && res.issue && res.issue.url) {
                    window.location.href = res.issue.url;
                } else {
                    if (typeof srv !== 'undefined' && typeof srv.err === 'function') srv.err(res || {});
                    else if (typeof showError === 'function') showError('Задача не найдена или нет доступа');
                    else alert('Задача не найдена или нет доступа');
                }
            });
        }

        toggle.addEventListener('click', function () {
            container.classList.toggle('collapsed');
            if (!container.classList.contains('collapsed')) {
                setTimeout(function () { input.focus(); input.select(); }, 0);
            }
        });

        btn.addEventListener('click', function () { doNavigate(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doNavigate();
            }
            if (e.key === 'Escape') {
                container.classList.add('collapsed');
                input.blur();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

