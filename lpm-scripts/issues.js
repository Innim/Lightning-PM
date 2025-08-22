$(document).ready(
    function () {
        //$( '#issueView .comments form.add-comment' ).hide();
        issuePage.projectId = parseInt($('#issueProjectID').val());
        if ($('#issueInfo').length) {
            issuePage.idInProject = $('#issueInfo').data('idInProject');
            issuePage.labels = $('#issueInfo').data('labels').split(',');
        }
        issuePage.updatePriorityVals();
        issuePage.scrumColUpdateInfo();
        var dd = new DropDown($('#dropdown'));

        $(document).on('click', '#showIssues4MeLink', function () {
            states.setState('only-my');
        });

        $(document).on('click', '#showIssues4AllLink', function () {
            // TODO: should remake this one
            issuePage.resetFilter();
        });

        $(document).on('click', '#showLastCreated', function () {
            states.setState('last-created');
        });

        $(document).on('click', '#sortDefault', function () {
            // TODO: should remake this one
            issuePage.sortDefault();
        });

        $(document).on('click', '#issuesList .member-list a', function (e) {
            const memberId = $(e.currentTarget).data('memberId');
            states.setState('by-user:' + memberId);
        });

        $(".comment-input-text-tabs").tabs({
            activate: function (_, ui) {
                if (ui.newPanel.hasClass('preview-tab')) {
                    issuePage.previewComment(ui.newPanel.parent('.comment-input-text-tabs'));
                }
            },
        });

        // BEGIN -- Настройка формы 

        $('#issueForm .tags-line a.tag').on('click', function (e) {
            let a = $(e.currentTarget);
            let input = $('#issueForm textarea[name=desc]');
            let type = a.data('type');

            if (type) {
                switch (type) {
                    case 'link':
                        insertFormattingLink(input);
                        break;
                }
            } else {
                let marker = a.data('marker')
                if (marker) {
                    insertFormattingMarker(input, marker, a.data('single'));
                }
            }
        });

        $('#issueForm input[name=hours]').on('focus', function (e) {
            let field = $(e.currentTarget);
            if (!field.val()) {
                var sum = 0;
                $('#issueMembers input.member-sp').each(function (i) {
                    if (sum === -1)
                        return;

                    let val = $(this).val();
                    if (val === '') {
                        sum = -1;
                        return;
                    }

                    let memberSp = val === '1/2' ? .5 : parseFloat(val);
                    sum += memberSp;
                });

                if (sum > 0) {
                    field.val(sum);
                    setTimeout(function () {
                        field.select();
                    }, 50);
                }
            }
        });

        const textInputs = [
            '#issueForm textarea[name=desc]',
            'form.add-comment textarea[name=commentText]',
            'form.pass-test #passTestComment textarea.comment-text-field'
        ];

        setupAutoComplete(textInputs);
        setupPasteTransformer(textInputs);

        // Настройка формы -- END

        // BEGIN -- Комментарии

        $(document).on('click', '.delete-comment', function () {
            const id = $(this).data('commentId');
            const el = $(this);
            const result = confirm('Удалить комментарий?');
            if (result) {
                issuePage.deleteComment(id, function (res) {
                    if (res) {
                        el.parents('div.comments-list-item').remove();
                    }
                });
            }
        });

        // Комментарии -- END

        if (!$('#is-admin').val()) {
            $('.delete-comment').each(function (index) {
                const elementId = $(this).attr('id');
                const startTime = $(this).data('time');
                hideElementAfterDelay(elementId, startTime);
            });
        }

        $('div.tooltip').hover(
            function () {
                $(this).find('div').clearQueue().show();
            },
            function () {
                $(this).find('div')
                    .animate({ width: 'width' + 20, height: 'height' + 20 }, 150)
                    .animate({ width: 'hide', height: 'hide' }, 1);
            }
        )

        bindFormattingHotkeys('#issueForm form textarea[name=desc]');
        bindFormattingHotkeys('form.add-comment textarea[name=commentText]');
        bindFormattingHotkeys('form.pass-test #passTestComment textarea.comment-text-field');
    }
);

function bindFormattingHotkeys(selector) {
    $(selector).keydown(function (e) {
        if (typeof this.selectionStart === 'undefined' || this.selectionStart == this.selectionEnd)
            return;

        if (e.ctrlKey || e.metaKey) {
            var code = e.originalEvent.code;
            switch (code) {
                case 'KeyB':
                    insertFormattingMarker(this, '*');
                    break;
                case 'KeyI':
                    insertFormattingMarker(this, '_');
                    break;
                case 'KeyU':
                    insertFormattingMarker(this, '__');
                    break;
                case 'KeyG':
                    insertFormattingMarker(this, '> ', true);
                    break;
                case 'KeyH':
                    insertFormattingMarker(this, '### ', true);
                    break;
                case 'KeyK':
                    insertFormattingLink(this);
                    break;
                default:
                    return;
            }

            event.stopImmediatePropagation();
            event.preventDefault();
        }
    });
}

