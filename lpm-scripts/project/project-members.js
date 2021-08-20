$(() => {
    projectMembers.projectId = $('#projectMembers input[name=projectId]').val();
    addSpecMaster.init();
});

const projectMembers = {
    projectId: null,
};

const addSpecMaster = {
    element: null,
    init: function () {
        const $el = $("#addSpecMaster");
        this.element = $el;
        $el.dialog(
            {
                autoOpen: false,
                modal: true,
                resizable: false,
                buttons: [
                    {
                        text: "Добавить",
                        click: function () {
                            addSpecMaster.save();
                        }
                    },
                    {
                        text: "Отмена",
                        click: function () {
                            addSpecMaster.close();
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
        $("#selectTagForSpecMaster", $el).val(0);
        $("#selectSpecMaster", $el).val(0);
        $el.dialog('close');
    },
    save: function () {
        const $el = this.element;

        const labelId = $("#selectTagForSpecMaster", $el).val();
        const userId = $("#selectSpecMaster", $el).val();
        
        if (labelId <= 0) {
            messages.alert('Вы должны выбрать тег.')
        } else if (userId <= 0) {
            messages.alert('Вы должны выбрать пользователя.')
        } else {
            srv.project.addSpecMaster(projectMembers.projectId, userId, labelId, res => {
                if (res.success) {
                    // TODO: добавить в список на лету
                    location.reload();

                    addSpecMaster.close();
                } else {
                    messages.alert('Не удалось добавить мастера')
                }
            });
        }
    },
}