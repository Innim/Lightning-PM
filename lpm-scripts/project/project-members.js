$(() => {
    projectMembers.projectId = $('#projectMembers input[name=projectId]').val();

    $('#specMasters .spec-master-item .remove-link').on('click', function() { 
        const $item = $(this).parent('.spec-master-item');
        projectMembers.removeSpecMaster($item.data('userId'), $item.data('labelId'));
    });
    
    addSpecMaster.init();

    $('#specTesters .spec-tester-item .remove-link').on('click', function() { 
        const $item = $(this).parent('.spec-tester-item');
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
    element: null,
    init: function () {
        const $el = $(`#addSpecMember`).clone();
        this.element = $el;
        const self = this;

        $('.select-spec-member-label', $el).text(dialog.label);
        $el.dialog(
            {
                title: dialog.title,
                autoOpen: false,
                modal: true,
                resizable: false,
                buttons: [
                    {
                        text: "Добавить",
                        click: function () {
                            self.save();
                        }
                    },
                    {
                        text: "Отмена",
                        click: function () {
                            self.close();
                        }
                    }
                ]
            }
        );
    },
    show: function () {
        const $el = this.element;
        $el.dialog('open');
    },
    close: function () {
        const $el = this.element;
        $('.select-tag-for-spec-member', $el).val(0);
        $('.select-spec-member', $el).val(0);
        $el.dialog('close');
    },
    save: function () {
        const $el = this.element;
        const self = this;

        const labelId = $('.select-tag-for-spec-member', $el).val();
        const userId = $('.select-spec-member', $el).val();
        
        if (labelId <= 0) {
            messages.alert('Вы должны выбрать тег.')
        } else if (userId <= 0) {
            messages.alert('Вы должны выбрать пользователя.')
        } else {
            serviceMethod.call(srv.project, projectMembers.projectId, userId, labelId, res => {
                if (res.success) {
                    // TODO: добавить в список на лету
                    location.reload();
                    self.close();
                } else {
                    messages.alert(errors.addFailed)
                }
            });
        }
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