function setupAutoComplete(selectors) {
    let tribute = new Tribute({
        collection: [
            createMembersAutoComplete(),
            createIssuesAutoComplete(),
        ]
    });

    for (var i = 0; i < selectors.length; i++) {
        tribute.attach($(selectors[i]).get());
    }
}


function createMembersAutoComplete() {
    var members = null;
    return {
        trigger: '@',
        selectTemplate: function (item) {
            let data = item.original;
            return '[@' + data.key + '](user:' + data.id + ')';
        },
        values: function (text, cb) {
            if (members !== null) {
                cb(members);
                return;
            }

            issuePage.loadMembers(function (list) {
                if (!list) {
                    cb([])
                } else {
                    members = [];
                    for (var i = 0; i < list.length; i++) {
                        let user = list[i];
                        let name = user.nick ? user.nick : user.firstName;

                        members[i] = { key: name, value: name, id: user.userId };
                    }
                    cb(members);
                }
            });
        },
    }
}

function createIssuesAutoComplete() {
    var cache = {};
    return {
        trigger: '#',
        searchOpts: {
            skip: true,
        },
        selectTemplate: function (item) {
            let data = item.original;
            return '[#' + data.key + '](' + data.url + ')';
        },
        menuItemTemplate: function (item) {
            let data = item.original;
            return '#' + data.key + ' ' + data.value;
        },
        noMatchTemplate: function () {
            return '<li>Задач не найдено.</li>';
        },
        values: function (text, cb) {
            if (!text) return;

            if (cache[text]) {
                cb(cache[text]);
                return;
            }

            srv.project.searchIssueNames(issuePage.projectId, text,
                function (res) {
                    if (res.success) {
                        let list = res.list.map((e) => {
                            return {
                                key: e.idInProject,
                                value: e.name,
                                url: e.url
                            };
                        });
                        cache[text] = list;
                        cb(list);
                    } else {
                        cb([]);
                        srv.err(res);
                    }
                });
        },
    };
}

function setupPasteTransformer(inputSelectors) {
    document.addEventListener('paste', function (event) {
        const target = event.target;

        if (!inputSelectors.some(sel => target.matches(sel))) {
            return;
        }

        const clipboardData = event.clipboardData || window.clipboardData;
        const pastedText = clipboardData.getData('text');
        if (pastedText.length === 0) return;

        const pattern = `^${lpmOptions.issueUrlPattern}$`;

        const trimmed = pastedText.trim();
        const match = trimmed.match(pattern);
        if (match) {
            event.preventDefault();

            const start = pastedText.indexOf(trimmed);
            const before = pastedText.substring(0, start);
            const after = pastedText.substring(start + trimmed.length);
            const markdownLink = `[#${match[2]}](${trimmed})`;
            insertTextAtCursor(target, before + markdownLink + after);
        }
    });
}

function insertTextAtCursor(element, text) {
  if (element.selectionStart !== undefined) {
    const start = element.selectionStart;
    const end = element.selectionEnd;
    const value = element.value;

    element.value = value.slice(0, start) + text + value.slice(end);
    element.selectionStart = element.selectionEnd = start + text.length;
  } 
  else if (element.isContentEditable) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    const range = selection.getRangeAt(0);
    range.deleteContents();
    range.insertNode(document.createTextNode(text));
    range.collapse(false);
  }
}

function DropDown(el) {
    this.dd = el;
    //this.placeholder = this.dd.children('span');
    this.opts = this.dd.find('ul#priority-values > li');
    this.val = '';
    this.initEvents();
}
DropDown.prototype = {
    initEvents: function () {
        var obj = this;

        obj.opts.click(function () {
            var opt = $(this);
            obj.val = opt.text();
            issuePage.setPriorityVal(obj.val.match(/\d+/) - 1);
        });
    }
}

const issuePage = {
    projectId: null,
    idInProject: null,
    labels: null,
    members: null,
    filterByTagVm: null, 
    getStatus: () => $('#issueInfo').data('status'),
    isCompleted: () => issuePage.getStatus() == 2,
    getIssueId: () => $('#issueView input[name=issueId]').val(),
    getRevision: () => $('#issueView input[name=revision]').val(),
    copyIssue: () => {
        const $copyLinkedField = $("#copyLinkedIssuesField", selectProject.element);
        issuePage.createIssueBy(
            (issueId) => 'copy-issue:' + issueId + ':' + ($copyLinkedField.prop("checked") ? 1 : 0),
            'copy'
        );
    },
    finishedIssue: () => { 
        const $kindField = $('#targetKindField', selectProject.element);
        issuePage.createIssueBy(
            (issueId) => 'finished-issue:' + issueId + ':' + $kindField.val(), 
            'finished',
            (projectId) => {
                const isCurrent = projectId == issuePage.projectId;
                let needResetVal = false;
                $('option', $kindField).each((_, item) => {
                    let visible = true;
                    $option = $(item);
                    switch ($option.val()) {
                        case 'apply':
                            visible = !isCurrent;
                            break;
                        case 'finished':
                            visible = isCurrent;
                            break;
                    }

                    if (visible) {
                        $option.show();
                    } else {
                        $option.hide();
                        needResetVal = needResetVal || $option.prop('selected');
                    }
                });

                if (needResetVal) {
                    $('option', $kindField).each((_, item) => {
                        $option = $(item);
                        if ($option.css('display') !== 'none') {
                            $option.prop('selected', true);
                            return false;
                        }
                    })
                }
            },
        );
    },
    createIssueBy: function (hash, mode, onProjectChanged) {
        const issueId = this.getIssueId();
        selectProject.show(this.projectId, issueId, (targetProject) => {
            const url = targetProject.url + '#' + (typeof hash === 'function' ? hash(issueId) : hash + ':' + issueId);
            window.open(url, '_blank');
        }, mode, onProjectChanged);
    },
};

