if ('undefined' == typeof RegExp.escapeStr) {
    /**
     * 
     * @param {String} str
     * @return String
     */
    RegExp.escapeStr = function (str) {
        /*
        ( ) — круглые скобки;
        [ ] — квадратные скобки;
        \ — обратный слеш;
        . — точка;
        ^ — степень;
        $ — знак доллара;
        | — вертикальная черта;
        ? — вопросительный знак;
        + — плюс.*/

        return str.replace(/([\(\)\[\]\\\.\^\$\|\?\+]{1})/g, "\\$1");
    };
};

if ('undefined' == typeof RegExp.createFromStr) {
    /**
     * 
     * @param {String} str
     * @return RegExp
     */
    RegExp.createFromStr = function (str, keys) {
        return new RegExp(RegExp.escapeStr(str), keys);
    };
};
if ('undefined' == typeof Element.prototype.show) {
    Element.prototype.show = function () {
        this.style.display = '';
    };
};

if ('undefined' == typeof Element.prototype.hide) {
    Element.prototype.hide = function () {
        this.style.display = 'none';
        return this;
    };
};

/**
 * Сервис для запросов на сервер
 * @class 
 * @param {F2PInvoker} invoker класс для отсылки запросов
 * @param {String} service название сервиса
 */
function BaseService(service, f2p) {
    this._service = service;
    this._f2p = f2p;

    /**
     * Вызов метода
     * @param {String} method вызываемый метод
     * @param {Array} params массив передаваемых параметров
     * @param {Function} onResult функция-обработчик ответа
     */
    this.call = function (method, params, onResult) {
        let f2p = this._f2p ?? srv.f2p;
        params.unshift(this._service, method, function (obj) {
            if (obj.errno == F2PInvoker.ERRNO_AUTH_BLOCKED) {
                window.location.reload();
            } else {
                try {
                    onResult(obj);
                } catch (e) {
                    console && console.error(e);
                    srv.err({ error: 'Ошибка при обработке ответа' });
                }
            }
        });
        f2p.request.apply(null, params);
    };

    this.callWithFiles = function (method, params, files, onResult) {
        // Проверяем суммарный объём вложений до отправки, чтобы сразу показать
        // причину и не гонять большой запрос на сервер впустую.
        const maxTotalMb = window.lpmOptions && window.lpmOptions.attachmentsTotalSizeMb;
        if (maxTotalMb) {
            let totalSize = 0;
            Array.prototype.forEach.call(files || [], function (file) {
                totalSize += file.size || 0;
            });
            if (totalSize > maxTotalMb * 1024 * 1024) {
                onResult({ success: false, error: 'Суммарный размер файлов не должен превышать ' + maxTotalMb + ' Мб (сейчас ' + lpm.format.sizeMb(totalSize) + ')' });
                return;
            }
        }

        const data = new FormData();
        data.append('service', this._service);
        data.append('method', method);
        data.append('params', JSON.stringify(params));

        Array.prototype.forEach.call(files || [], function (file) {
            data.append('commentFiles[]', file);
        });

        $.ajax({
            url: srv.gateway,
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: ru.vbinc.net.F2PInvoker.defaultHeaders,
            success: function (obj) {
                if (obj.errno == F2PInvoker.ERRNO_AUTH_BLOCKED) {
                    window.location.reload();
                    return;
                }

                try {
                    onResult(obj);
                } catch (e) {
                    console && console.error(e);
                    srv.err({ error: 'Ошибка при обработке ответа' });
                }
            },
            error: function () {
                onResult({ success: false, error: 'Ошибка при загрузке файлов' });
            }
        });
    };

    this._ = function (name) {
        var func = arguments.callee.caller;
        //name = defaultValue( name, func.caller.name );    
        var args = [];
        for (var i = 0; i < func.arguments.length; i++) {
            args.push(func.arguments[i]);
        }

        var onResult = args.pop();

        this.call.apply(this, [name, args, onResult]);
    };
};

function ParallelService(service) {
    this._service = service;
    this._cache = [];

    this._ = function (name) {
        let cache = this._cache;
        let impl = cache.pop() ?? new BaseService(service, new ru.vbinc.net.F2PInvoker(srv.gateway));

        let func = arguments.callee.caller;
        let args = [];
        for (var i = 0; i < func.arguments.length; i++) {
            args.push(func.arguments[i]);
        }

        let onResult = args.pop();

        impl.call(name, args, (r) => {
            cache.push(impl);
            onResult(r);
        });
    }
}

