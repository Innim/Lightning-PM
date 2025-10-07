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
}

let gateway = window.lpmOptions.url + 'lpm-libs/flash2php/gateway.php';
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
        getVideoInfo: function (url, onResult) {
            this.s._('getVideoInfo');
        },
        getImageInfo: function (url, onResult) {
            this.s._('getImageInfo');
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
        comment: function (issueId, text, requestChanges, onResult) {
            this.s._('comment');
        },
        previewComment: function (text, onResult) {
            this.s._('previewComment');
        },
        merged: function (issueId, complete, onResult) {
            this.s._('merged');
        },
        passTest: function (issueId, onResult) {
            this.s._('passTest');
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
        setProjectSettings: function (projectId, scrum, slackNotifyChannel, onResult) {
            this.s._('setProjectSettings');
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
    },

    err: function (res) {
        showError(typeof res.error != 'undefined' ? res.error : 'Ошибка при запросе к серверу');
    }
};

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
        alert(text);
    }
};

lpm.dialog = {
    show: function (options) {
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
        const $primaryBtn = $('.btn-primary', $modalTemplate);
        if (opts.primaryBtn) {
            $primaryBtn.text(opts.primaryBtn);
            $primaryBtn.on('click', function () {
                if (opts.onPrimary) {
                    onHidden = null;
                    opts.onPrimary();
                }
                modal.hide();
            });
            hasButtons = true;
        } else {
            $primaryBtn.remove();
        }

        const $secondaryBtn = $('.btn-secondary', $modalTemplate);
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

            if (onHidden) {
                onHidden();
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

    this.getLinkedName = function () {
        return this.getName();
    };

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
        $("input.date").datepicker({
            dateFormat: 'dd/mm/yy',
            dayNames: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда',
                'Четверг', 'Пятница', 'Суббота'],
            dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
            dayNamesShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            currentText: 'Сегодня',
            weekHeader: 'Нед',
            prevText: 'Предыдущий',
            nextText: 'Следующий',
            monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль',
                'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
            monthNamesShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл',
                'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
            firstDay: 1,
            closeText: 'Готово'
        });

        if (hljs) hljs.initHighlightingOnLoad();

        $.widget.bridge('uitooltip', $.ui.tooltip);

        (function () {
            const activeObservers = new WeakMap();

            const clearObserver = function (target) {
                const observer = activeObservers.get(target);
                if (observer) {
                    observer.disconnect();
                    activeObservers.delete(target);
                }
            };

            $(document).uitooltip({
                open: function (event, ui) {
                    const target = event.originalEvent?.target;
                    if (!target) return;

                    const $target = $(target);
                    if (activeObservers.has(target)) return;

                    // hack to fix bug with tooltip is staying open when element is removed by click on it
                    const observer = new MutationObserver(() => {
                        if (!document.body.contains(target) || !$target.is(':visible')) {
                            const tooltips = $(document).uitooltip('instance').tooltips;
                            for (var prop in tooltips) {
                                const item = tooltips[prop];

                                if (item.element[0] === target) {
                                    item.tooltip[0].remove();
                                }
                            }
                            
                            clearObserver(target);
                        }
                    });
                    
                    observer.observe(document.body, {
                        attributes: true,
                        childList: true,
                        subtree: true,
                        attributeFilter: ['style', 'class', 'hidden'],
                    });

                    activeObservers.set(target, observer);
                },
                close: function(event, ui) {
                    const target = event.originalEvent?.target;
                    if (!target) return;
                    clearObserver(target);
                },
                position: {
                    my: "center bottom-20",
                    at: "center top",
                    using: function (position, feedback) {
                        $(this).css(position);
                        $("<div>")
                            .addClass("arrow")
                            .addClass(feedback.vertical)
                            .addClass(feedback.horizontal)
                            .appendTo(this);
                    }
                }
            });

        })();

        $('body').on('hidden.bs.dropdown', function(e) {
            // Force element to stay visible - some sort of bug in Bootstrap in conflict with jQuery
            e.target.style.display = '';
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
    alert(error)
}

let parser = {
    urlRegex: /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig,
    urlMrSubpath: 'merge_requests/',
    urlPipelineSubpath: 'pipelines/',
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
