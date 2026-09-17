/**
 * Автообновление Scrum доски проекта.
 *
 * Пока доска открыта, периодически спрашивает сервер, изменилось ли её
 * состояние, и переносит изменения на страницу без перезагрузки.
 *
 * Тик отдаёт только отпечаток состояния, поэтому на неизменившейся доске
 * стоит копейки; разметка приезжает, лишь когда отпечаток разошёлся с тем,
 * что уже отрисовано. Приехавшая разметка не заменяет доску целиком:
 * заменяются только стикеры, разметка которых действительно изменилась,
 * — остальные остаются теми же узлами DOM и не двигаются.
 */
document.addEventListener('DOMContentLoaded', () => scrumBoardAutoRefresh.init());

const scrumBoardAutoRefresh = {
    /** Интервал опроса, мс. */
    POLL_INTERVAL: 15000,
    /** Потолок интервала при идущих подряд неудачах, мс. */
    MAX_POLL_INTERVAL: 120000,
    /** После скольких неудач подряд доска признаётся возможно устаревшей. */
    FAILURES_BEFORE_WARNING: 2,

    _board: null,
    _warning: null,
    _projectId: 0,
    /** Отпечаток состояния, которое сейчас отрисовано. */
    _digest: '',
    /**
     * Разметка стикеров в том виде, в каком её прислал сервер, по id задачи.
     * Сравнивать с живым узлом нельзя: показанная подсказка переносит `title`
     * в `data-bs-original-title`, и узел перестаёт совпадать с исходной
     * разметкой, хотя ничего не изменилось.
     */
    _html: {},
    _timer: null,
    _requesting: false,
    _mouseDown: false,
    _failures: 0,

    init: function () {
        this._board = document.getElementById('scrumBoard');
        if (!this._board || !this._board.querySelector('.scrum-board-table')) {
            return;
        }

        this._projectId = parseInt(this._board.dataset.projectId, 10);
        this._digest = this._board.dataset.boardDigest || '';
        this._warning = document.getElementById('scrumBoardStale');
        this.captureHtml(this._board);

        if (this._warning) {
            this._warning.querySelector('[data-action="refresh"]')
                .addEventListener('click', () => this.schedule(0));
        }

        // Отпускание кнопки ловим на окне: отпустить могли и вне доски.
        this._board.addEventListener('mousedown', () => this._mouseDown = true);
        window.addEventListener('mouseup', () => this._mouseDown = false);

        // На вкладке в фоне обновляться незачем, но вернувшийся пользователь
        // должен увидеть актуальную доску сразу, а не через интервал
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.schedule(0);
        });
        window.addEventListener('online', () => this.schedule(0));

        this.schedule(this.POLL_INTERVAL);
    },

    /**
     * Ставит следующий опрос через заданное время.
     * @param {Number} delay задержка, мс
     */
    schedule: function (delay) {
        clearTimeout(this._timer);
        this._timer = setTimeout(() => this.tick(), delay);
    },

    tick: function () {
        if (this._requesting) {
            return;
        }

        if (document.hidden || this.isUserBusy()) {
            this.schedule(this.POLL_INTERVAL);
            return;
        }

        this._requesting = true;
        srv.board.refreshScrumBoard(this._projectId, this._digest, (res) => {
            this._requesting = false;
            this.handleResult(res);
        });
    },

    /**
     * Определяет, занят ли пользователь работой, которую обновление прервёт.
     *
     * Выделенный текст сюда не входит: выделение живёт, пока его не снимут,
     * и доска из-за него замерла бы насовсем. Выделение на стикере, который
     * не изменился, переживает обновление само - такой стикер не трогают.
     * @return {Boolean}
     */
    isUserBusy: function () {
        // Кнопка мыши зажата: идёт выделение или начатый клик
        if (this._mouseDown) return true;
        // Открытый диалог: его содержимое собрано из текущей разметки доски
        if (document.querySelector('.modal.show')) return true;

        // Действие пользователя ещё выполняется - его результат придёт следом
        // и сам поправит доску
        return !$('#preloader').hasClass('invisible');
    },

    handleResult: function (res) {
        if (!res || !res.success) {
            this.handleFailure();
            return;
        }

        this._failures = 0;
        this.setStale(false);

        // Пока запрос шёл, пользователь мог взяться за работу: диалог, открытый
        // после отправки тика, проверка перед отправкой не застала. Отпечаток
        // при этом не запоминаем - изменения не применены, и следующий тик
        // заберёт их заново.
        if (!res.changed || !this.isUserBusy()) {
            this._digest = res.digest;

            if (res.changed) {
                this.apply(res);
            }
        }

        this.schedule(this.POLL_INTERVAL);
    },

    /**
     * Неудачный тик: и обрыв связи, и отказ сервиса значат для пользователя
     * одно - доска перестала обновляться. Интервал при этом растёт, чтобы
     * не долбить недоступный сервер всё время, пока вкладка открыта.
     */
    handleFailure: function () {
        this._failures++;

        if (this._failures >= this.FAILURES_BEFORE_WARNING) {
            this.setStale(true);
        }

        this.schedule(Math.min(
            this.POLL_INTERVAL * Math.pow(2, this._failures - 1),
            this.MAX_POLL_INTERVAL
        ));
    },

    /**
     * Показывает или убирает предупреждение о том, что данные могли устареть.
     * @param {Boolean} stale
     */
    setStale: function (stale) {
        if (!this._warning) return;

        this._warning.classList.toggle('d-none', !stale);
        this._warning.classList.toggle('d-flex', stale);
    },

    /**
     * Переносит на страницу состояние доски, пришедшее с сервера.
     * @param {Object} res ответ сервиса
     */
    apply: function (res) {
        const fresh = document.createElement('div');
        fresh.innerHTML = res.boardHtml;

        const freshTable = fresh.querySelector('.scrum-board-table');
        const liveTable = this._board.querySelector('.scrum-board-table');
        if (!freshTable || !liveTable) return;

        // Карта живых стикеров общая на всю доску, а не на колонку: стикер,
        // переехавший в соседнюю колонку, должен переехать узлом, а не быть
        // удалён из одной колонки и создан заново в другой.
        const live = {};
        liveTable.querySelectorAll('.scrum-board-sticker').forEach(
            (el) => live[el.dataset.issueId] = el);

        ['col-todo', 'col-in_progress', 'col-testing', 'col-done'].forEach((col) => {
            const liveCol = liveTable.querySelector('.scrum-board-col.' + col);
            const freshCol = freshTable.querySelector('.scrum-board-col.' + col);
            if (liveCol && freshCol) this.patchColumn(liveCol, freshCol, live);
        });

        // Остались те, кого на доске больше нет
        Object.keys(live).forEach((id) => {
            live[id].remove();
            delete this._html[id];
        });

        sprintTarget.setValue(res.sprintTargetText, res.sprintTargetHtml);

        const sprintNum = this._board.querySelector('.scrum-board-sprint-num');
        if (sprintNum) sprintNum.textContent = 'Спринт #' + res.sprintNum;

        // Фильтр доски клиентский: приехавшие стикеры про него не знают.
        // Счётчики колонок пересчитываются следом, внутри применения фильтров.
        issuePage.refreshFilteredIssues();
    },

    /**
     * Приводит колонку доски к состоянию, пришедшему с сервера.
     *
     * Сначала снимается всё, чего в новой колонке нет, и только потом идёт
     * расстановка. Обратный порядок сдвигал бы за каждым лишним узлом все
     * следующие, а сдвинутый узел - это оборванное на нём выделение и фокус,
     * даже если сам стикер не изменился.
     * @param {Element} liveCol  колонка на странице
     * @param {Element} freshCol та же колонка в приехавшей разметке
     * @param {Object}  live     стикеры страницы по id задачи; разобранные
     *                           отсюда удаляются
     */
    patchColumn: function (liveCol, freshCol, live) {
        const freshGroups = freshCol.querySelectorAll('.scrum-board-priority-group');
        const keepGroups = {};
        freshGroups.forEach((group) => keepGroups[group.dataset.priorityGroup] = true);
        this.dropUnwanted(liveCol, ':scope > .scrum-board-priority-group',
            (group) => keepGroups[group.dataset.priorityGroup]);

        let groupPos = 0;
        freshGroups.forEach((freshGroup) => {
            const key = freshGroup.dataset.priorityGroup;
            const group = liveCol.querySelector(
                ':scope > .scrum-board-priority-group[data-priority-group="' + key + '"]')
                || freshGroup;

            this.placeAt(liveCol, group, groupPos++);

            const freshStickers = freshGroup.querySelectorAll('.scrum-board-sticker');
            const keepStickers = {};
            freshStickers.forEach((el) => keepStickers[el.dataset.issueId] = true);
            // Снятый стикер остаётся в live: он мог просто переехать в другую
            // колонку, и там его вернут в документ
            this.dropUnwanted(group, '.scrum-board-sticker',
                (el) => keepStickers[el.dataset.issueId]);

            // Позиция 0 в группе занята её заголовком
            let pos = 1;
            freshStickers.forEach((freshSticker) => {
                const id = freshSticker.dataset.issueId;
                const html = freshSticker.outerHTML;
                const current = live[id];
                // Неизменившийся стикер остаётся тем же узлом на том же месте:
                // открытое на нём меню и наведённая подсказка переживают обновление
                const keep = !!current && this._html[id] === html;

                // Изменившийся стикер заменяется новой разметкой, а старый узел
                // надо снять здесь: он остался на месте, а из live уже уходит,
                // и до общей уборки в конце не доживёт
                if (current && !keep) current.remove();

                delete live[id];
                this._html[id] = html;
                this.placeAt(group, keep ? current : freshSticker, pos++);
            });
        });
    },

    /**
     * Убирает из узла всех потомков по селектору, которые не нужно оставлять.
     * @param {Element}  root
     * @param {String}   selector
     * @param {Function} keep получает элемент, возвращает истину для остающихся
     */
    dropUnwanted: function (root, selector, keep) {
        Array.prototype.forEach.call(root.querySelectorAll(selector), function (el) {
            if (!keep(el)) el.remove();
        });
    },

    /**
     * Ставит узел на заданное место среди детей родителя.
     *
     * Узел, который уже стоит там, где нужно, не трогается вовсе: повторная
     * вставка того же узла - это его изъятие и возврат, а значит потерянный
     * фокус и оборванная подсказка.
     * @param {Element} parent
     * @param {Element} node
     * @param {Number} index позиция среди детей
     */
    placeAt: function (parent, node, index) {
        const current = parent.children[index];
        if (current === node) return;

        parent.insertBefore(node, current || null);
    },

    /**
     * Запоминает исходную разметку стикеров, чтобы потом отличать
     * действительно изменившиеся от нетронутых.
     * @param {Element} root
     */
    captureHtml: function (root) {
        root.querySelectorAll('.scrum-board-sticker').forEach(
            (el) => this._html[el.dataset.issueId] = el.outerHTML);
    },
};
