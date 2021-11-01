$(function () {
    selectProject.init();
});

const selectProject = {
    element: null,
    projects: null,
    currentProjectId: null,
    currentIssueId: null,
    currentOnSuccess: null,
    currentModeClass: null,
    currentOnProjectChanged: null,
    init: function () {
        const $el = $("#selectProjectPopup");
        this.element = $el;
        $el.dialog(
            {
                autoOpen: false,
                modal: true,
                resizable: false,
                buttons: [
                    {
                        text: "Продолжить",
                        click: function () {
                            selectProject.save();
                        }
                    },
                    {
                        text: "Отмена",
                        click: function () {
                            selectProject.close();
                        }
                    }
                ]
            }
        );
        
        const $selectProject = $("#projectField", $el);
        $selectProject.on('change', () => {
            if (selectProject.currentOnProjectChanged) selectProject.currentOnProjectChanged($selectProject.val());
        });
    },
    show: function (projectId, issueId, onSuccess, mode, onProjectChanged) {
        const $el = this.element;
        if (mode) {
            this.currentModeClass = mode + '-mode';
            $el.addClass(this.currentModeClass);
        }

        this.currentProjectId = projectId;
        this.currentIssueId = issueId;
        this.currentOnSuccess = onSuccess;
        this.currentOnProjectChanged = onProjectChanged;

        this.loadProjects((list) => {
            $el.dialog('open');
            this.setProjects(list);
        });
    },
    close: function () {
        this.currentProjectId = null;
        this.currentIssueId = null;
        this.currentOnSuccess = null;
        this.currentOnProjectChanged = null;

        const $el = this.element;
        if (this.currentModeClass) {
            $el.removeClass(this.currentModeClass);
            this.currentModeClass = null;
        }
        $("#projectField", $el).empty();
        $("#copyLinkedIssuesField", $el).prop("checked", false);
        $el.dialog('close');
    },
    save: function () {
        const $el = this.element;
        const targetProjectId = $("#projectField", $el).val();

        const onSuccess = this.currentOnSuccess;

        onSuccess(this.projects.find(obj => obj.id == targetProjectId));
        
        this.close();
    },
    loadProjects: function (onSuccess) {
        if (this.projects == null)  {
            preloader.show();
            
            srv.projects.getList((res) => {
                preloader.hide();
                if (res.success) {
                    this.projects = res.list;
                    onSuccess(res.list);
                } else {
                    this.close();
                    showError(res.error ?? 'Не удалось получить список проектов');
                }
            });
        } else {
            onSuccess(this.projects);
        }
    },
    setProjects: function (list) {
        const $el = this.element;
        const $selectProject = $('#projectField', $el);
        $selectProject.empty();

        if (list.length == 0) return;

        list.forEach(item => {
            $selectProject.append($("<option></option>")
                .attr("value", item.id).text(item.name));
        });

        $selectProject.val(this.currentProjectId);
        if (selectProject.currentOnProjectChanged) selectProject.currentOnProjectChanged($selectProject.val());
    },
}