issuePage.loadMembers = function (handler) {
    if (issuePage.members != null) {
        handler(issuePage.members);
    } else {
        srv.project.getMembers(issuePage.projectId, function (res) {
            if (res.success) {
                issuePage.members = res.members;
                handler(issuePage.members);
            } else {
                handler(null);
                srv.err(res);
            }
        });
    }
}

issuePage.updatePriorityVals = function () {
    issuePage.setPriorityVal($('input[type=range]#priority').val());
    //issuePage.setPriorityVal( $('input[type=range]#priority').val() );
    $('.priority-val.circle').each(function (i) {
        issuePage.updatePriorityVal($(this), parseInt($(this).text()));
        $(this).text('');
    });
};
issuePage.updatePriorityVal = function ($el, value) {
    $el.css('backgroundColor', issuePage.getPriorityColor(value));
}

issuePage.setPriorityVal = function (value) {
    let valueInt = parseInt(value);
    let title = Issue.getPriorityStr(valueInt);
    let displayVal = Issue.getPriorityDisplayVal(valueInt);
    $('#priority').val(valueInt);

    $('#priorityVal').html(title + ' (' + displayVal + '%)');
    $('#priorityVal').css('backgroundColor', issuePage.getPriorityColor(valueInt));
};

issuePage.upPriorityVal = function () {
    var value = $('#priority').val();
    if (value < 99) {
        value++;
        issuePage.setPriorityVal(value);
    };
}

issuePage.downPriorityVal = function () {
    var value = $('#priority').val();
    if (value > 0) {
        value--;
        issuePage.setPriorityVal(value);
    };
}

issuePage.getPriorityColor = function (val) {
    var v = Math.floor(val % 25 / 25 * 255);
    var r = 0;
    var g = 0;
    var b = 0;
    if (val < 25) {
        g = v;
        b = 255;
    } else if (val < 50) {
        g = 255;
        b = 255 - v;
    } else if (val < 75) {
        g = 255;
        r = v;
    } else {
        r = 255;
        g = 255 - v;
    }
    return 'rgba( ' + r + ', ' + g + ', ' + b + ', 0.8 )';
};

issuePage.updateStat = function () {
    if ($("#projectView").length == 0) return;

    $(".project-stat .issues-opened").text($("#issuesList > tbody > tr.active-issue,tr.verify-issue").size());
    $(".project-stat .issues-completed").text($("#issuesList > tbody > tr.completed-issue").size());

    // Перезапрашиваем сумму часов
    srv.project.getSumOpenedIssuesHours($("#projectView").data('projectId'), function (r) {
        if (r.success) {
            if (r.count > 0) {
                $(".project-stat .project-opened-issue-hours").show();
                $(".project-stat .issue-hours.value").text(r.count);
                // TODO склонения лейбла?
            }
            else {
                $(".project-stat .project-opened-issue-hours").hide();
            }
        }
    });
};

function insertFormattingLink(input) {
    const text = getSelectedText(input);
    if (parser.findLinks(text)) {
        insertFormatting(input, '[](', ')', 1);
    }
    else {
        insertFormatting(input, '[', ']()', -2);
    }
}

function insertFormattingMarker(input, marker, single) {
    insertFormatting(input, marker, single ? "" : marker)
}

function getSelectedText(input) {
    const text = $(input)[0];
    return text.value.substring(text.selectionStart, text.selectionEnd);
}

function insertFormatting(input, before, after, cursorShift) {
    let $input = $(input);
    let text = $input[0];
    let selectionStart = text.selectionStart;
    let subtext = text.value.substring(selectionStart, text.selectionEnd);

    let res = text.value.substring(0, selectionStart) +
        before + subtext + after +
        text.value.substring(text.selectionEnd, text.value.length);

    var caretPos = selectionStart;
    let fullLength = before.length + subtext.length + after.length;
    if (cursorShift) {
        // если отрицательный, то считаем с конца
        // -1 соответствует концу выражения
        if (cursorShift >= 0)
            caretPos += cursorShift;
        else
            caretPos += fullLength + cursorShift + 1;
    } else {
        // если нет выделенного текста, то ставим курсор внутри,
        // чтобы написали текст, а если есть - то за закрывающим тегом,
        // чтобы продолжали писать
        if (subtext == "")
            caretPos += before.length;
        else
            caretPos += fullLength;
    }

    $input.val(res).trigger('input');

    //устанавливаем курсор на полученную позицию
    setCaretPosition(text, caretPos);
}

