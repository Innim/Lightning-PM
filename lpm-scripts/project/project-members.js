$(() => {
    projectMembers.projectId = $('#projectMembers input[name=projectId]').val();

    $('#specMasters .spec-master-item .remove-link').on('click', function() { 
        const $item = $(this).closest('.spec-master-item');
        projectMembers.removeSpecMaster($item.data('userId'), $item.data('labelId'));
    });
    
    addSpecMaster.init();

    $('#specTesters .spec-tester-item .remove-link').on('click', function() { 
        const $item = $(this).closest('.spec-tester-item');
        projectMembers.removeSpecTester($item.data('userId'), $item.data('labelId'));
    });
    addSpecTester.init();
});

const projectMembers = {
    projectId: null,
    removeSpecMaster: function (userId, labelId) {
        preloader.show();
        srv.project.deleteSpecMaster(projectMembers.projectId, userId, labelId, res => {
            preloader.hide();
            if (res.success) {
                $('#specMasters .spec-master-item[data-user-id=' + userId + '][data-label-id=' + labelId + ']').remove();
            } else {
                messages.alert('Не удалось удалить мастера')
            }
        });
    },
    removeSpecTester: function (userId, labelId) {
        preloader.show();
        srv.project.deleteSpecTester(projectMembers.projectId, userId, labelId, res => {
            preloader.hide();
            if (res.success) {
                $('#specTesters .spec-tester-item[data-user-id=' + userId + '][data-label-id=' + labelId + ']').remove();
            } else {
                messages.alert('Не удалось удалить тестера')
            }
        });
    },
};

const specMembers = (serviceMethod, dialog, errors) => ({
    contentHtml: null,
    init: function () {
        // cache template content for quicker show
        this.contentHtml = $('#addSpecMember').html();
    },
    show: function () {
        const dlgId = 'addSpecMember-' + Date.now();
        const content = '<div id="' + dlgId + '">' + this.contentHtml + '</div>';

        // Show Bootstrap modal using shared helper
        lpm.dialog.show({
            title: dialog.title,
            content: content,
            primaryBtn: 'Добавить',
            onPrimary: function () {
                const $root = $('#' + dlgId);
                console.log($root);
                const labelId = $('.select-tag-for-spec-member', $root).val();
                const userId = $('.select-spec-member', $root).val();

                console.log(labelId, userId);
                if (labelId <= 0) {
                    messages.alert('Вы должны выбрать тег.');
                    return;
                }
                if (userId <= 0) {
                    messages.alert('Вы должны выбрать пользователя.');
                    return;
                }

                serviceMethod.call(srv.project, projectMembers.projectId, userId, labelId, res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        messages.alert(errors.addFailed);
                    }
                });
            },
        });

        // adjust label after content injected
        const $root = $('#' + dlgId);
        $('.select-spec-member-label', $root).text(dialog.label);
        // ensure defaults
        $('.select-tag-for-spec-member', $root).val(0);
        $('.select-spec-member', $root).val(0);
    },
});

const addSpecMaster = specMembers(
    srv.project.addSpecMaster, 
    {
        title: 'Добавить мастера по тегу',
        label: 'Мастер',
    },
    {
        addFailed: 'Не удалось добавить мастера',
    },
);
const addSpecTester = specMembers(
    srv.project.addSpecTester, 
    {
        title: 'Добавить тестировщика по тегу',
        label: 'Тестер',
    },
    {
        addFailed: 'Не удалось добавить тестировщика'
    },
);
