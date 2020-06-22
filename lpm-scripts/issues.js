$(document).ready(
    function () {
        //$( '#issueView .comments form.add-comment' ).hide();
        issuePage.projectId = parseInt($('#issueProjectID').val());
        issuePage.updatePriorityVals();
        issuePage.scumColUpdateInfo();
        var dd = new DropDown($('#dropdown'));

        $('#issuesList .member-list a').click(function (e) {
            issuePage.showIssuesByUser($(e.currentTarget).data('memberId'));
        });


        // BEGIN -- Настройка формы 

        $('#issueForm .note.tags-line a.tag').click(function (e) {
            var a = $(e.currentTarget);
            insertMarker(a.data('marker'));
        });

        $('#issueForm input[name=hours]').focus(function (e) {
            let field = $(e.currentTarget);
            if (!field.val()) {
                var sum = 0;
                $('#issueForm input.member-sp').each(function (i) {
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

        setupMembersAutoComplete(['#issueForm textarea[name=desc]',
            'form.add-comment textarea[name=commentText]']);

        // Настройка формы -- END

        // BEGIN -- Комментарии

        $(document).on('click', '.delete-comment', function () {
            let id = $(this).attr('data-comment-id');
            let el = $(this);
            let result = confirm('Удалить комментарий?');
            if (result) {
                issuePage.deleteComment(id, function (res) {
                    if (res) {
                        el.parent('div.comments-list-item').remove();
                        el = null;
                    }
                });
            }
        });

        // Комментарии -- END

        if (!$('#is-admin').val()) {
            $('.delete-comment').each(function (index) {
                let elementId = $(this).attr('id');
                let startTime = $(this).attr('data-time');
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
    }
);

function bindFormattingHotkeys(selector) {
    $(selector).keypress(function (e) {
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
                default:
                    return;
            }

            event.stopImmediatePropagation();
            event.preventDefault();
        }
    });
}

function setupMembersAutoComplete(selectors) {
    var members = null;
    let tribute = new Tribute({
        trigger: '@',
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

                        members[i] = { key: name, value: name };
                    }
                    cb(members);
                }
            });
        },
    });

    for (var i = 0; i < selectors.length; i++) {
        tribute.attach($(selectors[i]).get());
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

var issuePage = {
    projectId: null,
    members: null
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
    var valStr = Issue.getPriorityStr(value);
    $('#priority').val(value);
    value++;
    $('#priorityVal').html(valStr + ' (' + value + '%)');
    $('#priorityVal').css('backgroundColor', issuePage.getPriorityColor(value - 1));
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
    //$( ".project-stat .issues-total" ).text( $( "#issuesList > tbody > tr" ).size() );
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

function insertMarker(marker) {
    insertFormattingMarker($('#issueForm textarea[name=desc]'), marker);
}

function insertFormattingMarker(input, marker, single) {
    var $input = $(input);
    var text = $input[0];
    var selectionStart = text.selectionStart;
    var subtext = text.value.substring(selectionStart, text.selectionEnd);
    var caretPos = 0;
    const closetag = single ? "" : marker;
    //Если в описании задачи есть текст
    if (!$.isEmptyObject({ text })) {
        // берем все, что до выделения
        var desc = text.value.substring(0, selectionStart) +
            marker + subtext + closetag +
            text.value.substring(text.selectionEnd, text.value.length);
        //определяем позицию курсора(перед закрывающим тэгом)
        //если есть выделенный текст
        if (subtext == "")
            //определяем фокус перед '/' тэгом
            caretPos = selectionStart + marker.length;
        else //после тэга       
            caretPos = selectionStart + subtext.length + marker.length * 2;

        //добавляем итог в описание задачи
        $input.val(desc);

        //устанавливаем курсор на полученную позицию
        setCaretPosition(text, caretPos);
    }
}

function setCaretPosition(elem, pos) {
    elem.setSelectionRange(pos, pos);
    elem.focus();
}

function completeIssue(e) {
    var parent = e.currentTarget.parentElement;
    var issueId = $('input[name=issueId]', parent).val();

    if (issueId > 0) {
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
}

issuePage.changePriority = function (e) {
    var $control = $(e.currentTarget);
    var $row = $control.parents('tr');
    var issueId = $('input[name=issueId]', $row).val();
    var delta = $control.hasClass('priority-up') ? 1 : -1;

    if (issueId > 0) {
        srv.issue.changePriority(issueId, delta, function (res) {
            if (res.success) {
                // alert('ok: ' + res.priority);
                var priority = res.priority;
                var priorityStr = Issue.getPriorityStr(priority);
                $('.priority-val', $row).attr('title', 'Приоритет: ' + priorityStr +
                    ' (' + priority + ')').data("value", priority);
                issuePage.updatePriorityVal($('.priority-val', $row), priority);

                var hintY = e.pageY - 13;
                $("<span></span>").text(priority).addClass("priority-change-animation").
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
                            if ($last)
                                $last.after($row);
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
                            if ($first)
                                $first.before($row);
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


issuePage.putStickerOnBoard = function (issueId) {
    preloader.show();
    srv.issue.putStickerOnBoard(issueId, function (res) {
        preloader.hide();
        if (res.success) {
            $('#issueInfo h3 .scrum-put-sticker').remove();
            $('#putToBoardField').prop('checked', true);
            issuePage.scumColUpdateInfo();
        }
    });
};

function showIssue(issueId) {
    srv.issue.load(
        issueId,
        function (res) {
            if (res.success) {
                window.location.hash = 'issue-view';
                // if (window.location.search == '') window.location.search += '?';
                //else window.location.search += '&';
                //window.location.search += 'iid=' + issueId;
                states.updateView();
                setIssueInfo(new Issue(res.issue));
            } else {
                srv.err(res);
            }
        }
    );
};

issuePage.showAddForm = function (type, parentId) {
    window.location.hash = 'add-issue';
    states.updateView();

    if (typeof type != 'undefined') {
        $('form input:radio[name=type]:checked', "#issueForm").prop('checked', true);
        $('form input:radio[value=1]', "#issueForm").prop('checked', true);
    } else {
        $('form input:radio[name=type]:checked', "#issueForm").prop('checked', true);
        $('form input:radio[value=0]', "#issueForm").prop('checked', true);
    }
};

issuePage.showEditForm = function () {
    // переключаем вид
    window.location.hash = 'edit';
    states.updateView();
};

function removeImage(imageId) {
    if (confirm('Вы действительно хотите удалить это изображение?')) {
        $('#issueForm form .images-list > li').has('input[name=imgId][value=' + imageId + ']').remove();
        var val = $('#issueForm form input[name=removedImages]').val();
        if (val != '') val += ',';
        val += imageId;
        $('#issueForm form input[name=removedImages]').val(val);
    }
}

/**
 * 
 * @param {Issue} issue
 */
function setIssueInfo(issue) {
    $("#issueInfo > h3 .issue-name").text(issue.name);
    var fields = $("#issueInfo > .info-list > div > .value");

    //$( "#issueInfo .buttons-bar > button.restore-btn"  ).hide();
    //$( "#issueInfo .buttons-bar > button.complete-btn" ).hide();

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
    }

    var testers = issue.getTesters();

    var values = [
        issue.getStatus(),
        issue.getType(),
        issue.getPriority(),
        issue.getCreateDate(),
        issue.getCompleteDate(),
        issue.getCompletedDate(),
        issue.getAuthor(),
        issue.getMembers(),
        testers,
        issue.getDesc(true)
    ];

    for (var i = 0; i < values.length; i++) {
        fields[i].innerHTML = values[i];
    }

    if (testers)
        $('#issueInfo .testers-row').show();
    else
        $('#issueInfo .testers-row').hide();

    issuePage.updatePriorityVals();

    $("#issueInfo > p > input[name=issueId]").val(issue.id);
};

issuePage.showCommentForm = function () {
    $('#issueView .comments form.add-comment').show();
    $('#issueView .comments .links-bar a').hide();
    $('#issueView .comments form.add-comment textarea[name=commentText]').focus();
};

issuePage.hideCommentForm = function () {
    $('#issueView .comments form.add-comment').hide();
    $('#issueView .comments .links-bar a').show();
};

issuePage.toogleCommentForm = function () {
    var link = $('#issueView .comments .links-bar a.toggle-comments');
    var comments = $('#issueView .comments .comments-list');
    if (!comments.is(':visible')) {
        link.html('Свернуть комментарии');
    } else {
        link.html('Показать комментарии (' +
            $('#issueView .comments .comments-list .comments-list-item').size() + ')');
    }

    link.show();
    comments.slideToggle('normal');
};

issuePage.commentPassTesting = function () {
    //issuePage.postCommentForCurrentIssue('Прошла тестирование');
    issuePage.passTest();
};

issuePage.commentMergeInDevelop = function () {
    issuePage.postCommentForCurrentIssue('`-> develop`');
};

issuePage.postComment = function () {
    var text = $('#issueView .comments form.add-comment textarea[name=commentText]').val();
    issuePage.postCommentForCurrentIssue(text);
    return false;
};

issuePage.postCommentForCurrentIssue = function (text) {
    var issueId = $('#issueView .comments form.add-comment input[name=issueId]').val();

    // TODO проверку на пустоту
    if (issueId > 0 && text != '') {
        preloader.show();
        srv.issue.comment(
            issueId,
            text,
            function (res) {
                preloader.hide();
                if (res.success) {
                    issuePage.addComment(res.comment);
                } else {
                    srv.err(res);
                }
            }
        );
    }
}

issuePage.passTest = function () {
    var issueId = $('#issueView .comments form.add-comment input[name=issueId]').val();

    // TODO проверку на пустоту
    if (issueId > 0) {
        preloader.show();
        srv.issue.passTest(
            issueId,
            function (res) {
                preloader.hide();
                if (res.success) {
                    issuePage.addComment(res.comment);
                } else {
                    srv.err(res);
                }
            }
        );
    }
}

issuePage.addComment = function (comment) {
    // TODO: выпилить отсюда шаблон - сложно поддерживать и не отображает видео и MR
    let userId = $('#user-id-hidden').val();
    let elementId = 'comment_' + comment.id;
    let commentTime = comment.date;
    $('#issueView .comments form.add-comment textarea[name=commentText]').val('');
    $('#issueView .comments .comments-list').prepend(
        '<div class="comments-list-item">' +
        '<p class="delete-comment" id="' + elementId + '" data-comment-id="' + comment.id + '" data-user-id="' + userId + '"' +
        '               data-time="' + commentTime + '">Удалить</p>' +
        '<img src="' + comment.author.avatarUrl + '" class="user-avatar small"/>' +
        '<p class="author">' + comment.author.linkedName + '</p> ' +
        '<p class="date"><a class="anchor" id="' + comment.id +
        '"href="#comment-' + comment.id + '">' + comment.dateLabel + '</a></p>' +
        '<article class="text formatted-desc">' + comment.text + '</p>' +
        '</div>'
    );
    issuePage.hideCommentForm();
    $('#issueView .comments .links-bar a.toggle-comments').show();

    if (!$('#issueView .comments .comments-list').is(':visible'))
        issuePage.toogleCommentForm();

    hideElementAfterDelay(elementId, commentTime);
};

issuePage.showIssues4Me = function () {
    window.location.hash = 'only-my';
    issuePage.filterByMemberId(lpInfo.userId);

    $('#showIssues4MeLink').hide();
    $('#showIssues4AllLink').show();
    return false;
};

issuePage.showLastCreated = function () {
    window.location.hash = 'last-created';
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

issuePage.showIssuesByUser = function (memberId) {
    window.location.hash = 'by-user:' + memberId;
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
    //$('#showIssues4MeLink').text('Показать только мои задачи').
    //    click(issuePage.showIssues4Me);
    //e.currentTarget.onclick =issuePage.showIssues4Me;//= "issuePage.showIssues4Me(event); return false;";
    //e.currentTarget.innerText = 'Показать только мои задачи';
    return false;
};

issuePage.scumColUpdateInfo = function () {
    var cols = ['col-todo', 'col-in_progress', 'col-testing', 'col-done'];
    var totalSP = 0;
    var totalNum = 0;
    for (var i = 0; i < cols.length; ++i) {
        var col = cols[i];

        var sp = 0;
        $('#scrumBoard .scrum-board-table .scrum-board-col.' + col + ' .scrum-board-sticker').
            each(function (i, el) {
                sp += parseFloat($(el).data('stickerSp'));
            });
        var num = $('#scrumBoard .scrum-board-table .scrum-board-col.' + col + ' .scrum-board-sticker').size();

        var selector = '#scrumBoard .scrum-board-table .' + col + ' .scrum-col-info';

        if (num > 0) {
            $(selector + ' .scrum-col-count .value').html(num);

            var spSelector = selector + ' .scrum-col-sp';
            if (sp > 0)
                $(spSelector).show();
            else
                $(spSelector).hide();

            var spScr = parseInt(sp) == sp ? sp : sp.toFixed(1);
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
            var totalSpScr = parseInt(totalSP) == totalSP ? totalSP : totalSP.toFixed(1);
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
    this.images = obj.images;
    this.isOnBoard = obj.isOnBoard;
    this.url = obj.url;

    this.getCompleteDate = function () {
        return this.getDate(this.completeDate);
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
        var val = (this.priority + 1);
        return '<span class="priority-val circle">' + this.priority + '</span>' +
            Issue.getPriorityStr(val) + ' (' + val + '%)';
    };

    this.getMembers = function () {
        var str = '';
        if (this.members)
            for (var i = 0; i < this.members.length; i++) {
                var member = this.members[i];
                if (i > 0) str += ', ';
                str += this.members[i].linkedName;
                if (member.sp)
                    str += " (" + member.sp + " SP)";
            }
        return str;
    };

    this.getMemberIds = function () {
        var str = '';
        if (this.members)
            for (var i = 0; i < this.members.length; i++) {
                if (i > 0) str += ', ';
                str += this.members[i].userId;
            }
        return str;
    };

    this.getTesters = function () {
        var str = '';
        if (this.testers)
            for (var i = 0; i < this.testers.length; i++) {
                if (i > 0) str += ', ';
                str += this.testers[i].linkedName;
            }
        return str;
    };

    this.getTesterIds = function () {
        var str = '';
        if (this.testers)
            for (var i = 0; i < this.testers.length; i++) {
                if (i > 0) str += ', ';
                str += this.testers[i].userId;
            }
        return str;
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
        var date = new Date((value + 3600) * 1000);
        // TODO разобраться что за хрень - почему на час разница?

        //return this._num2Str( date.getDate() ) + '-' + this._num2Str( date.getMonth() + 1 ) + '-' + date.getFullYear() + 
        //' ' + date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds() + ':' + date.getMilliseconds();

        return this._num2Str(date.getDate()) + '-' + this._num2Str(date.getMonth() + 1) + '-' + date.getFullYear();
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
 * @param {Number} priority = 1..100
 */
Issue.getPriorityStr = function (priority) {
    if (priority < 33) return 'низкий';
    else if (priority < 66) return 'нормальный';
    else return 'высокий';
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