function setCaretPosition(elem, pos) {
    elem.setSelectionRange(pos, pos);
    elem.focus();
}

function completeIssue(e) {
    var parent = e.currentTarget.parentElement;
    var issueId = $('input[name=issueId]', parent).val();
    if (issueId <= 0) return

    if (!confirm('Задача будет отмечена как завершенная. Продолжить?')) return;

    preloader.show();
    srv.issue.complete(
        issueId,
        function (res) {
            //btn.disabled = false;
            preloader.hide();
            if (res.success) {
                if ($('#issuesList').length > 0) {
                    $("#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])").remove();
                    showMain();
                } else if ($('#issueView').length > 0) {
                    setIssueInfo(new Issue(res.issue));
                }
                issuePage.updateStat();
            } else {
                srv.err(res);
            }
        }
    );
}

issuePage.changePriority = function (e) {
    var $control = $(e.currentTarget);
    var $row = $control.parents('tr');
    var issueId = $('input[name=issueId]', $row).val();
    var delta = $control.hasClass('priority-up') ? 1 : -1;

    if (issueId > 0) {
        srv.issue.changePriority(issueId, delta, function (res) {
            if (res.success) {
                let priority = res.priority;
                let priorityStr = Issue.getPriorityStr(priority);
                let priorityVal = Issue.getPriorityDisplayVal(priority);
                let tooltipHost = $('.priority-title-owner', $row);
                tooltipHost.attr('title', 'Приоритет: ' + priorityStr + ' (' + priorityVal + '%)');
                let tooltips = $(document).uitooltip('instance').tooltips;
                for (var prop in tooltips) {
                    let item = tooltips[prop];
                    let element = item.element;
                    if (element[0] == tooltipHost[0]) {
                        let tooltip = item.tooltip;
                        // TODO: кривой способ, ломает следующее открытие
                        // но так и не получилось адекватно закрыть тултипы
                        // надо еще разбираться
                        tooltip.remove();
                    }
                }

                $('.priority-val', $row).data("value", priority);
                issuePage.updatePriorityVal($('.priority-val', $row), priority);

                var hintY = e.pageY - 13;
                $("<span></span>").text(priorityVal).addClass("priority-change-animation").
                    appendTo($('body')).offset({ top: hintY, left: e.pageX - 10 }).
                    animate(
                        {
                            opacity: '0',
                            top: '-=20px'
                        }, 500, function () {
                            $(this).remove();
                        });

                var status = $row.data("status");
                var date = $row.data("completeDate");
                var compare = function ($r) {
                    if ($r.data("status") != status)
                        return 0;
                    var p = parseInt($(".priority-val", $r).data("value"));
                    if (p != priority)
                        return priority - p;
                    else if ($r.data("completeDate") != date)
                        return $r.data("completeDate") - date;
                    else
                        return $r.data("id") - issueId;
                }

                if (delta < 0) {
                    var $next = $row;
                    var $last = null;
                    while ($next) {
                        var $next = $next.next();

                        if (compare($next) < 0) {
                            $last = $next;
                        }
                        else {
                            if ($last) {
                                $last.after($row);
                                highlightIssueRow($row);
                            }
                            break;
                        }
                    }
                } else {
                    var $prev = $row;
                    var $first = null;
                    while ($prev) {
                        var $prev = $prev.prev();
                        if (compare($prev) > 0) {
                            $first = $prev;
                        }
                        else {
                            if ($first) {
                                $first.before($row);
                                highlightIssueRow($row);
                            }
                            break;
                        }
                    }
                }
            } else {
                srv.err(res);
            }
        });
    }
}

function restoreIssue(e) {
    var parent = e.currentTarget.parentElement;
    var issueId = $('input[name=issueId]', parent).val();
    preloader.show();

    srv.issue.restore(
        issueId,
        function (res) {
            preloader.hide();
            if (res.success) {
                if ($('#issuesList').length > 0) {
                    $("#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])").remove();
                    showMain();
                } else if ($('#issueView').length > 0) {
                    setIssueInfo(new Issue(res.issue));
                }
                issuePage.updateStat();
            } else {
                srv.err(res);
            }
        }
    );
};

function verifyIssue(e) {
    var parent = e.currentTarget.parentElement;

    var issueId = $('input[name=issueId]', parent).val();
    preloader.show();

    srv.issue.verify(
        issueId,
        function (res) {
            preloader.hide();
            if (res.success) {
                if ($('#issueView').length > 0) {
                    setIssueInfo(new Issue(res.issue));
                }
                issuePage.updateStat();
            } else {
                srv.err(res);
            }
        }
    );
};

