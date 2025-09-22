const issuesExport2Excel = {
    projectId: null,
    openWindow: function (projectId) {
        this.projectId = projectId;

        const tpl = document.getElementById('issuesExportToExcel');
        const title = (tpl && tpl.getAttribute('title')) || 'Экспорт в Excel';
        const content = tpl ? tpl.innerHTML : '';

        lpm.dialog.show({
            title: title,
            content: content,
            primaryBtn: 'Экспорт',
            secondaryBtn: 'Отмена',
            onPrimary: function () {
                const modal = document.querySelector('.modal.show');
                const form = modal ? modal.querySelector('#issuesExportForm') : document.getElementById('issuesExportForm');
                const fromDate = form ? form.querySelector('input[name=fromDate]').value : '';
                const toDate = form ? form.querySelector('input[name=toDate]').value : '';

                if (!fromDate || !toDate) {
                    messages.alert('Необходимо указать период для выгрузки.');
                    return;
                }

                const projectId = issuesExport2Excel.projectId;
                if (!projectId) return;

                preloader.show();
                srv.issue.exportCompletedIssuesToExcel(
                    projectId,
                    fromDate,
                    toDate,
                    function (res) {
                        preloader.hide();
                        if (res.success) {
                            window.open(res.fileUrl, '_blank');
                        } else {
                            srv.err(res);
                        }
                    }
                );
            },
            onSecondary: function () { issuesExport2Excel.projectId = null; }
        });
    }
};