const lpm = {
    format: {
        // Размер в мегабайтах с одним знаком после запятой, напр. "156,2 Мб".
        sizeMb: function (bytes) {
            const mb = (bytes || 0) / (1024 * 1024);
            return mb.toLocaleString('ru-RU', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' Мб';
        },
        date: function (unixTimeSec, addTimeZone = true) {
            const date = new Date(unixTimeSec * 1000);

            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear();

            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');

            let res = `${day}.${month}.${year} ${hours}:${minutes}`;
            if (addTimeZone) {
                const timeZoneOffset = date.getTimezoneOffset();
                const sign = timeZoneOffset < 0 ? '+' : '-';
                const absOffset = Math.abs(timeZoneOffset);
                const tzHours = Math.floor(absOffset / 60).toString().padStart(2, '0');
                const tzMinutes = (absOffset % 60).toString().padStart(2, '0');
                res += ` GMT${sign}${tzHours}:${tzMinutes}`;
            }

            return res;
        }
    },
    components: {
        /* reserved for dynamic components */
    },
    // later init
    dialog: null,
    toast: null,
    utils: null,
    datePicker: null,
}

// Экспортируем неймспейс в window, чтобы он был доступен из ES-модулей
// (top-level const классического скрипта не становится свойством window).
window.lpm = lpm;

let gateway = window.lpmOptions.url + 'lpm-libs/flash2php/gateway.php';

// Токен своей страницы: сервер отклоняет запросы к сервисам без него, поэтому
// со стороннего сайта действие от имени пользователя выполнить нельзя.
// Задаём на конструкторе - его читают все инвокеры, включая создаваемые позже.
ru.vbinc.net.F2PInvoker.defaultHeaders['X-CSRF-Token'] = window.lpmOptions.csrfToken;

// Сборка ИИ-сводки — это запрос к внешней модели, он идёт дольше обычного:
// сервер ждёт ответа до aiRequestTimeout секунд, поэтому у ИИ-сервиса
// собственный инвокер с запасом сверху (у общего таймаут 30 секунд).
let aiInvoker = new ru.vbinc.net.F2PInvoker(gateway);
aiInvoker.setTimeout((window.lpmOptions.aiRequestTimeout || 60) + 30);

let srv = {
    gateway: gateway,
    f2p: new ru.vbinc.net.F2PInvoker(gateway),
    attachments: {
        s: new ParallelService('AttachmentsService'),
        getMRInfo: function (url, onResult) {
            this.s._('getMRInfo');
        },
        getPipelineInfo: function (url, onResult) {
            this.s._('getPipelineInfo');
        },
        getJobInfo: function (url, onResult) {
            this.s._('getJobInfo');
        },
        getVideoInfo: function (url, onResult) {
            this.s._('getVideoInfo');
        },
        getImageInfo: function (url, onResult) {
            this.s._('getImageInfo');
        },
    },
    ai: {
        s: new BaseService('AiService', aiInvoker),
        issueSummary: function (issueId, onResult) {
            this.s._('issueSummary');
        },
        issueTestChecklist: function (issueId, onResult) {
            this.s._('issueTestChecklist');
        },
        issueDraft: function (projectId, text, images, onResult) {
            this.s._('issueDraft');
        },
    },
    files: {
        s: new BaseService('FilesService'),
        getCompressStatus: function (uids, onResult) {
            this.s._('getCompressStatus');
        },
    },
    issue: {
        s: new BaseService('IssueService'),
        complete: function (issueId, onResult) {
            this.s._('complete');
        },
        restore: function (issueId, onResult) {
            this.s._('restore');
        },
        verify: function (issueId, onResult) {
            this.s._('verify');
        },
        load: function (issueId, loadLinked, onResult) {
            this.s._('load');
        },
        loadByIdInProject: function (idInProject, projectId, onResult) {
            this.s._('loadByIdInProject');
        },
        remove: function (issueId, onResult) {
            this.s._('remove');
        },
        addLink: function (issueId, linkedIssueId, onResult) {
            this.s._('addLink');
        },
        addLinkByUrl: function (issueId, url, onResult) {
            this.s._('addLinkByUrl');
        },
        removeLink: function (issueId, linkedIssueId, onResult) {
            this.s._('removeLink');
        },
        comment: function (issueId, text, requestChanges, files, onResult) {
            this.s.callWithFiles('comment', [issueId, text, requestChanges], files, onResult);
        },
        previewComment: function (text, onResult) {
            this.s._('previewComment');
        },
        previewIssueDesc: function (text, onResult) {
            this.s._('previewIssueDesc');
        },
        merged: function (issueId, complete, onResult) {
            this.s._('merged');
        },
        takeForTesting: function (issueId, onResult) {
            this.s._('takeForTesting');
        },
        releaseFromTesting: function (issueId, onResult) {
            this.s._('releaseFromTesting');
        },
        passTest: function (issueId, text, files, onResult) {
            this.s.callWithFiles('passTest', [issueId, text], files, onResult);
        },
        postTestChecklist: function (issueId, text, onResult) {
            this.s._('postTestChecklist');
        },
        createBranch: function (issueId, branchName, gitlabProjectId, parentBranch, onResult) {
            this.s._('createBranch');
        },
        changePriority: function (issueId, delta, onResult) {
            this.s._('changePriority');
        },
        changeScrumState: function (issueId, state, onResult) {
            this.s._('changeScrumState');
        },
        putStickerOnBoard: function (issueId, onResult) {
            this.s._('putStickerOnBoard');
        },
        removeStickersFromBoard: function (projectId, transferOpened, onResult) {
            this.s._('removeStickersFromBoard');
        },
        takeIssue: function (issueId, replace, onResult) {
            this.s._('takeIssue');
        },
        addMeToIssue: function (issueId, role, onResult) {
            this.s._('addMeToIssue');
        },
        lockIssue: function (issueId, revision, forced, onResult) {
            this.s._('lockIssue');
        },
        unlockIssue: function (issueId, revision, onResult) {
            this.s._('unlockIssue');
        },
        addLabel: function (label, isForAllProjects, projectId, onResult) {
            this.s._('addLabel');
        },
        removeLabel: function (id, projectId, onResult) {
            this.s._('removeLabel');
        },
        exportCompletedIssuesToExcel: function (projectId, fromDate, toDate, onResult) {
            this.s._('exportCompletedIssuesToExcel');
        },
        deleteComment: function (id, deleteBranch, onResult) {
            this.s._('deleteComment');
        },
        resolveComment: function (commentId, onResult) {
            this.s._('resolveComment');
        }
    },
    project: {
        s: new BaseService('ProjectService'),
        addMembers: function (projectId, userIds, onResult) {
            this.s._('addMembers');
        },
        getMembers: function (projectId, onResult) {
            this.s._('getMembers');
        },
        setMaster: function (projectId, masterId, onResult) {
            this.s._('setMaster');
        },
        deleteMaster: function (projectId, onResult) {
            this.s._('deleteMaster');
        },
        addSpecMaster: function(projectId, masterId, labelId, onResult) {
            this.s._('addSpecMaster');
        },
        addSpecTester: function(projectId, userId, labelId, onResult) {
            this.s._('addSpecTester');
        },
        deleteSpecMaster: function(projectId, masterId, labelId, onResult) {
            this.s._('deleteSpecMaster');
        },
        deleteSpecTester: function(projectId, userId, labelId, onResult) {
            this.s._('deleteSpecTester');
        },
        deleteMemberDefault: function (projectId, onResult) {
            this.s._('deleteMemberDefault');
        },
        addIssueMemberDefault: function (projectId, memberByDefaultId, onResult) {
            this.s._('addIssueMemberDefault');
        },
        addTester: function (projectId, userId, onResult) {
            this.s._('addTester');
        },
        deleteTester: function ($projectId, onResult) {
            this.s._('deleteTester');
        },
        setPM: function (projectId, userId, onResult) {
            this.s._('setPM');
        },
        deletePM: function (projectId, onResult) {
            this.s._('deletePM');
        },
        getSumOpenedIssuesHours: function (projectId, onResult) {
            this.s._('getSumOpenedIssuesHours');
        },
        saveProject: function (
            projectId, uid, name, desc, scrum, slackNotifyChannel, gitlabGroupId, gitlabProjectIds,
            aiSummary, aiTestChecklist, aiIssueDraft, requireLabels, onResult
        ) {
            this.s._('saveProject');
        },
        searchIssueNames: function (projectId, idInProjectPart, onResult) {
            this.s._('searchIssueNames');
        },
        getRepositories: function (projectId, onResult) {
            this.s._('getRepositories');
        },
        getBranches: function (projectId, gitlabProjectId, onResult) {
            this.s._('getBranches');
        },
        setSprintTarget: function (projectId, textTarget, onResult) {
            this.s._('addSprintTarget');
        },
    },
    projects: {
        s: new BaseService('ProjectsService'),
        setIsArchive: function (projectId, value, onResult) {
            this.s._('setIsArchive');
        },
        setIsFixed: function (projectId, value, onResult){
            this.s._('setIsFixed');
        },
        getList: function () { 
            this.s._('getList');
        },
    },
    profile: {
        s: new BaseService('ProfileService'),
        emailPref: function (data, onResult) {
            this.s._('emailPref');
        },
        newPass: function (currentPass, newPass, onResult) {
            this.s._('newPass');
        },
        createApiKey: function (name, onResult) {
            this.s._('createApiKey');
        },
        revokeApiKey: function (keyId, onResult) {
            this.s._('revokeApiKey');
        }
    },
    users: {
        s: new BaseService('UsersService'),
        lockUser: function (userId, isLock) {
            this.s._('lockUser');
        },
        setSlackName: function (userId, slackName) {
            this.s._('setSlackName');
        },
        setRole: function (userId, role) {
            this.s._('setRole');
        },
    },

    err: function (res) {
        showError(typeof res.error != 'undefined' ? res.error : 'Ошибка при запросе к серверу');
    }
};

// Пока страница открыта, напоминаем о себе серверу: токен страницы живёт вместе
// с сессией, а PHP сбрасывает её после session.gc_maxlifetime без запросов.
// Форму — например, описание задачи — заполняют и дольше, и без пинга отправка
// упёрлась бы в устаревший токен.
(function () {
    const lifetime = parseInt(window.lpmOptions.sessionLifetime, 10);
    if (!lifetime || lifetime <= 0) return;

    // С запасом — половина срока, но не чаще раза в минуту и не реже раза в 10 минут
    const interval = Math.min(Math.max(Math.floor(lifetime / 2), 60), 600) * 1000;

    setInterval(function () {
        // Намеренно в обход BaseService: тот на отказ перезагружает страницу,
        // а фоновый пинг не должен стирать недописанную форму. Если сессия всё
        // же умерла, пользователь узнает об этом при отправке — и получит
        // введённое обратно.
        srv.f2p.request('SessionService', 'ping', function () {});
    }, interval);
})();

var states = {
    _list: [],
    current: null,
    // Будет показывать заданный элемент при включении указанного стейта
    // стейт может содержать параметры, при регистрации вместо каждого параметра надо указывать #
    // сами параметры должны быть перечислены через : (двоеточие)
    addState: function (element, state, showHandler) {
        var params = 0;
        if (typeof state == 'undefined' || state == '') state = '';
        else {
            var arr = state.split(':');
            params = arr.length - 1;
            state = arr[0];
        }

        for (var i = 0; i < this._list.length; i++) {
            if (this._list[i].st == state) return;
        }
        this._list.push({ el: element, st: state, sh: showHandler, p: params });
    },
    setState: function (state, skipUpdateView = false) {
        const currentHash = window.location.hash;
        var newHash;
        if (state.trim() == '') {
            newHash = '';
        } else {
            newHash = state;
            
        }

        if (newHash != currentHash && '#' + newHash != currentHash) {
            window.location.hash = newHash;
            if (skipUpdateView != true) states.updateView();
        }
    },
    updateView: function () {
        var item;
        this.current = null;
        var hash = window.location.hash;
        if (hash.startsWith('#')) hash = hash.substring(1);
        var hashArr = hash.split(':');
        var p = hashArr.length - 1;
        hash = hashArr.shift();
        for (var i = 0; i < this._list.length; i++) {
            item = this._list[i];
            if (hash === item.st && item.p === p) {
                this.activateState(item, p > 0 ? hashArr : null);
                break;
            }
        }

        if (!this.current) {
            if (this._list.length > 0) this.activateState(this._list[0]);
            else this.deactivateAll();
        }
    },
    deactivateAll: function () {
        for (var i = 0, len = this._list.length; i < len; i++) {
            var item = this._list[i];
            if (item.el) item.el.hide();
            $('.info-message', item.el).hide();
            //$( '.info-message', item.el ).hide();
        }
    },
    activateState: function (item, params) {
        try {
            this.deactivateAll();

            if (item.sh) item.sh.apply(item.sh, params);
            if (item.el) item.el.show();
            this.current = item;
        } catch (e) {
            // do something
            console.error(e);
        }
    }
};

var messages = {
    _ito: -1,
    /*error : function (text) {
        
    },*/
    info: function (text, _container) {
        if (!_container)
            _container = $('.info-message', states.current ? states.current.el : null);
        if (_container) {
            _container.html(text);
            _container.fadeIn('normal');
            if (messages._ito != -1) {
                clearTimeout(messages._ito);
                messages._ito = -1;
            }
            messages._ito = setTimeout(function () {
                _container.fadeOut('slow');
            }, 3000);
        }
    },
    alert: function (text) {
        lpm.dialog.show({
            // Экранируем: text вставляется как HTML, а сообщение может содержать
            // произвольные (в т.ч. серверные) данные.
            text: $('<span>').text(text == null ? '' : text).html(),
            primaryBtn: 'OK',
            secondaryBtn: null,
        });
    }
};

lpm.dialog = {
    // Открыто ли сейчас модальное окно (в т.ч. в процессе закрытия).
    _isOpen: false,
    // Окна, запрошенные пока открыто другое: покажем их по очереди.
    _queue: [],
    show: function (options) {
        // Шаблон #dynamicModal выводится в конце body. Если show вызван из inline-скрипта
        // во время парсинга страницы (например, showError с серверной ошибкой), шаблона
        // ещё нет в DOM — откладываем показ до готовности документа.
        if (document.readyState === 'loading' && document.getElementById('dynamicModal') === null) {
            $(function () { lpm.dialog.show(options); });
            return;
        }

        // Bootstrap 5.1.3 не поддерживает одновременно открытые модальные окна
        // (у второго ломается блокировка прокрутки фона). Поэтому показываем окна
        // строго по одному: если уже открыто — ставим в очередь и покажем следующее
        // по событию закрытия текущего.
        if (lpm.dialog._isOpen) {
            lpm.dialog._queue.push(options);
            return;
        }
        lpm.dialog._isOpen = true;

        const defaultOptions = {
            title: null,
            text: null,
            content: null,
            centered: true,
            onPrimary: null,
            onSecondary: null,
            onCancel: null,
            primaryBtn: 'OK',
            secondaryBtn: 'Отмена',
            secondaryBtnClass: null,
        };

        const opts = Object.assign({}, defaultOptions, options);

        const $modalTemplate = $('#dynamicModal').clone();
        const newId = 'dynamicModal-' + Date.now();
        $modalTemplate.attr('id', newId);

        if (opts.centered) $modalTemplate.addClass('modal-dialog-centered');

        if (opts.title !== null) {
            const $title = $('.modal-title', $modalTemplate);
            $title.html(opts.title);
        } else {
            $('.modal-header', $modalTemplate).remove();
        }

        const $body = $('.modal-body', $modalTemplate);
        if (opts.content !== null) {
            $body.html(opts.content);
        } else if (opts.text !== null) {
            $body.html('<p>' + opts.text + '</p>');
        } else {
            $body.remove();
        }

        $('body').append($modalTemplate);
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById(newId));

        let onHidden = opts.onCancel;

        let hasButtons = false;
        // Только кнопки футера: в content могут быть свои .btn-primary/.btn-secondary,
        // и поиск по всему окну удалял бы или переименовывал их.
        const $footer = $('.modal-footer', $modalTemplate);
        const $primaryBtn = $('.btn-primary', $footer);
        if (opts.primaryBtn) {
            $primaryBtn.text(opts.primaryBtn);
            $primaryBtn.on('click', function () {
                if (opts.onPrimary) {
                    const cancelHandler = onHidden;
                    onHidden = null;
                    // onPrimary может вернуть false, чтобы окно осталось открытым
                    // (многошаговый диалог закрывает себя сам). Окно ещё открыто,
                    // поэтому обработчик отмены возвращаем на место.
                    if (opts.onPrimary() === false) {
                        onHidden = cancelHandler;
                        return;
                    }
                }
                modal.hide();
            });
            hasButtons = true;
        } else {
            $primaryBtn.remove();
        }

        const $secondaryBtn = $('.btn-secondary', $footer);
        if (opts.secondaryBtn) {
            $secondaryBtn.text(opts.secondaryBtn);
            if (opts.onSecondary) {
                $secondaryBtn.off('click').on('click', function () {
                    onHidden = null;
                    opts.onSecondary();
                    modal.hide();
                });
            }
            if (opts.secondaryBtnClass) {
                $secondaryBtn.addClass(opts.secondaryBtnClass);
            }
            hasButtons = true;
        } else {
            $secondaryBtn.remove();
        }

        if (!hasButtons) {
            $('.modal-footer', $modalTemplate).remove();
        }

        $modalTemplate.on('hidden.bs.modal', function () {
            $modalTemplate.remove();
            lpm.dialog._isOpen = false;

            if (onHidden) {
                onHidden();
            }

            // Показать следующее окно из очереди (если onHidden не открыл своё).
            if (!lpm.dialog._isOpen && lpm.dialog._queue.length > 0) {
                lpm.dialog.show(lpm.dialog._queue.shift());
            }
        });

        modal.show()
    },
    /**
     * Показать диалог подтверждения.
     * options: {
     *   title?: string,
     *   text: string,
     *   yesLabel?: string, // default 'OK'
     *   noLabel?: string,  // default 'Отмена'
     *   centered?: boolean,
     *   onYes?: function,
     *   onNo?: function,
     * }
     */
    confirm: function (options) {
        const opts = options || {};
        this.show({
            title: opts.title || null,
            text: opts.text || '',
            centered: opts.centered !== false,
            primaryBtn: opts.yesLabel || 'OK',
            secondaryBtn: (typeof opts.noLabel === 'undefined') ? 'Отмена' : opts.noLabel,
            onPrimary: function () {
                if (typeof opts.onYes === 'function') opts.onYes();
            },
            onSecondary: function () {
                if (typeof opts.onNo === 'function') opts.onNo();
            }
        });
    }
}