issuePage.removeIssue = function (e) {
    if (confirm('Вы действительно хотите удалить эту задачу?')) {
        var btn = e.currentTarget;
        var issueId = $('input[type=hidden][name=issueId]', btn.parentElement).val();

        preloader.show();

        srv.issue.remove(
            issueId,
            function (res) {
                preloader.hide();
                if (res.success) {
                    //window.location.hash = '';
                    window.location.href = $("#issueView a.back-link").attr('href');
                    //window.location.reload();
                } else {
                    srv.err(res);
                }
            }
        );
    }
};

issuePage.putStickerOnBoard = function () {
    preloader.show();
    const issueId = $('#issueInfo').data('issueId');
    srv.issue.putStickerOnBoard(issueId, function (res) {
        preloader.hide();
        if (res.success) {
            $('#issueInfo h3 .scrum-put-sticker').remove();
            $('#issueInfo').data('isOnBoard', true);
            issuePage.scrumColUpdateInfo();
        }
    });
};

function showIssue(issueId) {
    srv.issue.load(
        issueId,
        false,
        function (res) {
            if (res.success) {
                states.setState('issue-view');
                setIssueInfo(new Issue(res.issue));
            } else {
                srv.err(res);
            }
        }
    );
};

issuePage.showAddForm = function (type) {
    states.setState('add-issue');

    if (typeof type != 'undefined') {
        $('form input:radio[name=type]:checked', "#issueForm").prop('checked', true);
        $('form input:radio[value=1]', "#issueForm").prop('checked', true);

        const bugTemplate = `### Описание

📝 Описание проблемы

### Предусловие

📝 Начальные условия, при которых воспроизводится проблема

### Шаги воспроизведения

1. 📝  Шаги для воспроизведения
2. 

*ФР*: 📝  Фактический полученный результат

*ОР*: 📝  Ожидаемый результат

### Окружение

📝 Укажите устройство, ОС, окружение и тп

### Видео

🎥 Приложите ссылку на видео, где показана проблема
        `;
        
        $('form textarea[name=desc]', '#issueForm').html(bugTemplate).css('height', '500px');
    } else {
        $('form input:radio[name=type]:checked', "#issueForm").prop('checked', true);
        $('form input:radio[value=0]', "#issueForm").prop('checked', true);
        $('form textarea[name=desc]', '#issueForm').html('').css('height', '');
    }
};

issuePage.showEditForm = function () {
    issueForm.acquireLock(issuePage.getIssueId(), issuePage.getRevision(), false, function () {
        // переключаем вид
        states.setState('edit');
    });
};

/**
 * 
 * @param {Issue} issue
 */
function setIssueInfo(issue) {
    $("#issueInfo > h3 .issue-name").text(issue.name);
    const fields = $("#issueInfo > .info-list > div > .value");

    //$( "#issueInfo .buttons-bar > button.restore-btn"  ).hide();
    //$( "#issueInfo .buttons-bar > button.complete-btn" ).hide();

    $("#issueView").removeClass('issue-testing');

    $("#issueInfo .info-list").
        removeClass('active-issue').
        removeClass('verify-issue').
        removeClass('completed-issue');

    $("#issueInfo .buttons-bar").
        removeClass('active-issue').
        removeClass('verify-issue').
        removeClass('completed-issue');

    if (issue.isCompleted()) {
        //$( "#issueInfo .buttons-bar > button.restore-btn" ).show();
        $("#issueInfo .buttons-bar").addClass('completed-issue');
        $("#issueInfo .info-list").addClass('completed-issue');
    } else if (issue.isOpened()) {
        //$( "#issueInfo .buttons-bar > button.complete-btn" ).show();
        $("#issueInfo .buttons-bar").addClass('active-issue');
        //$( "#issueInfo .buttons-bar" ).addClass( 'verify-issue' );
        $("#issueInfo .info-list").addClass('active-issue');
    } else if (issue.isVerify()) {
        $("#issueInfo .buttons-bar").addClass('verify-issue');
        $("#issueInfo .info-list").addClass('verify-issue');
        $("#issueView").addClass('issue-testing');
    }

    const testers = issue.getTesters();
    const masters = issue.getMasters();

    const values = [
        issue.getStatus(),
        issue.getType(),
        issue.getPriority(),
        issue.getCreateDate(),
        issue.getCompleteDate(),
        issue.getCompletedDate(),
        issue.getAuthor(),
        issue.getMembers(),
        testers,
        masters,
        issue.getDesc(true)
    ];

    for (var i = 0; i < values.length; i++) {
        fields[i].innerHTML = values[i];
    }

    const $completedDate = $('#issueInfo .issue-complete-date-row');
    if (issue.hasCompleteDate())
        $completedDate.show();
    else 
        $completedDate.hide();

    if (testers)
        $('#issueInfo .testers-row').show();
    else
        $('#issueInfo .testers-row').hide();

    if (masters)
        $('#issueInfo .masters-row').show();
    else
        $('#issueInfo .masters-row').hide();

    issuePage.updatePriorityVals();

    $("#issueInfo > p > input[name=issueId]").val(issue.id);
    $('#issueInfo').data('status', issue.status);
};

