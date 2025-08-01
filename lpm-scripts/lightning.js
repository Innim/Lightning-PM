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

let gateway = window.lpmOptions.url + 'lpm-libs/flash2php/gateway.php';
let srv = {
    gateway: gateway,
    f2p: new ru.vbinc.net.F2PInvoker(gateway),
    attachments: {
        s: new ParallelService('AttachmentsService'),
        getMRInfo: function (url, onResult) {
            this.s._('getMRInfo');
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
        addLabel: function (label, isForAllProjects, projectId, onResult) {
            this.s._('addLabel');
        },
        removeLabel: function (id, projectId, onResult) {
            this.s._('removeLabel');
        },
        exportCompletedIssuesToExcel: function (projectId, fromDate, toDate, onResult) {
            this.s._('exportCompletedIssuesToExcel');
        },
        deleteComment: function (id, onResult) {
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
        emailPref: function (addIssue, editIssue, issueState, issueComment, onResult) {
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

        $(document).uitooltip({
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