lpm.toast = {
    show: function (message) {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fa fa-check me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '1055';
            document.body.appendChild(toastContainer);
        }
        
        const toastElement = document.createElement('div');
        toastElement.innerHTML = toastHtml;
        const toast = toastElement.firstElementChild;
        toastContainer.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
}

lpm.validators = {
    // Допустимый идентификатор проекта: латинские буквы, цифры и дефис (1–255 символов).
    projectUid: function (uid) {
        return /^(([a-zA-Z0-9]){1}([a-zA-Z0-9\-]){0,254})$/u.test(uid);
    },
};

lpm.utils = {
    copyRichToClipboard: function (html, plain) {
        if (navigator.clipboard && window.isSecureContext) {
            const item = new ClipboardItem({
                "text/html": new Blob([html], { type: "text/html" }),
                "text/plain": new Blob([plain], { type: "text/plain" })
            });
            return navigator.clipboard.write([item]);
        } else {
            return lpm.utils.copyToClipboard(plain);
        }
    },
    /**
     * Экранирует текст для безопасной вставки в HTML.
     * @param {string} text
     * @returns {string}
     */
    escapeHtml: function (text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },
    copyToClipboard: function (text) {
        // Modern clipboard API (works in HTTPS/localhost)
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback for HTTP or older browsers
            return new Promise((resolve, reject) => {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    const successful = document.execCommand('copy');
                    document.body.removeChild(textArea);
                    if (successful) {
                        resolve();
                    } else {
                        reject();
                    }
                } catch (err) {
                    document.body.removeChild(textArea);
                    reject(err);
                }
            });
        }
    },
};