issuePage.createBranch = function () {
    createBranch.show(issuePage.projectId, issuePage.getIssueId(), issuePage.idInProject);
}

issuePage.commentPassTesting = function () {
    issuePage.passTest();
};

issuePage.commentMergeInDevelop = function () {
    issuePage.merged();
};

issuePage.postComment = function () {
    const text = $('#issueView .comments form.add-comment textarea[name=commentText]').val();
    const requestChanges = $('#issueView .comments form.add-comment input[name=requestChanges]').is(':checked');
    issuePage.postCommentForCurrentIssue(text, requestChanges);
    return false;
};

issuePage.previewComment = function (tabs) {
    let text = $('textarea[name=commentText]', tabs).val();

    let previewItem = $('.preview-comment', tabs);
    previewItem.empty().append(preloader.getNewIndicatorMedium());

    srv.issue.previewComment(text, (res) => {
        if (res.success) {
            previewItem.html(res.html);

            comments.updateAttachments($('.comment-text', previewItem));
            attachments.update($('.block-with-attachments', previewItem));
        } else {
            srv.err(res);
        }
    });
};

issuePage.doSomethingAndPostCommentForCurrentIssue = function (srvCall, onSuccess) {
    var issueId = $('#issueView .comments form.add-comment input[name=issueId]').val();

    // TODO проверку на пустоту
    if (issueId > 0) {
        preloader.show();
        srvCall(
            issueId,
            function (res) {
                preloader.hide();
                if (res.success) {
                    issuePage.addComment(res.comment, res.html);
                    if (onSuccess) onSuccess(res);
                } else {
                    srv.err(res);
                }
            }
        );
    }
}

issuePage.postCommentForCurrentIssue = function (text, requestChanges = false) {
    if (text == '') return;

    issuePage.doSomethingAndPostCommentForCurrentIssue(
        (issueId, handler) => srv.issue.comment(issueId, text, requestChanges, handler));
}

issuePage.merged = function () {
    let doMerge = function (complete) {
        issuePage.doSomethingAndPostCommentForCurrentIssue(
            (issueId, handler) => srv.issue.merged(issueId, complete, handler),
            res => {
                if (res.issue)
                    setIssueInfo(new Issue(res.issue));
                issuePage.updateStat();
            });
    }

    if (issuePage.isCompleted()) {
        doMerge(false);
    } else {
        $("#completeOnMergeConfirm").dialog({
            resizable: false,
            height: "auto",
            width: 400,
            modal: true,
            buttons: {
                Cancel: function () {
                    $(this).dialog("close");
                },
                No: function () {
                    doMerge(false);
                    $(this).dialog("close");
                },
                Yes: function () {
                    doMerge(true);
                    $(this).dialog("close");
                },
            },
            open: function () {
                $(this).parent().find('.ui-dialog-buttonpane button:nth-child(3)').focus();
            }
        });
    }
}

issuePage.passTest = function () {
    passTest.show(issuePage.getIssueId());
}

issuePage.addComment = function (comment, html) {
    let elementId = 'comment_' + comment.id;
    let commentTime = comment.date;
    $('#issueView .comments form.add-comment textarea[name=commentText]').val('');
    $('#issueView .comments .comments-list').prepend(
        '<div class="comments-list-item">' + html + '</div>'
    );

    let newItem = $('#issueView .comments .comments-list .comments-list-item').first()
    comments.updateAttachments($('.comment-text', newItem));
    attachments.update($('.block-with-attachments', newItem));

    comments.hideCommentForm();

    hideElementAfterDelay(elementId, commentTime);
};

issuePage.handleOnlyMeFilter = function () {
    issuePage.showIssues4Me();
}

issuePage.showIssues4Me = function () {
    issuePage.filterByMemberId(lpInfo.userId);

    $('#showIssues4MeLink').hide();
    $('#showIssues4AllLink').show();
    return false;
};

issuePage.handleLastCreatedSort = function () {
    issuePage.showLastCreated();
}

issuePage.showLastCreated = function () {
    var table = $('#issuesList');
    window.defaultIssues = table.html();
    table.find('tr:not(:first)').sort(function (a, b) {
        return $(b).data('createDate') - $(a).data('createDate');
    }).appendTo(table);
    $('#showLastCreated').hide();
    $('#sortDefault').show();
    return false;
};

issuePage.sortDefault = function () {
    window.location.hash = '';
    var table = $('#issuesList');
    table.html(window.defaultIssues);
    $('#sortDefault').hide();
    $('#showLastCreated').show();
};

issuePage.handleShowIssuesByUser = function (memberId) {
    issuePage.showIssuesByUser(memberId);
}

issuePage.showIssuesByUser = function (memberId) {
    issuePage.filterByMemberId(memberId);
    $('#showIssues4MeLink').hide();
    $('#showIssues4AllLink').show();
    return false;
};

