$(function () {
    addIssueLink.init();
});

// Диалог связывания задачи. Отображается через общий lpm.dialog (а не отдельное
// модальное окно), поэтому ошибки показываются внутри диалога, без вложенных окон.
// Одно поле: номер/название ищется в текущем проекте, ссылка связывает задачу из
// любого доступного проекта.
const addIssueLink = {
    projectId: null,
    issueId: null,
    onSuccess: null,
    searchTimer: null,
    init: function () {
        // Содержимое диалога добавляется в DOM динамически, поэтому обработчики
        // вешаем делегированно на document.
        $(document)
            .on('input', '#addIssueLinkInput', (e) => {
                clearTimeout(this.searchTimer);
                const text = $(e.currentTarget).val().trim();
                this.searchTimer = setTimeout(() => this._onInput(text), 250);
            })
            .on('click', '.add-issue-link-results button', (e) => {
                const $btn = $(e.currentTarget);
                const url = $btn.data('url');
                if (url) {
                    this._addByUrl(url);
                } else {
                    this._addById($btn.data('id'));
                }
            })
            .on('keydown', '#addIssueLinkInput', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this._submit($(e.currentTarget).val().trim());
                }
            });
    },
    show: function (projectId, issueId, onSuccess) {
        this.projectId = projectId;
        this.issueId = issueId;
        this.onSuccess = onSuccess;
        clearTimeout(this.searchTimer);

        const tpl = document.getElementById('addIssueLinkContent');
        lpm.dialog.show({
            title: 'Связать задачу',
            content: tpl ? tpl.innerHTML : '',
            primaryBtn: null,
            secondaryBtn: 'Закрыть',
        });
    },
    close: function () {
        const el = document.querySelector('.modal.show');
        if (el) bootstrap.Modal.getOrCreateInstance(el).hide();
    },
    // Ищем поля внутри активного диалога: скрытый шаблон #addIssueLinkContent
    // остаётся в DOM с теми же id, поэтому глобальные селекторы попали бы в него.
    _$: function (sel) {
        return $('.modal.show').find(sel);
    },
    _looksLikeUrl: function (text) {
        return /^https?:\/\//i.test(text) || /\/issue\//.test(text);
    },
    _isIssueUrl: function (text) {
        try {
            return new RegExp('^' + lpmOptions.issueUrlPattern + '$').test(text);
        } catch (e) {
            return false;
        }
    },
    _onInput: function (text) {
        this._clearError();
        if (!text) {
            this._renderResults([]);
            return;
        }

        // #12 — точный поиск по номеру задачи в текущем проекте.
        if (text.charAt(0) === '#') {
            this._searchByNumber(text.slice(1).trim());
            return;
        }

        if (this._looksLikeUrl(text)) {
            if (this._isIssueUrl(text)) {
                this._renderUrlOption(text);
            } else {
                this._renderResults([]);
                this._showError('Некорректная ссылка на задачу');
            }
            return;
        }

        srv.project.searchIssueNames(this.projectId, text, (res) => {
            if (res.success) {
                this._renderResults(res.list.filter((e) => e.id != this.issueId));
            } else {
                this._showError(res.error || 'Не удалось выполнить поиск');
            }
        });
    },
    _searchByNumber: function (num) {
        if (!/^\d+$/.test(num)) {
            this._renderResults([]);
            return;
        }

        srv.issue.loadByIdInProject(num, this.projectId, (res) => {
            if (res.success && res.issue && res.issue.id != this.issueId) {
                this._renderResults([{
                    id: res.issue.id,
                    idInProject: res.issue.idInProject,
                    name: res.issue.name,
                }]);
            } else {
                this._renderResults([]);
            }
        });
    },
    _submit: function (text) {
        if (!text) return;

        // Прямая ссылка на задачу — связываем сразу (с той же проверкой ссылки).
        if (text.charAt(0) !== '#' && this._looksLikeUrl(text)) {
            if (this._isIssueUrl(text)) {
                this._addByUrl(text);
            } else {
                this._showError('Некорректная ссылка на задачу');
            }
            return;
        }

        // Иначе активируем первый результат поиска / точного номера, если он есть.
        const $first = this._$('.add-issue-link-results button').first();
        if ($first.length) $first.trigger('click');
    },
    _renderUrlOption: function (url) {
        const $results = this._$('.add-issue-link-results');
        $results.empty();

        $('<button type="button" class="list-group-item list-group-item-action"></button>')
            .attr('data-url', url)
            .html('<i class="fa fa-link fa-xs me-1" aria-hidden="true"></i>Связать задачу по ссылке')
            .appendTo($results);
        $results.show();
    },
    _renderResults: function (list) {
        const $results = this._$('.add-issue-link-results');
        $results.empty();

        if (!list || list.length === 0) {
            $results.hide();
            return;
        }

        list.forEach((item) => {
            $('<button type="button" class="list-group-item list-group-item-action"></button>')
                .attr('data-id', item.id)
                .text('#' + item.idInProject + ' ' + item.name)
                .appendTo($results);
        });
        $results.show();
    },
    _addById: function (id) {
        this._clearError();
        preloader.show();
        srv.issue.addLink(this.issueId, id, (res) => this._handleResult(res));
    },
    _addByUrl: function (url) {
        if (!url) return;

        this._clearError();
        preloader.show();
        srv.issue.addLinkByUrl(this.issueId, url, (res) => this._handleResult(res));
    },
    _handleResult: function (res) {
        preloader.hide();
        if (res.success) {
            if (this.onSuccess) this.onSuccess(res);
            this.close();
            lpm.toast.show('Задача связана');
        } else {
            this._showError(res.error || 'Не удалось связать задачу');
        }
    },
    _showError: function (msg) {
        this._$('.add-issue-link-error').text(msg).show();
    },
    _clearError: function () {
        this._$('.add-issue-link-error').hide().text('');
    },
};