var preloader = {
    _showed: 0,
    show: function () {
        this._showed++;
        if (this._showed == 1) {
            $('#preloader').removeClass('invisible');
        }
    },
    hide: function () {
        if (this._showed == 0) return;
        this._showed--;
        if (this._showed == 0) {
            $('#preloader').addClass('invisible');
        }
    },
    getNewIndicator: function (className) {
        const res = $('#templates .preloader').clone();
        if (className) res.addClass(className);
        return res;
    },
    getNewIndicatorLarge: function () {
        return preloader.getNewIndicator('spinner-border-large');
    },
    getNewIndicatorMedium: function () {
        return preloader.getNewIndicator();
    },
    getNewIndicatorSmall: function () {
        return preloader.getNewIndicator('spinner-border-sm');
    },
};

var imgUpload = {
    onSelect: function (event, maxPhotos) {
        var input = event.currentTarget;
        var parent = input.parentNode.parentNode;
        if (typeof maxPhotos !== 'undefined' && maxPhotos <= parent.children.length) return;

        var inputs = parent.getElementsByTagName('input');
        for (var i = inputs.length - 1; i >= 0; i--) {
            if (input.type === 'file' && !inputs[i].value) return;
        }

        var field = input.parentNode.cloneNode(true);
        $("input[type=file]", field).val("");
        parent.appendChild(field);
    }
};