issuePage.filterByMemberId = function (userId) {
    var list = document.getElementById('issuesList');
    var rows = list.tBodies[0].children;
    var row, fields = null;
    var hide = true;

    for (var i = 0; i < rows.length; i++) {
        row = rows[i];
        hide = true;

        //if (!row.classList.contains('verify-issue')) {
        fields_members = row.children[3].getElementsByTagName('a');
        for (var j = 0; j < fields_members.length; j++) {
            if (fields_members[j].getAttribute('data-member-id') == userId) {
                hide = false;
                break;
            }
        }
        // }

        if (hide)
            row.hide();
        else
            row.show();
    }
};

issuePage.resetFilter = function ()//e) 
{
    //$( '#issuesList > tbody > tr' ).show();
    window.location.hash = '';
    var rows = document.getElementById('issuesList').tBodies[0].children;

    for (var i = 0; i < rows.length; i++) {
        rows[i].show();
    }

    $('#showIssues4AllLink').hide();
    $('#showIssues4MeLink').show();
    return false;
};

issuePage.handleTagsFilterState = function (value) {
    console.log('value:', value);
    const tags = value.trim() == '' ? [] : decodeURI(value).split(',');
    issuePage.filterByTagVm.selectedTags = tags;
}

issuePage.onFilterByTagChanged = function (tags)  {
    issuePage.scrumColUpdateInfo(tags);
    if (tags.length)  {
        states.setState('tags:' + encodeURI(tags.join(',')), true);
    } else {
        states.setState('', true);
    }
};

issuePage.scrumColUpdateInfo = function () {
    const cols = ['col-todo', 'col-in_progress', 'col-testing', 'col-done'];
    const getColStickersSelector = (col) =>
        '#scrumBoard .scrum-board-table .scrum-board-col.' + col + ' .scrum-board-sticker:visible';

    let totalSP = 0;
    let totalNum = 0;
    for (let i = 0; i < cols.length; ++i) {
        const col = cols[i];
        const colStickers = $(getColStickersSelector(col));

        let sp = 0;
        colStickers.each((i, el) => {
            sp += parseFloat($(el).data('stickerSp'));
        });

        let num = colStickers.size();

        let selector = '#scrumBoard .scrum-board-table .' + col + ' .scrum-col-info';

        if (num > 0) {
            $(selector + ' .scrum-col-count .value').html(num);

            let spSelector = selector + ' .scrum-col-sp';
            if (sp > 0)
                $(spSelector).show();
            else
                $(spSelector).hide();

            let spScr = parseInt(sp) == sp ? sp : sp.toFixed(1);
            $(spSelector + ' .value').html(spScr);

            totalSP += sp;
            totalNum += num;

            $(selector).show();
        } else {
            $(selector).hide();
        }
    }

    if (totalNum) {
        $('#scrumBoard .scrum-board-info').show();
        $('#scrumBoard .scrum-board-info .scrum-board-count .value').html(totalNum);
        if (totalSP > 0) {
            let totalSpScr = parseInt(totalSP) == totalSP ? totalSP : totalSP.toFixed(1);
            $('#scrumBoard .scrum-board-sp').show().find('.value').html(totalSpScr);
        }
        else
            $('#scrumBoard .scrum-board-sp').hide();
    } else {
        $('#scrumBoard .scrum-board-info').hide();
    }
}

issuePage.showExportXls = function () {
    issuesExport2Excel.openWindow(parseInt($("#projectView").data('projectId')));
}

