$(function () {
    createBranch.init();
});

const createBranch = {
    currentProjectId: null,
    currentIssueId: null,
    init: function () {
        const $el = $("#createBranch");
        $el.dialog(
            {
                autoOpen: false,
                modal: true,
                resizable: false,
                buttons: [
                    {
                        text: "Создать",
                        click: function () {
                            createBranch.save();
                        }
                    },
                    {
                        text: "Отмена",
                        click: function () {
                            createBranch.close();
                        }
                    }
                ]
            }
        );

        const $selectRepo = $('#repository', $el);
        const $selectBranch = $('#parentBranch', $el);
        const $branchName = $('#branchName', $el);

        $selectRepo.on('change', () => {
            const repoId = $selectRepo.val();
            createBranch.onSelectRepository(repoId);
        });

        $selectBranch.on('change', () => {
            $branchName.trigger('focus');
        });

        $branchName.on('input', () => {
            const el = $branchName[0];
            const selectionStart = el.selectionStart;
            const name = $branchName.val();
            const res = name.replace(/[_ ]/, '-').toLowerCase();

            if (name != res) {
                $branchName.val(res);
                el.selectionStart = selectionStart;
                el.selectionEnd = selectionStart;
            }
        });
    },
    show: function (projectId, issueId, issueIdInProject) {
        const $el = $("#createBranch");

        createBranch.currentProjectId = projectId;
        createBranch.currentIssueId = issueId;

        preloader.show();
        // TODO: грузить только 1 раз?
        srv.project.getRepositories(projectId, (res) => {
            preloader.hide();
            if (res.success) {
                $el.dialog('open');
                createBranch.setRepositories(res.list, res.popularRepositoryId, res.myPopularRepositoryIds);
                $("#branchName", $el).val(issueIdInProject + '.').trigger('focus');
            } else {
                createBranch.close();
                showError(res.error ?? 'Не удалось получить список репозиториев');
            }
        });
    },
    close: function () {
        createBranch.currentProjectId = null;
        createBranch.currentIssueId = null;

        const $el = $("#createBranch");
        $("#branchName", $el).val('');
        $("#repository", $el).empty();
        $("#parentBranch", $el).empty();
        $el.dialog('close');
    },
    save: function () {
        const $el = $("#createBranch");

        const branchName = $("#branchName", $el).val();
        const repoId = $("#repository", $el).val();
        const parentBranch = $("#parentBranch", $el).val();

        issuePage.doSomethingAndPostCommentForCurrentIssue(
            (issueId, handler) => srv.issue.createBranch(issueId, branchName, repoId, parentBranch, handler),
            res => {
                createBranch.close();
                if (res.issue) {
                    setIssueInfo(new Issue(res.issue));
                }
            });
    },
    setRepositories: function (list, popularRepositoryId, myPopularRepositoryIds) {
        const $el = $("#createBranch");
        const $selectRepo = $('#repository', $el);
        $selectRepo.empty();

        if (list.length == 0) return;

        // Перебираем теги, и если какой-то тег совпадает со словом в имени репозитория
        // то предлагаем его
        const labels = issuePage.labels;
        var repoId;
        const lastActivity = list.reduce((val, item) => !val || val < item.lastActivity ? item.lastActivity : val, 0);

        // Ставим выше активные
        const outdatedSec = 30 * 24 * 3600;
        list.sort((a, b) => {
            const aOutdated = lastActivity - a.lastActivity > outdatedSec;
            const bOutdated = lastActivity - b.lastActivity > outdatedSec;

            if (aOutdated != bOutdated) return bOutdated ? -1 : 1;

            return b.name.localeCompare(a.name);
        });
        
        const appropriateRepos = [];
        list.forEach(item => {
            if (item.name.split(' ').some(e => labels.includes(e))) {
                appropriateRepos.push(item.id);
            }

            $selectRepo.append($("<option></option>")
                .attr("value", item.id).text(item.name));
        });

        if (appropriateRepos.length == 1 || !myPopularRepositoryIds) {
            repoId = appropriateRepos[0];
        } else if (appropriateRepos.length > 1) {
            repoId = myPopularRepositoryIds.find(id => appropriateRepos.indexOf(id) !== -1);
            if (repoId === undefined) repoId = appropriateRepos[0];
        } else {
            repoId = list.some(r => r.id == popularRepositoryId) ? popularRepositoryId : list[0].id;
        }

        $selectRepo.val(repoId);
        createBranch.onSelectRepository(repoId);
    },
    onSelectRepository: function (repoId) {
        const projectId = createBranch.currentProjectId;
        if (projectId == null) return;

        // TODO: грузить только 1 раз?
        preloader.show();
        srv.project.getBranches(projectId, repoId, (res) => {
            preloader.hide();
            if (res.success) {
                const $el = $("#createBranch");
                const $selectParent = $('#parentBranch', $el);
                $selectParent.empty();
                // Первой предлагаем develop - выбор по умолчанию
                // потом master
                // потом main
                // потом все остальные рутовые ветки
                // и только потом прочие, по алфавиту
                const priorityBranches = ['develop', 'master', 'main'];
                res.list.sort((a, b) => {
                    const aName = a.name;
                    const bName = b.name;

                    for (var i = 0; i < priorityBranches.length; i++) {
                        const priorityName = priorityBranches[i];
                        if (aName == priorityName) return -1;
                        if (bName == priorityName) return 1;
                    }

                    const isARoot = !aName.includes('/');
                    const isBRoot = !bName.includes('/');

                    if (isARoot != isBRoot) return isARoot ? -1 : 1;
                    return aName.localeCompare(bName);
                });
                res.list.forEach(item => {
                    $selectParent.append($("<option></option>")
                        .attr("value", item.name).text(item.name));
                });

                $("#branchName", $el).trigger('focus');
            } else {
                createBranch.close();
                showError(res.error ?? 'Не удалось получить список репозиториев');
            }
        });
    },
}