var lpInfo = {
    userId: 0
};

function User(obj) {
    this._obj = obj;

    this.userId = obj.userId;
    this.firstName = obj.firstName;
    this.lastName = obj.lastName;
    this.nick = obj.nick;
    //this.        = obj.;

    // Имя пользователя приходит с сервера как есть, поэтому всё,
    // что вставляется в разметку, экранируется здесь.
    this.getLinkedName = function () {
        return this._obj.linkedName
            ? this._obj.linkedName
            : lpm.utils.escapeHtml(this.getName());
    };

    // Имя в исходном виде: для вставки в разметку не годится.
    this.getName = function () {
        return this.firstName + ' ' +
            (this.nick != '' ? this.nick + ' ' : '') +
            this.lastName;
    };
};

/*
function checkState( element, state ) {
    if (state == '' && window.location.hash == '' || window.location.hash == '#' + state) element.show();
    else element.hide();
};*/

window.onload = function () {
    var canvas = document.createElement('canvas');
    if (!canvas || navigator.userAgent.match(/MSIE/i)) {
        $('#content').hide();
        $('body > nav').hide();
        $('#noway').show();
    }

    // gallery
    L.path = window.lpmOptions.themeUrl + 'imgs/';
    L.create();
};

$(document).ready(
    function () {
        // highlight.js is loaded only on pages that render code (see ProjectPage),
        // so guard with typeof — it is undefined elsewhere.
        if (typeof hljs !== 'undefined' && hljs) hljs.initHighlightingOnLoad();

        // Global tooltip: a single delegated Bootstrap tooltip on <body> covers every current
        // and dynamically added [title] element (replaces the former jQuery UI global tooltip).
        // Exclusions:
        //  - [data-tooltip]: issue-link elements have their own popover (see formatting.js).
        //  - structural [data-bs-toggle] toggles (dropdown/collapse/modal/tab/offcanvas/…): Bootstrap
        //    allows only ONE component instance per element (Data.set), so a Tooltip can't attach to an
        //    element that already hosts a Dropdown/Collapse/… — the instance silently isn't stored and
        //    its tip never hides (stays stuck open). Those keep their native `title` tooltip instead.
        //    The second selector clause re-includes data-bs-toggle="tooltip", which IS a tooltip (no
        //    conflicting component), e.g. the notification hints in profile.html.
        new bootstrap.Tooltip(document.body, {
            selector: '[title]:not([data-tooltip]):not([data-bs-toggle]), [data-bs-toggle="tooltip"][title]:not([data-tooltip])',
            container: 'body',
        });

        // Global copy-to-clipboard: any current or dynamically added [data-copy] element copies its
        // value and shows a confirmation toast (optional custom text via data-copy-toast).
        $(document).on('click', '[data-copy]', function () {
            const value = this.getAttribute('data-copy');
            const toast = this.getAttribute('data-copy-toast') || 'Скопировано';
            lpm.utils.copyToClipboard(value).then(function () {
                lpm.toast.show(toast);
            });
        });

        // Bootstrap only hides a tooltip in response to pointer/focus events on its trigger, so a
        // titled control that removes or hides its own DOM node while the tooltip is open (e.g. the
        // SCRUM board "Убрать с доски" action removes the sticker on AJAX success — no mouseleave
        // fires) leaves the tooltip stuck in <body>. While a tooltip is shown, watch for its trigger
        // being removed or hidden and dispose the tooltip so it isn't left orphaned. Disposing (not
        // hide()) avoids re-firing hide.bs.tooltip, whose guard below would otherwise un-hide the
        // trigger the app deliberately hid.
        $('body').on('shown.bs.tooltip', function(e) {
            const trigger = e.target;
            const observer = new MutationObserver(function() {
                if (!document.body.contains(trigger) || !$(trigger).is(':visible')) {
                    observer.disconnect();
                    $(trigger).removeData('lpmTooltipCleanup');
                    const instance = bootstrap.Tooltip.getInstance(trigger);
                    if (instance) instance.dispose();
                }
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class', 'hidden'],
            });
            $(trigger).data('lpmTooltipCleanup', observer);
        });
        $('body').on('hidden.bs.tooltip', function(e) {
            const observer = $(e.target).data('lpmTooltipCleanup');
            if (observer) {
                observer.disconnect();
                $(e.target).removeData('lpmTooltipCleanup');
            }
        });

        // The "..." issue-actions dropdown and the goto-issue collapse toggle can't carry a Bootstrap
        // tooltip on the toggle itself (one component instance per element), so their title lives on the
        // inner icon and the delegated tooltip attaches there. Hide that tooltip when the control opens
        // so it doesn't linger over the revealed menu/panel.
        const toggleIconTooltip = function(toggle) {
            const icon = toggle && toggle.querySelector('[title], [data-bs-original-title]');
            return icon ? bootstrap.Tooltip.getInstance(icon) : null;
        };
        $('body').on('show.bs.dropdown', function(e) {
            const instance = toggleIconTooltip(e.target);
            // Disable too: the toggle stays visible while open, so a re-hover would otherwise re-show it.
            if (instance) { instance.hide(); instance.disable(); }
        });
        $('body').on('show.bs.collapse', function(e) {
            // Collapse events fire on the target panel; hide the tooltip on the toggle(s) that control it.
            if (!e.target.id) return;
            const instance = toggleIconTooltip(document.querySelector(
                '[data-bs-toggle="collapse"][href="#' + e.target.id + '"], ' +
                '[data-bs-toggle="collapse"][data-bs-target="#' + e.target.id + '"]'));
            if (instance) instance.hide();
        });

        $('body').on('hidden.bs.dropdown', function(e) {
            // Force element to stay visible - some sort of bug in Bootstrap in conflict with jQuery
            e.target.style.display = '';
            const instance = toggleIconTooltip(e.target);
            if (instance) instance.enable();
        });

        // Same conflict for tabs: jQuery invokes the Element.prototype.hide polyfill when Bootstrap
        // fires hide.bs.tab, hiding the deselected tab button. Restore its display in a microtask so
        // the change is reverted before the browser paints (waiting for hidden.bs.tab would flicker,
        // as that only fires after the ~150ms fade transition).
        $('body').on('hide.bs.tab', function(e) {
            const el = e.target;
            Promise.resolve().then(function() { el.style.display = ''; });
        });

        // Same conflict for tooltips: when hide.bs.tooltip fires, jQuery's default action calls the
        // Element.prototype.hide polyfill on the tooltip's trigger element, hiding it. Restore its
        // display in a microtask (after the trigger's default action runs) to keep the target visible.
        $('body').on('hide.bs.tooltip', function(e) {
            const el = e.target;
            Promise.resolve().then(function() { el.style.display = ''; });
        });

        window.lpInfo.userId = $('#curUserId').val();
        // Инициализация копирования в буфер
        (new ClipboardJS('.copy-commit-message'));
    }
);