function Issue(obj) {
    this._obj = obj;

    this.id = obj.id;
    this.author = obj.author;
    this.completeDate = obj.completeDate;
    this.completedDate = obj.completedDate;
    this.createDate = obj.createDate;
    this.desc = obj.desc;
    this.formattedDesc = obj.formattedDesc;
    this.name = obj.name;
    this.status = obj.status;
    this.type = obj.type;
    this.members = obj.members;
    this.priority = obj.priority;
    this.hours = obj.hours;
    this.testers = obj.testers;
    this.masters = obj.masters;
    this.images = obj.images;
    this.isOnBoard = obj.isOnBoard;
    this.url = obj.url;
    this.linked = obj.linked;

    const getUsersStr = (list) => {
        var str = '';
        if (list)
            for (var i = 0; i < list.length; i++) {
                if (i > 0) str += ', ';
                str += list[i].linkedName;
            }
        return str;
    };

    this.getCompleteDate = function () {
        return this.getDate(this.completeDate);
    };

    this.hasCompleteDate = function () {
        return this.completeDate != 0;
    };

    this.getCompleteDateInput = function () {
        var d = this.getCompleteDate();

        if (d)
            d = d.replace(/-/g, '/');

        return d;
    };

    this.getCompletedDate = function () {
        return this.getDate(this.completedDate);
    };

    this.getCreateDate = function () {
        return this.getDate(this.createDate);
    };

    this.getAuthor = function () {
        return this.author ? this.author.linkedName : '';
    };

    this.getPriority = function () {
        var val = Issue.getPriorityDisplayVal(this.priority);
        return '<span class="priority-val circle">' + this.priority + '</span>' +
            Issue.getPriorityStr(val) + ' (' + val + '%)';
    };

    this.getMembers = function () {
        var str = '';
        if (this.members) {
            for (var i = 0; i < this.members.length; i++) {
                var member = this.members[i];
                if (i > 0) str += ', ';
                str += this.members[i].linkedName;
                if (member.sp)
                    str += " (" + member.sp + " SP)";
            }
        }

        return str ? str : 'Не назначены';
    };

    this.getMemberIds = function () {
        return this.members.map(member => member.userId);
    };

    this.getMembersSp = function () {
        return this.members.map(member => member.sp);
    };

    this.getTesters = () => getUsersStr(this.testers);

    this.getTesterIds = function () {
        return this.testers.map(tester => tester.userId);
    };

    this.getMasters = () => getUsersStr(this.masters);

    this.getMasterIds = function () {
        return this.masters.map(master => master.userId);
    };

    this.getLinkedBaseIds = function () {
        return this.linked?.filter(i => i.isBaseLinked)?.map(i => i.id) ?? [];
    };

    this.getLinkedChildrenIds = function () {
        return this.linked?.filter(i => !i.isBaseLinked)?.map(i => i.id) ?? [];
    };

    this.getDesc = function (formatted = false) {
        return formatted ? this.formattedDesc : this.desc;
    };

    this.getStatus = function () {
        switch (this.status) {
            case 1: return 'Ожидает проверки';
            case 2: return 'Завершена';
            default: return 'В работе';
        }
    };

    this.getType = function () {
        switch (this.type) {
            case 1: return 'Ошибка';
            case 2: return 'Поддержка';
            default: return 'Разработка';
        }
    };

    this.isCompleted = function () {
        return this.status == 2;
    };

    this.isOpened = function () {
        return this.status == 0;
    };

    this.isVerify = function () {
        return this.status == 1;
    };

    this.getDate = function (value) {
        if (!value) return '';

        const date = new Date((value + 3600) * 1000);
        // TODO разобраться что за хрень - почему на час разница?

        //return this._num2Str( date.getDate() ) + '-' + this._num2Str( date.getMonth() + 1 ) + '-' + date.getFullYear() + 
        //' ' + date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds() + ':' + date.getMilliseconds();

        return this._num2Str(date.getDate()) + '-' + this._num2Str(date.getMonth() + 1) + '-' + date.getFullYear();
    };

    this.getImagesUrl = function () {
        return this.images.map(img => img.source)
    };

    this._num2Str = function (val, dig) {
        if (!dig || dig < 1) dig = 1;
        else dig -= 1;

        var str = '';
        if (val < 0) str += '-';
        val = Math.abs(val);

        var i = dig - Math.floor(Math.log(val) / Math.log(10));
        while (i > 0) {
            str += '0';
            i--;
        }

        str += val;

        return str;
    };
};

/**
 * @param {Number} priority = 0..99
 */
Issue.getPriorityStr = function (priority) {
    if (priority < 33) return 'низкий';
    else if (priority < 66) return 'нормальный';
    else return 'высокий';
};

/**
 * @param {Number} priority = 0..99
 */
Issue.getPriorityDisplayVal = function (priority) {
    return priority + 1;
};

Issue.getCommitMessage = function (num, title) {
    return 'Issue #' + num + ': ' + title;
}

/**
 * Возвращает название задачи "По доделкам"
 */
Issue.getCompletionName = function (issueName, prefix = 'Доделать задачу') {
    const lastTagIndex = issueName.lastIndexOf(']');
    return (~lastTagIndex) ?
        `${issueName.substring(0, lastTagIndex + 1)} ${prefix} ${issueName.substring(lastTagIndex + 1).trim()}`
        : `${prefix} ${issueName.trim()}`;
}

// Всплывающее окно скопировать commit сообщение

jQuery(function ($) {

    $('.issues-list > tbody > tr > td:first-of-type a').mouseenter(
        function () {
            $(this).next('.issue_copy.popup-menu').slideDown(180);
        }
    );

    $('.issues-list > tbody > tr > td:first-of-type').mouseleave(
        function () {
            $('.issue_copy.popup-menu').slideUp(180);
        }
    );

    $('.issue_copy.popup-menu').hover(
        function () {
            $(this).show();
        },
        function () {
            $(this).slideUp(180);
        }
    );

});

issuePage.deleteComment = (id, callback) => {
    srv.issue.deleteComment(
        id,
        function (res) {
            if (res.success) {
                callback(true);
            } else {
                srv.err(res);
            }
        }
    )
};

function hideElementAfterDelay(elementId, startTimeInSeconds, delayTimeInSeconds = 600) {
    let delay = (Number(startTimeInSeconds) + Number(delayTimeInSeconds)) * 1000 - Date.now();

    if (delay >= 0) {
        const timerId = setTimeout(() => {
            $('#' + elementId).remove();
            clearTimeout(timerId);
        }, delay);
    } else {
        $('#' + elementId).remove();
    }
}


function highlightIssueRow($row) {
    $row
        .css("backgroundColor", "#e0cffc")
        .animate({ backgroundColor: "#ffffff" }, 3000);
}