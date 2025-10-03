$(function () {
    createFromIssue.init();
});

const createFromIssue = {
    element: null,
    elements: {},
    projects: null,
    currentProjectId: null,
    currentIssueId: null,
    currentOnSuccess: null,
    currentOnProjectChanged: null,
    init: function () {
        // Cache both modal roots
        this.elements = {
            copy: $("#createFromIssueCopyModal"),
            finished: $("#createFromIssueFinishedModal"),
        };

        // Bind controls for each modal
        Object.keys(this.elements).forEach((key) => {
            const $el = this.elements[key];

            // Continue button
            $('[data-action="continue"]', $el).on('click', () => {
                this.save();
            });

            // Cancel button just closes (Bootstrap handles via data-bs-dismiss too)
            $('[data-action="cancel"]', $el).on('click', () => {
                this.close();
            });

            // Reset state when hidden
            $el.on('hidden.bs.modal', () => {
                // Ensure cleanup happens even if user closes via backdrop/ESC
                if ($el.is(this.element)) {
                    this._cleanupElement($el);
                    this._resetState();
                } else {
                    // If closing non-active element for any reason, still cleanup its fields
                    this._cleanupElement($el);
                }
            });

            // project select change handler
            const $selectProject = key === 'copy' ? $('#createFromIssueProjectCopy', $el) : $('#createFromIssueProjectFinished', $el);
            $selectProject.on('change', () => {
                if (this.currentOnProjectChanged) this.currentOnProjectChanged($selectProject.val());
            });
        });
    },
    show: function (projectId, issueId, onSuccess, mode, onProjectChanged) {
        const modalKey = mode || 'copy';
        const $el = this.elements[modalKey];
        this.element = $el;

        this.currentProjectId = projectId;
        this.currentIssueId = issueId;
        this.currentOnSuccess = onSuccess;
        this.currentOnProjectChanged = onProjectChanged;

        this.loadProjects((list) => {
            this.setProjects(list);
            const modal = bootstrap.Modal.getOrCreateInstance($el[0]);
            modal.show();
        });
    },
    close: function () {
        if (!this.element) return;
        const modal = bootstrap.Modal.getOrCreateInstance(this.element[0]);
        modal.hide();
    },
    _resetState: function () {
        this.currentProjectId = null;
        this.currentIssueId = null;
        this.currentOnSuccess = null;
        this.currentOnProjectChanged = null;
        this.element = null;
    },
    _cleanupElement: function ($el) {
        // Clear selects and fields within provided element
        $('#createFromIssueProjectCopy, #createFromIssueProjectFinished', $el).empty();
        $("#createFromIssueCopyLinks", $el).prop("checked", false);
    },
    save: function () {
        const $el = this.element;
        if (!$el) return;

        const $projectSelect = $('#createFromIssueProjectCopy, #createFromIssueProjectFinished', $el);
        const targetProjectId = $projectSelect.val();

        const onSuccess = this.currentOnSuccess;
        if (onSuccess) {
            onSuccess(this.projects.find(obj => obj.id == targetProjectId));
        }
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
        if (!$el) return;

        const $selectProject = $('#createFromIssueProjectCopy, #createFromIssueProjectFinished', $el);
        $selectProject.empty();

        if (list.length == 0) return;

        list.forEach(item => {
            $selectProject.append($("<option></option>")
                .attr("value", item.id).text(item.name));
        });

        $selectProject.val(this.currentProjectId);
        if (this.currentOnProjectChanged) this.currentOnProjectChanged($selectProject.val());
    },
}