function redirectTo(url) {
    window.location.replace(url);
}

function showError(error) {
    lpm.dialog.show({
        title: 'Ошибка',
        // Текст ошибки может приходить с сервера/из внешних сервисов, поэтому
        // экранируем его: text вставляется как HTML.
        text: $('<span>').text(error == null ? '' : error).html(),
        primaryBtn: 'OK',
        secondaryBtn: null,
    });
}

let parser = {
    urlRegex: /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig,
    urlMrSubpath: 'merge_requests/',
    urlPipelineSubpath: 'pipelines/',
    urlJobSubpath: 'jobs/',
    isUrl: function (text) {
        return text.test(parser.urlRegex);
    },
    findLinks: function (text) {
        return text.match(parser.urlRegex);
    },
    isMRUrl: function (url) {
        let baseUrl = lpmOptions.gitlabUrl;
        return url.indexOf(baseUrl) === 0 &&
            url.indexOf(parser.urlMrSubpath) !== -1;
    },
    isPipelineUrl: function (url) {
        let baseUrl = lpmOptions.gitlabUrl;
        return url.indexOf(baseUrl) === 0 &&
            url.indexOf(parser.urlPipelineSubpath) !== -1;
    },
    isJobUrl: function (url) {
        let baseUrl = lpmOptions.gitlabUrl;
        return url.indexOf(baseUrl) === 0 &&
            url.indexOf(parser.urlJobSubpath) !== -1;
    },
    isVideoUrl: function (url) {
        let patterns = lpmOptions.videoUrlPatterns;
        return patterns.some((pattern, i, a) => new RegExp(pattern).test(url));
    },
    isImageUrl: function (url) {
        let patterns = lpmOptions.imageUrlPatterns;
        return patterns.some((pattern, i, a) => new RegExp(pattern, 'i').test(url));
    }
};

$(document).ready(() => {
    $(window).load(() => {
        states.updateView();
    });
});
