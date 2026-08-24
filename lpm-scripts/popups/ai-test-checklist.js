// Диалог чек-листа тестирования. Отображается через общий lpm.dialog
// (а не отдельное модальное окно), поэтому ошибки показываются внутри диалога,
// без вложенных окон.
// Диалог двухшаговый: одна и та же кнопка футера сначала собирает черновик,
// потом публикует его комментарием. Черновик нигде не хранится — он живёт
// только в открытом диалоге.
const aiTestChecklist = {
    // Есть ли на странице разметка диалога: чек-лист включается флагом проекта,
    // а issues.js грузится и на списках задач, и на scrum доске.
    isAvailable: function () {
        return document.getElementById('aiTestChecklistContent') !== null;
    },
    show: function () {
        const tpl = document.getElementById('aiTestChecklistContent');
        if (!tpl) return;

        lpm.dialog.show({
            title: 'Чек-лист тестирования',
            content: tpl.innerHTML,
            primaryBtn: 'Составить чек-лист',
            // Закрыть можно крестиком, отдельная кнопка только путала бы
            // с основным действием.
            secondaryBtn: null,
            onPrimary: () => {
                this._onPrimary();
                // Окно закрывается только после успешной публикации.
                return false;
            },
        });

        // lpm.dialog не умеет задавать размер окна, а в узком текст чек-листа
        // не читается. У копии внутри диалога есть .modal-dialog, у скрытого
        // шаблона — нет, поэтому лишнего не заденем.
        $('.ai-test-checklist-body').closest('.modal-dialog').addClass('modal-lg');
    },
    close: function () {
        const el = document.querySelector('.modal.show');
        if (el) bootstrap.Modal.getOrCreateInstance(el).hide();
    },
    // Ищем элементы внутри активного диалога: скрытый шаблон
    // #aiTestChecklistContent остаётся в DOM с теми же id, поэтому глобальные
    // селекторы попали бы в него.
    _$: function (sel) {
        return $('.modal.show').find(sel);
    },
    _btn: function () {
        return this._$('.modal-footer .btn-primary');
    },
    _onPrimary: function () {
        if (this._$('.ai-test-checklist-draft').is(':visible')) {
            this._publish();
        } else {
            this._generate();
        }
    },
    _generate: function () {
        const $btn = this._btn();
        if ($btn.prop('disabled')) return;

        this._clearError();
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
            + 'Составляем чек-лист…'
        );

        srv.ai.issueTestChecklist(issuePage.getIssueId(), (res) => {
            if (!res.success) {
                $btn.prop('disabled', false).text('Составить чек-лист');
                this._showError(res.error || 'Не удалось составить чек-лист');
                return;
            }

            this._$('.ai-test-checklist-intro').hide();
            this._$('.ai-test-checklist-draft').show();
            this._$('#aiTestChecklistText').val(res.text);

            if (res.published) {
                this._$('.ai-test-checklist-published').show();
            }

            $btn.prop('disabled', false).text('Опубликовать комментарием');
        });
    },
    _publish: function () {
        const $btn = this._btn();
        if ($btn.prop('disabled')) return;

        const text = this._$('#aiTestChecklistText').val().trim();
        if (!text) {
            this._showError('Чек-лист пуст');
            return;
        }

        this._clearError();
        $btn.prop('disabled', true);

        srv.issue.postTestChecklist(issuePage.getIssueId(), text, (res) => {
            if (!res.success) {
                $btn.prop('disabled', false);
                this._showError(res.error || 'Не удалось опубликовать чек-лист');
                return;
            }

            issuePage.addComment(res.comment, res.html);
            if (res.linkedHtml) issuePage.updateLinkedIssues(res.linkedHtml);

            this._collapseLink();
            this.close();
            lpm.toast.show('Чек-лист опубликован');
        });
    },
    // После первой публикации ссылка перестаёт быть акцентной и сжимается
    // до иконки: действие ещё доступно, но место в панели не занимает.
    _collapseLink: function () {
        const label = 'Составить чек-лист тестирования';
        $('#issueView .ai-checklist-link')
            .removeClass('text-primary')
            .attr('title', label)
            .attr('aria-label', label)
            .find('.ai-checklist-link-text').addClass('d-none');
    },
    _showError: function (msg) {
        this._$('.ai-test-checklist-error').text(msg).show();
    },
    _clearError: function () {
        this._$('.ai-test-checklist-error').hide().text('');
    },
};
