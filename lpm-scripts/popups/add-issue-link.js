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
    // Номер последнего запущенного поиска. Ответы приходят в произвольном
    // порядке, поэтому список перерисовывает только ответ на текущий запрос:
    // иначе ответ на недонабранный номер затирал бы готовый результат.
    searchGen: 0,
    // Поиск запущен и ещё не ответил.
    searchPending: false,
    // Enter нажат до того, как пришли результаты: связываем найденное,
    // как только поиск ответит.
    submitOnResults: false,
    // Результаты последнего завершившегося поиска и поколение запроса, которому
    // они принадлежат. Enter решает по ним, а не по разметке: нарисованный
    // список может относиться к уже устаревшему тексту поля.
    results: [],
    resultsGen: -1,
    // Счётчик копий шаблона — из него берутся уникальные id полей диалога.
    contentSeq: 0,
    // Ответ сервиса на запрос по номеру, означающий «задачи с таким номером нет».
    // Сервис не отличает причины отказа кодом (errno всегда 0), поэтому отказ
    // по этой причине опознаётся по тексту: остальные (нет прав, сбой) должны
    // дойти до человека ошибкой, а не подсказкой про ссылку.
    NOT_FOUND_ERROR: 'Нет такой задачи',
    init: function () {
        // Содержимое диалога добавляется в DOM динамически, поэтому обработчики
        // вешаем делегированно на document. Поле ищем по классу, а не по id:
        // id у поля в каждой копии шаблона свой ({@see _buildContent}).
        $(document)
            .on('input', '.add-issue-link-input', (e) => {
                clearTimeout(this.searchTimer);

                // Текст изменился — прежние результаты больше ему не отвечают.
                // Со списка их не убираем (иначе он мигал бы на каждый символ):
                // Enter смотрит на поколение, а не на разметку, а ответ прежнего
                // запроса отбросит проверка поколения в _finishSearch.
                this.searchGen++;
                this.searchPending = false;
                this.submitOnResults = false;

                const text = $(e.currentTarget).val().trim();
                this.searchTimer = setTimeout(() => {
                    this.searchTimer = null;
                    this._onInput(text);
                }, 250);
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
            .on('keydown', '.add-issue-link-input', (e) => {
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
        this.searchTimer = null;
        this.searchPending = false;
        this.submitOnResults = false;
        this.searchGen++;
        this.results = [];
        this.resultsGen = -1;

        const tpl = document.getElementById('addIssueLinkContent');
        lpm.dialog.show({
            title: 'Связать задачу',
            content: tpl ? this._buildContent(tpl) : '',
            primaryBtn: null,
            secondaryBtn: 'Закрыть',
        });
    },
    close: function () {
        const el = document.querySelector('.modal.show');
        if (el) bootstrap.Modal.getOrCreateInstance(el).hide();
    },
    // Готовит содержимое диалога из скрытого шаблона #addIssueLinkContent.
    // Шаблон остаётся в DOM со своими id, поэтому в копии все id получают свой
    // суффикс: при двух одинаковых id и getElementById, и label[for] попадают
    // в скрытый шаблон, а не в открытый диалог, и подпись не ставит фокус
    // в настоящее поле. Класс add-issue-link-input, за который цепляются
    // обработчики, от номера копии не зависит.
    _buildContent: function (tpl) {
        const $content = $('<div></div>').html(tpl.innerHTML);
        const suffix = '-' + (++this.contentSeq);

        $content.find('#addIssueLinkInput').addClass('add-issue-link-input');
        $content.find('[id]').each(function () {
            const oldId = this.id;
            $content.find('label[for="' + oldId + '"]').attr('for', oldId + suffix);
            this.id = oldId + suffix;
        });

        return $content.html();
    },
    // Ищем поля внутри активного диалога: скрытый шаблон #addIssueLinkContent
    // остаётся в DOM с теми же классами, поэтому глобальные селекторы попали бы в него.
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
        const gen = ++this.searchGen;
        this.searchPending = true;

        if (!text) {
            this._finishSearch(gen, () => []);
            return;
        }

        // #12 — точный поиск по номеру задачи в текущем проекте.
        if (text.charAt(0) === '#') {
            this._searchByNumber(gen, text.slice(1).trim());
            return;
        }

        if (this._looksLikeUrl(text)) {
            this._finishSearch(gen, () => {
                if (this._isIssueUrl(text)) {
                    // Ключ linkUrl, а не url: у найденных по названию задач
                    // тоже есть url, и по нему эти два вида не различить.
                    return [{ linkUrl: text }];
                }

                this._showError('Некорректная ссылка на задачу');
                return [];
            });
            return;
        }

        srv.project.searchIssueNames(this.projectId, text, (res) => {
            this._finishSearch(gen, () => {
                if (!res.success) {
                    this._showError(res.error || 'Не удалось выполнить поиск');
                    return [];
                }

                const list = res.list.filter((e) => e.id != this.issueId);

                // Ничего не нашлось, а введены одни цифры — человек искал
                // по номеру, и ему нужно объяснить, почему список пуст.
                if (list.length === 0 && /^\d+$/.test(text)) {
                    if (res.list.some((e) => e.id == this.issueId)) {
                        this._showHint('Это номер текущей задачи.');
                    } else {
                        this._showNotFoundHint(text);
                    }
                }

                return list;
            });
        });
    },
    _searchByNumber: function (gen, num) {
        if (!/^\d+$/.test(num)) {
            this._finishSearch(gen, () => []);
            return;
        }

        srv.issue.loadByIdInProject(num, this.projectId, (res) => {
            this._finishSearch(gen, () => {
                if (res.success && res.issue && res.issue.id != this.issueId) {
                    return [{
                        id: res.issue.id,
                        idInProject: res.issue.idInProject,
                        name: res.issue.name,
                    }];
                }

                if (res.success) {
                    this._showHint('Это номер текущей задачи.');
                } else if (res.error === this.NOT_FOUND_ERROR) {
                    this._showNotFoundHint(num);
                } else {
                    this._showError(res.error || 'Не удалось выполнить поиск');
                }

                return [];
            });
        });
    },
    // Завершение поиска: ответ на устаревший запрос ничего не меняет.
    // resolve() рисует сообщение, если оно нужно, и возвращает найденное.
    _finishSearch: function (gen, resolve) {
        if (gen !== this.searchGen) {
            return;
        }

        this.searchPending = false;
        this._clearError();

        this.results = resolve() || [];
        this.resultsGen = gen;
        this._renderResults(this.results);

        if (this.submitOnResults) {
            this.submitOnResults = false;
            this._activateSingle();
        }
    },
    // Enter связывает задачу, только когда совпадение одно: из нескольких
    // выбирает человек, иначе связалась бы задача, которую он не выбирал.
    _activateSingle: function () {
        if (this.results.length !== 1) {
            return;
        }

        const item = this.results[0];
        if (item.linkUrl) {
            this._addByUrl(item.linkUrl);
        } else {
            this._addById(item.id);
        }
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

        // Результаты относятся к тому, что сейчас в поле — решаем по ним.
        if (this.resultsGen === this.searchGen) {
            this._activateSingle();
            return;
        }

        // Иначе поиск ещё не ответил, и нажатие не должно пропасть: отложенный
        // поиск запускаем немедленно, уже запущенный — дожидаемся.
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
            this.searchTimer = null;
            this.submitOnResults = true;
            this._onInput(text);
        } else if (this.searchPending) {
            this.submitOnResults = true;
        }
    },
    _renderResults: function (list) {
        const $results = this._$('.add-issue-link-results');
        $results.empty();

        if (!list || list.length === 0) {
            $results.hide();
            return;
        }

        list.forEach((item) => {
            const $btn = $('<button type="button" class="list-group-item list-group-item-action"></button>');
            if (item.linkUrl) {
                $btn.attr('data-url', item.linkUrl)
                    .html('<i class="fa fa-link fa-xs me-1" aria-hidden="true"></i>Связать задачу по ссылке');
            } else {
                $btn.attr('data-id', item.id)
                    .text('#' + item.idInProject + ' ' + item.name);
            }
            $btn.appendTo($results);
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
    // Поиск по номеру идёт только по текущему проекту: номера задач в проектах
    // повторяются, поэтому искать номер по всем проектам нечем.
    _showNotFoundHint: function (num) {
        this._showHint('В этом проекте нет задачи #' + num
            + ' — задачу из другого проекта можно связать, вставив ссылку на неё.');
    },
    _showError: function (msg) {
        this._alert().removeClass('alert-secondary').addClass('alert-danger').text(msg).show();
    },
    _showHint: function (msg) {
        this._alert().removeClass('alert-danger').addClass('alert-secondary').text(msg).show();
    },
    _clearError: function () {
        this._alert().removeClass('alert-secondary').addClass('alert-danger').hide().text('');
    },
    _alert: function () {
        return this._$('.add-issue-link-error');
    },
};
