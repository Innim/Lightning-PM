$(document).ready(
    function () {
        //$( '#issueView .comments form.add-comment' ).hide();
        issuePage.updatePriorityVals();
        issuePage.scumColUpdateInfo();
        var dd = new DropDown($('#dropdown'));
        document.addEventListener('paste', pasteClipboardImage);

        $('#issuesList .member-list a').click(function (e) {
            issuePage.showIssuesByUser($(e.currentTarget).data('memberId'));
        });

        $('#issueForm .note.tags-line a.tag').click(function (e) {
            var a = $(e.currentTarget);
            insertMarker(a.data('marker'));
        });

        $('.delete-comment').live('click', function() {
            let id = $(this).attr('data-comment-id');
            let el = $(this);
            let result = confirm('Удалить комментарий?');
            if (result) {
                issuePage.deleteComment(id, function(res) {
                    if (res) {
                        el.parent('div.comments-list-item').remove();
                        el = null;
                    }
                });
            }
        });

        if (!$('#is-admin').val()) {
            $('.delete-comment').each(function (index) {
                let elementId = $(this).attr('id');
                let startTime = $(this).attr('data-time');
                hideElementAfterDelay(elementId, startTime);
            });
        }

        $('div.tooltip').hover(
            function() {
                $(this).find('div').clearQueue().show();
            },
            function() {
                $(this).find('div')
                    .animate({width: 'width' + 20, height: 'height' + 20}, 150)
                    .animate({width: 'hide', height: 'hide'}, 1);
            }
        )

        bindFormattingHotkeys('#issueForm form textarea[name=desc]');
        bindFormattingHotkeys('form.add-comment textarea[name=commentText]');
    }
);

function bindFormattingHotkeys(selector) {
    $(selector).keypress(function(e) {
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

var issuePage = {};

issuePage.onShowAddIssue = function () {
    var selectedPerformer = $('#selected-performer').val();
    if (selectedPerformer) {
        issuePage.addIssueMember();
    }
}

issuePage.addIssueLabel = function() {
    $("#addIssueLabelFormContainer").dialog({
        resizable: false,
        width: 400,
        modal: true,
        draggable: false,
        title: "Добавление новой метки",
        buttons: [{
            text: "Сохранить",
            click: function (e) {
                var label = $("#issueLabelText").val();
                var checked = $("#isAllProjectsCheckbox").is(':checked');
                var projectId = $("#issueProjectID").val();
                if (label.length > 0) {
                    preloader.show();
                    srv.issue.addLabel(label, checked, projectId, function (res) {
                        preloader.hide();
                        if (res.success) {
                            issuePage.clearLabel(label);
                            issuePage.createLabel(label, (checked ? 0 : projectId), res.id);
                            issuePage.addLabelToName(label);
                        } else {
                            srv.err( res );
                        }
                    });
                }
                $("#addIssueLabelForm")[0].reset();
                $("#addIssueLabelFormContainer").dialog('close');
            }
        }, {
            text: "Отмена",
            click: function (e) {
                $("#addIssueLabelForm")[0].reset();
                $("#addIssueLabelFormContainer").dialog('close');
            }
        }],
        open: function() {
            $("#addIssueLabelFormContainer").keypress(function(e) {
                if (e.keyCode == $.ui.keyCode.ENTER) {
                    $(this).parent().find("button:eq(0)").trigger("click");
                    return false;
                }
            });
        }
    });
}

issuePage.removeIssueLabels = function() {
    $("#removeIssuesLabelContainer").dialog({
        resizable: false,
        width: 'auto',
        modal: true,
        draggable: false,
        title: "Удаление меток"
    });
}

issuePage.removeIssueLabel = function(name, id) {
    if (typeof issueLabels === 'undefined')
        issueLabels = [];

    var success = false;


    if (id == undefined) {
        issuePage.clearLabel(name);
    } else {
        preloader.show();
        srv.issue.removeLabel(id, $("#issueProjectID").val(), function (res) {
            preloader.hide();
            if (res.success) {
                issuePage.clearLabel(name);
            } else {
                srv.err( res );
            }
        });
    }
}

issuePage.createLabel = function (label, id, projectId) {
    $(".add-issue-label").before(
        "<a href=\"javascript:void(0)\" class=\"issue-label\" onclick=\"issuePage.addLabelToName('"
        + label + "');\">" + label + "</a>");

    $("#removeIssuesLabelContainer .table").append("<div class=\"table-row\">" +
        "<div class=\"table-cell label-name\">" + label + "</div>" +
        "<div class=\"table-cell\">0</div>" +
        "<div class=\"table-cell\">" + (projectId == 0 ? "<i class=\"far fa-check-square\" aria-hidden=\"true\"></i>" : "") + "</div>" +
        "<div class=\"table-cell\">" +
        "<a href=\"javascript:void(0)\" onclick=\"issuePage.removeIssueLabel('" + label + (id != 0 ? "', " + id : "") + ");\">" +
        "<i class=\"far fa-minus-square\" aria-hidden=\"true\"></i>" +
        "</a>" +
        "</div>" +
        "</div>");
}

issuePage.clearLabel = function (labelName) {

    if (issueLabels.indexOf(labelName) != -1)
        issuePage.addLabelToName(labelName);

    $("#removeIssuesLabelContainer .table-row").each(function () {
        var item = $.trim($(this).find(".label-name").text());
        id = $(this).find(".label-name").data("labelid");
        if (item == labelName) {
            $(this).remove();
        }
    });

    $(".issue-labels-container a.issue-label").each(function () {
        var item = $(this).text();
        if (item == labelName) {
            $(this).remove();
        }
    });
}

issuePage.addLabelToName = function(labelName) {
    if (typeof issueLabels === 'undefined')
        issueLabels = [];
    var index = issueLabels.indexOf(labelName);
    var isAddingLabel = index == -1;
    var strPos = 0;
    var resultLabels = "";
    for (var i = 0, len = issueLabels.length; i < len; ++i)
    {
        var str = issueLabels[i];
        strPos += str.length + 2;
        if (index == i) { // на случай, если несколько одинаковых меток у задачи, ну мало ли кто накосячил.
            issueLabels.splice(index, 1);
            len--;
            i--;
            index = issueLabels.indexOf(labelName);
        } else {
            resultLabels += "[" + str + "]";
        }
    }

    if (isAddingLabel) {
        resultLabels += "[" + labelName + "]";
        issueLabels.push(labelName);
    }

    var name = $( "#issueForm form input[name=name]" ).val();
    name = (resultLabels.length > 0 ? resultLabels + " " : "") + $.trim(name.substr(strPos));

    $( "#issueForm form input[name=name]" ).val(name);
    issuePage.updateLabelsView();
}

issuePage.updateLabelsView = function () {
    if (typeof issueLabels !== 'undefined') {
        var subclass = 'selected';
        $(".issue-labels-container a.issue-label").each(function () {
            if ($(this).hasClass(subclass))
                $(this).removeClass(subclass);

            var item = $(this).text();
            if (issueLabels.indexOf(item) != -1)
                $(this).addClass(subclass);
        });
    }
}

issuePage.issueNameChanged = function (value) {
    if (typeof issueLabels === 'undefined')
        issueLabels = [];

    var labelsStr = $.trim(value).match(/^\[.*]/);
    if (labelsStr != null) {
        // т.к. на js нет нормальной регулярки для такой задачи, то как-то так
        var labels = labelsStr.toString().split("]");
        var isUpdate = false;
        var currentSymbol = 0;
        for (var i = 0, len = labels.length; i < len; ++i) {
            var label = $.trim(labels[i]);
            if (label.substr(0, 1) == '[') {
                currentSymbol += 2;
                label = $.trim(label.substr(1));
                if (label == "") {
                    labels.splice(i--, 1);
                    len--;
                } else {
                    labels[i] = label;
                    if (issueLabels.indexOf(label) == -1) {
                        issueLabels.push(label);
                        isUpdate = true;
                    }
                }
            } else {
                break;
            }
        }

        //Удаляем те, которые стерли
        var len = issueLabels.length;
        while (len-- > 0) {
            var label = issueLabels[len];
            if (labels.indexOf(label) == -1) {
                issueLabels.splice(len, 1);
                isUpdate = true;
            }
        }

        if (isUpdate)
            issuePage.updateLabelsView();
    }
}

issuePage.addIssueMember = function(sp) {
    /**
     * @type HTMLSelectElement
     */
    var selectElement = document.getElementById('addIssueMembers');
    var index = selectElement.selectedIndex;
    if (index == 0)
        return;

    var scrum = $('#issueForm').data('projectScrum') == 1;
    var option = selectElement.options[index];
    var $memberLi = $('<li>');


    $memberLi.
        append($('<span class="user-name">').html(option.innerHTML)).
        append($('<input type="hidden" name="members[]">').val(option.value));

    // проверка что это скрам проект
    if (scrum)
        $memberLi.
            append($('<input type="text" name="membersSp[]" class="member-sp">').val(sp > 0 ? sp : "")).
            append($('<span class="member-sp-label">').html("SP"));


    $memberLi.
        append($('<a class="remove-btn">').click(issuePage.removeIssueMember));
    
    $('#issueMembers').append($memberLi);
    
    selectElement.removeChild(option);
    selectElement.selectedIndex = 0;
};

issuePage.removeIssueMember = function(e) {
    var li           = e.currentTarget.parentNode;
    
    var userId       = $('input[type=hidden][type=members\[\]]', li).attr('value');
    var userName     = $('span.user-name', li).html();
    
    var option       = document.createElement('option');
    option.value     = userId;
    option.innerHTML = userName;
    
    if (li.parentNode) li.parentNode.removeChild(li);
    
    var selectElement = document.getElementById('addIssueMembers');
    for (var i = 1; i < selectElement.options.length; i++) {
        if (userName < selectElement.options[i].innerHTML) break;
    }
    selectElement.appendChild(option, i);
};

issuePage.addIssueTester = function() {
    /**
     * @type HTMLSelectElement
     */
    var selectElement = document.getElementById( 'addIssueTesters' );

    var option = selectElement.options[selectElement.selectedIndex];

    /**
     * @type HTMLOListElement
     */

    var testers = document.getElementById( 'issueTesters' );

    /**
     * @type HTMLOListElement
     */
    var li = document.createElement( 'li' );

    /**
     * @type HTMLSpanElement
     */
    var nameLabel = document.createElement( 'span' );
    nameLabel.innerHTML = option.innerHTML;
    nameLabel.className = 'user-name';

    /**
     * @type HTMLLinkElement
     */
    var idField = document.createElement( 'input' );
    idField.type  = 'hidden';
    idField.name  = 'testers[]';
    idField.value = option.value;

    /**
     * @type HTMLButtonElement
     */
    var removeBtn = document.createElement( 'a' );
    //removeBtn.innerHTML = 'Удалить';
    removeBtn.className = 'remove-btn';
    removeBtn.onclick   = issuePage.removeIssueTester;

    li.appendChild( nameLabel );
    li.appendChild( idField   );
    li.appendChild( removeBtn );

    testers.appendChild( li );

    selectElement.removeChild( option );
    selectElement.selectedIndex = 0;
};

issuePage.removeIssueTester = function(e) {
    var li           = e.currentTarget.parentNode;

    var userId       = $( 'input[type=hidden][type=testers\[\]]', li ).attr( 'value' );
    var userName     = $( 'span.user-name', li ).html();

    var option       = document.createElement( 'option' );
    option.value     = userId;
    option.innerHTML = userName;

    if (li.parentNode)
        li.parentNode.removeChild( li );

    var selectElement = document.getElementById( 'addIssueTesters' );
    for (var i = 1; i < selectElement.options.length; i++) {
        if (userName < selectElement.options[i].innerHTML) break;
    }
    selectElement.appendChild( option, i );
};

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
    var valStr = Issue.getPriorityStr( value );
    $('#priority').val(value);
    value++;
    $( '#priorityVal' ).html( valStr + ' (' + value + '%)' );
    $( '#priorityVal' ).css( 'backgroundColor', issuePage.getPriorityColor( value - 1 ) );
};

issuePage.upPriorityVal = function() {
    var value = $('#priority').val();
    if (value<99)
    {
        value++;
        issuePage.setPriorityVal(value);
    };
}

issuePage.downPriorityVal = function() {
    var value = $('#priority').val();
    if (value>0)
    {
        value--;
        issuePage.setPriorityVal(value);
    };
}

issuePage.getPriorityColor = function (val) {
    var v = Math.floor( val % 25 / 25 * 255 );
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
    $( ".project-stat .issues-opened" ).text( $( "#issuesList > tbody > tr.active-issue,tr.verify-issue" ).size());
    $( ".project-stat .issues-completed" ).text( $( "#issuesList > tbody > tr.completed-issue" ).size() );

    // Перезапрашиваем сумму часов
    srv.project.getSumOpenedIssuesHours($("#projectView").data('projectId'), function (r) {
        if (r.success)
        {
            if (r.count > 0)
            {
                $(".project-stat .project-opened-issue-hours").show();
                $(".project-stat .issue-hours.value").text(r.count);
                // TODO склонения лейбла?
            }
            else 
            {
                $(".project-stat .project-opened-issue-hours").hide();
            }
        }
    });
};

issuePage.validateIssueForm = function () {
    var errors = [];
    var inputs =  $( "#issueForm input:file" );
    var len = 0;
    
    if (!$.isEmptyObject({inputs})){
        inputs.each(function( i ) {
            len += inputs[i].files.length;
        });
    }

    if (len > window.lpmOptions.issueImgsCount)
        errors.push('Вы не можете прикрепить больше ' + window.lpmOptions.issueImgsCount + ' изображений' );

    if ($('#issueForm #issueMembers input[type=hidden][name=members\[\]]').size() == 0)
        errors.push( 'Задаче должен быть назначен хотя бы один исполнитель' );
    
      if (errors.length == 0 ) {
        $('#issueForm > div.validateError' ).hide();
        return true;
    } else {
        $('#issueForm > div.validateError' ).html( errors.join( '<br/>' ) ).show();
        return false;
    }
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
    if (!$.isEmptyObject({text})) { 
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
    var parent   = e.currentTarget.parentElement;
    var issueId  = $('input[name=issueId]', parent).attr('value');
    
    if (issueId > 0) {
        preloader.show();
        srv.issue.complete( 
            issueId, 
            function (res) {
                //btn.disabled = false;
                preloader.hide();
                if (res.success) {
                    //var row = cell.parentElement;
                    //row.parentElement.appendChild( row );
                    //row.className = 'completed-issue';
                    // находим в таблице строку с этой задачей и переставляем
                    //var row =                     
                    if ($( '#issuesList' ).length > 0) {
                        $( "#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])" ).remove();                   
                        showMain();
                    } else if ($( '#issueView' ).length > 0) {
                        /*$( "#issueInfo .buttons-bar" ).
                        addClass   ( 'completed-issue'     ).
                        removeClass( 'active-issue'        );*/
                        setIssueInfo( new Issue( res.issue ) );
                    }
                    issuePage.updateStat();
                    //cell.removeChild( btn );
                    //btn.innerText = 'Открыть';
                    //btn.onclick   = restoreIssue;
                } else {
                    srv.err( res );
                }
            }  
        );
    }
}

issuePage.changePriority = function (e) {
    var $control = $(e.currentTarget);
    var $row = $control.parents('tr');
    var issueId = $('input[name=issueId]', $row).attr('value');
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
                    appendTo($('body')).offset({top:hintY, left:e.pageX - 10}).
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

                        if (compare($next) < 0)
                        {
                            $last = $next;
                        }
                        else 
                        {
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
                        if (compare($prev) > 0)
                        {
                            $first = $prev;
                        }
                        else 
                        {
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

function restoreIssue( e ) {
    var parent   = e.currentTarget.parentElement;
    //var issueId = $( 'input[type=hidden][name=issueId]', cell ).attr( 'value' );
    //var btn     = $( 'button', cell );
   // btn.attr( 'disabled', 'disabled' );
    
    var issueId  = $( 'input[name=issueId]', parent ).attr( 'value' );
    preloader.show();
    
    srv.issue.restore( 
        issueId, 
        function (res) {
            //btn.removeAttr( 'disabled' );
            preloader.hide();
            if (res.success) {
                //var row    = cell.parentElement;
                //var parent = row.parentElement;
                /*var tmpRow;
                for (var i = 0; i < parent.numChilder; i++) {
                    tmpRow = parent.getChildAt( i );
                    if (tmpRow.className == 'completed-issue' || )
                }*/
                //parent.insertBefore( row, parent.rows[0] );
                //row.className = 'active-issue';                
                
                if ($( '#issuesList' ).length > 0) {
                    $( "#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])" ).remove();
                    //addClass   ( 'active-issue'        ).
                    //removeClass( 'completed-issue'     ).
                    //prependTo  ( '#issuesList > tbody' );
                    //$( "#completedIssuess #issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])" ).hide();
                    showMain();
                } else if ($( '#issueView' ).length > 0) {
                    /*$( "#issueInfo .buttons-bar" ).
                    addClass   ( 'active-issue'        ).
                    removeClass( 'completed-issue'     );*/
                    setIssueInfo( new Issue( res.issue ) );
                }
                issuePage.updateStat();
                                                    
                //btn.attr( 'onclick', completeIssue ); 
                //btn.text( 'Завершить' );
                //btn.click( completeIssue );
            } else {
                srv.err( res );
            }
        }
    );
};

function verifyIssue( e ) {
    var parent   = e.currentTarget.parentElement;
    
    var issueId  = $( 'input[name=issueId]', parent ).attr( 'value' );
    preloader.show();
    
    srv.issue.verify( 
        issueId, 
        function (res) {
            preloader.hide();
            if (res.success) {        
                    if ($( '#issueView' ).length > 0) {
                    setIssueInfo( new Issue( res.issue ) );
                }
                issuePage.updateStat();
            } else {
                srv.err( res );
            }
        }
    );
};

issuePage.removeIssue = function( e ) {    
    if (confirm( 'Вы действительно хотите удалить эту задачу?' )) {    
        var btn     = e.currentTarget;
        var issueId = $( 'input[type=hidden][name=issueId]', btn.parentElement ).attr( 'value' );
        
        preloader.show();
        
        srv.issue.remove( 
            issueId, 
            function (res) {
                preloader.hide();
                if (res.success) {
                    //window.location.hash = '';
                    window.location.href = $( "#issueView a.back-link" ).attr( 'href' );
                    //window.location.reload();
                } else {
                    srv.err( res );
                }
            }
        );
    }
};

issuePage.changeScrumState = function (e) {
    var $control = $(e.currentTarget);
    var $sticker = $control.parents('.scrum-board-sticker');
    var issueId = $sticker.data('issueId');
    var curState = $sticker.data('stickerState');

    // Определяем следующий стейт
    var state;
    if ($control.hasClass('sticker-control-done'))
        state = 4;
    else if ($control.hasClass('sticker-control-prev'))
        state = curState - 1;
    else if ($control.hasClass('sticker-control-next'))
        state = curState + 1;
    else if ($control.hasClass('sticker-control-archive'))
        state = 5;
    else if ($control.hasClass('sticker-control-remove'))
        state = 0;
    else 
        return;

    preloader.show();
    srv.issue.changeScrumState(issueId, state, function (res) {
        preloader.hide();
        if (res.success) {
            $sticker.attr('data-sticker-state', state);
            // Перевешиваем стикер
            $sticker.remove();
            var colName;
            switch (state) {
                case 1 : colName = 'todo'; break;
                case 2 : colName = 'in_progress'; break;
                case 3 : colName = 'testing'; break;
                case 4 : colName = 'done'; break;
            }

            if (colName) {
                $('.scrum-board-col.col-' + colName).append($sticker);
            }
            issuePage.scumColUpdateInfo();
        }
    });
};

issuePage.putStickerOnBoard = function (issueId) {
    preloader.show();
    srv.issue.putStickerOnBoard(issueId, function (res) {
        preloader.hide();
        if (res.success) {
            $('#issueInfo h3 .scrum-put-sticker').remove();
            $('#putToBoardField').attr('checked', true);
            issuePage.scumColUpdateInfo();
        }
    });
};

issuePage.takeIssue = function (e) {
    var $control = $(e.currentTarget);
    var $sticker = $control.parents('.scrum-board-sticker');
    var issueId = $sticker.data('issueId');
    preloader.show();
    srv.issue.takeIssue(issueId, function (res) {
        preloader.hide();
        if (res.success) {
            $sticker.addClass('mine');
            issuePage.scumColUpdateInfo();
        }
    });
};

function showIssue (issueId) {
    srv.issue.load( 
        issueId, 
        function (res) {
            if (res.success) {
                window.location.hash = 'issue-view';
               // if (window.location.search == '') window.location.search += '?';
                //else window.location.search += '&';
                //window.location.search += 'iid=' + issueId;
                states.updateView();
                setIssueInfo( new Issue( res.issue ) );
            } else {
                srv.err( res );
            }
        } 
    );
};

issuePage.showAddForm = function ( type, parentId ) {
    //$("#issueForm").show();
    //$("#projectView").hide();
    window.location.hash = 'add-issue';
    states.updateView();
    
    if (typeof type != 'undefined') {
        //$('#issueForm > form > ')
        $('form input:radio[name=type]:checked', "#issueForm").attr( 'checked', 'checked' );
        $('form input:radio[value=1]', "#issueForm" ).attr( 'checked', 'checked' ); 
    } else {
        $('form input:radio[name=type]:checked', "#issueForm").attr( 'checked', 'checked' );
        $('form input:radio[value=0]', "#issueForm" ).attr( 'checked', 'checked' ); 
    }    
};

issuePage.showEditForm = function () {
    // переключаем вид
    window.location.hash = 'edit';
    states.updateView();
};

issuePage.setEditInfo = function () {
    // заполняем всю информацию
    //$( "" ).value( $( "" ) );
    // меняем заголовок
    $( "#issueForm > h3" ).text( "Редактирование задачи" );
    // имя
    var issueName = $( "#issueInfo > h3 > .issue-name" ).text();
    $( "#issueForm form input[name=name]" ).val(issueName);
    // внешний вид меток
    issuePage.updateLabelsView();
    // часы
    $( "#issueForm form input[name=hours]" ).val( $( "#issueInfo > h3 .issue-hours" ).text() );

    // тип
    $('form input:radio[name=type]:checked', "#issueForm").removeAttr( 'checked' );
    $('form input:radio[name=type][value=' + $( "#issueInfo div input[name=type]" ).val() + ']',
       "#issueForm" ).attr( 'checked', 'checked' ); 
    // приоритет
    var priorityVal = $( "#issueInfo div input[name=priority]" ).val();
    $( "#issueForm form input[name=priority]" ).val( priorityVal );
    issuePage.setPriorityVal( priorityVal );
    // дата окончания
    $( "#issueForm form input[name=completeDate]" ).val( 
        $( "#issueInfo div input[name=completeDate]" ).val()
    );
    // исполнители
    var memberIds = $("#issueInfo div input[name=members]").val().split(',');
    var membersSp = $("#issueInfo div input[name=membersSp]").val().split(',');
    var i, l = 0;
    l = memberIds.length;
    for (i = 0; i < l; i++) {
        $( "#addIssueMembers option[value=" + memberIds[i] + "]" ).attr( 'selected', 'selected' );
        issuePage.addIssueMember(membersSp[i]);
    }

    // Тестеры
    var testerIds = $( "#issueInfo div input[name=testers]" ).val() .split( ',' );
    l = testerIds.length;
    for (i = 0; i < l; i++) {
        var testerId = testerIds[i];
        if (testerId.length > 0) {
            $("#addIssueTesters option[value=" + testerId + "]").attr('selected', 'selected');
            issuePage.addIssueTester();
        }
    }

    //$( "#issueForm form" ).value( $( "" ) );
    // описание
    // пришлось убрать, потому что там уже обработанное описание - с ссылками и тп
    // вообще видимо надо переделать это все
    //$( "#issueForm form textarea[name=desc]" ).val( $( "#issueInfo li.desc .value" ).html() );
    // изображения
    var imgs = $("#issueInfo div > .images-line > li");
    l = imgs.length;
    var $imgInputField = $('#issueForm form .images-list > li').has('input[name="images[]"]');
    var $imgInput = $('#issueForm form .images-list').empty();
    var imgLI = null;
    for (i = l - 1; i >= 0; i--) {
        //$('input[name=imgId]',imgs[i]).val() 
        imgLI = imgs[i].cloneNode( true );
        $(imgLI).append('<a href="javascript:;" class="remove-btn" onclick="removeImage(' + 
            $('input[name=imgId]', imgLI).val() + ')"></a>');
        $imgInput.append(imgLI);
        //imgInput.insertBefore(imgLI, imgInput.children[0]);
    };
    $imgInput.append($imgInputField);
    if (l >= window.lpmOptions.issueImgsCount) {
        $("#issueForm form .images-list > li input[type=file]").hide();
        $("#issueForm form li a[name=imgbyUrl]").hide();
    }
    
    // родитель
    $( "#issueForm form input[name=parentId]" ).val( $( "#issueInfo input[name=parentId]" ).val() );
    // идентификатор задачи
    $( "#issueForm form input[name=issueId]" ).val( $( "#issueInfo input[name=issueId]" ).val() );
    // действие меняем на редактирование
    $( "#issueForm form input[name=actionType]" ).val( 'editIssue' );
    // меняем заголовок кнопки сохранения    
    $( "#issueForm form .save-line button[type=submit]" ).text( "Сохранить" );
    
};

issuePage.setIssueBy = function (value) {
    // заполняем всю информацию
    //$( "" ).value( $( "" ) );
    // меняем заголовок
    $("#issueForm > h3").text( "Добавить задачу" );
    // имя
    $("#issueForm form input[name=name]").val(value.name);
    // часы
    $("#issueForm form input[name=hours]").val(value.hours);

    // тип
    $('form input:radio[name=type]:checked', "#issueForm").removeAttr('checked');
    $('form input:radio[name=type][value=' + /*$( "#issueInfo li input[name=type]" ).val()*/ value.type + ']',
        "#issueForm").attr('checked', 'checked');
    // приоритет
    // var priorityVal = $( "#issueInfo li input[name=priority]" ).val();
    $("#issueForm form input[name=priority]").val(value.priority);
    issuePage.setPriorityVal(value.priority);
    // дата окончания
    $("#issueForm form input[name=completeDate]").val(value.completeDate);
    // исполнители
    var memberIds = value.members.split(',');
    var i, l = 0;
    l = memberIds.length;
    for (i = 0; i < l; i++) {
        $("#addIssueMembers option[value=" + memberIds[i] + "]").attr('selected', 'selected');
        issuePage.addIssueMember();
    }

    // Тестеры
    var testerIds = value.testers/*$( "#issueInfo li input[name=testers]" ).val()*/ .split( ',' );
    l = testerIds.length;
    for (i = 0; i < l; i++) {
        var testerId = testerIds[i];
        if (testerId.length > 0) {
            $("#addIssueTesters option[value=" + testerId + "]").attr('selected', 'selected');
            issuePage.addIssueTester();
        }
    }

    //$( "#issueForm form" ).value( $( "" ) );
    // описание
    // пришлось убрать, потому что там уже обработанное описание - с ссылками и тп
    // вообще видимо надо переделать это все
    //$( "#issueForm form textarea[name=desc]" ).val( $( "#issueInfo li.desc .value" ).html() );
    $( "#issueForm form textarea[name=desc]" ).val( value.desc );
    // изображения
    var imgs = value.images;
    var numImages = imgs.length;
    for (i = 0; i < numImages; ++i){
        addImagebyUrl(imgs[i].source);
    }
    /*var imgs = $("#issueInfo li > .images-line > li");
    l = imgs.length;
    var $imgInputField = $('#issueForm form .images-list > li').has('input[name="images[]"]');
    var $imgInput = $('#issueForm form .images-list').empty();
    var imgLI = null;
    for (i = l - 1; i >= 0; i--) {
        //$('input[name=imgId]',imgs[i]).val()
        imgLI = imgs[i].cloneNode( true );
        $(imgLI).append('<a href="javascript:;" class="remove-btn" onclick="removeImage(' +
            $('input[name=imgId]', imgLI).val() + ')"></a>');
        $imgInput.append(imgLI);
        //imgInput.insertBefore(imgLI, imgInput.children[0]);
    };
    $imgInput.append($imgInputField);
    if (l >= window.lpmOptions.issueImgsCount) {
        $("#issueForm form .images-list > li input[type=file]").hide();
        $("#issueForm form li a[name=imgbyUrl]").hide();
    }*/

    // родитель
    $( "#issueForm form input[name=parentId]" ).val( value.parentId /*$( "#issueInfo input[name=parentId]" ).val()*/ );
    // идентификатор задачи
    // $( "#issueForm form input[name=issueId]" ).val( value.issueId/*$( "#issueInfo input[name=issueId]" ).val()*/ );
    // действие меняем на редактирование
    $( "#issueForm form input[name=actionType]" ).val( 'addIssue' );
    $( "#issueForm form input[name=baseIdInProject]" ).val(value.baseIdInProject);
    // меняем заголовок кнопки сохранения
    $( "#issueForm form .save-line button[type=submit]" ).text( "Сохранить" );

    // выставляем галочку "Поместить на Scrum доску"
    var boardField = $("#putToBoardField");
    if (boardField && boardField[0])
        boardField[0].checked = value.isOnBoard;
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

function addImagebyUrl(imageUrl) {
    // $("#issueForm li > ul.images-url > li input").removeAttr('autofocus');
    var urlLI = $("#issueForm li > ul.images-url > li.imgUrlTempl").clone().show();
    var imgInput = $("#issueForm ul.images-url");
    urlLI.removeAttr('class');
    if (imageUrl)
        urlLI[0].children[0].value = imageUrl;
    //urlLI.("input").attr('autofocus','true');
    //добавляем в контейнер
    imgInput.append(urlLI);
    // setCaretPosition(urlLI.find("input"));
    urlLI.find("a").click(function (event) {
        urlLI.remove();    
    });
};

/**
 * 
 * @param {Issue} issue
 */
function setIssueInfo( issue ) {        
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
    
    $("#issueInfo > p > input[name=issueId]").attr('value', issue.id);
};

issuePage.showCommentForm = function () {
    $('#issueView .comments form.add-comment').show();
    $('#issueView .comments .links-bar a').hide();
    $('#issueView .comments form.add-comment textarea[name=commentText]' ).focus();
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
            $( '#issueView .comments .comments-list .comments-list-item' ).size() + ')');
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
    var issueId = $( '#issueView .comments form.add-comment input[name=issueId]'        ).val();
    
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
    let userId = $('#user-id-hidden').val();
    let elementId = 'comment_' + comment.id;
    let commentTime = comment.date;
    $('#issueView .comments form.add-comment textarea[name=commentText]').val('');
    $('#issueView .comments .comments-list').prepend(
           '<div class="comments-list-item">' +
                '<p class="delete-comment" id="' + elementId + '" data-comment-id="' + comment.id + '" data-user-id="'+ userId +'"' +
        '               data-time="'+ commentTime +'">Удалить</p>' +
            '<img src="' + comment.author.avatarUrl + '" class="user-avatar small"/>' +
            '<p class="author">' + comment.author.linkedName + '</p> ' +
            '<p class="date"><a class="anchor" id="'+comment.id+
            '"href="#comment-'+comment.id+'">'+comment.dateLabel+'</a></p>' +
            '<article class="text formatted-desc">' + comment.text + '</p>' +
           '</div>' 
    );
    issuePage.hideCommentForm();
    $( '#issueView .comments .links-bar a.toggle-comments' ).show();
    
    if (!$( '#issueView .comments .comments-list' ).is(':visible')) 
        issuePage.toogleCommentForm();

    hideElementAfterDelay(elementId, commentTime);
};

issuePage.showIssues4Me = function () {
    window.location.hash = 'only-my';
    issuePage.filterByMemberId( lpInfo.userId );

    $('#showIssues4MeLink').hide();
    $('#showIssues4AllLink').show();
    return false;
};

issuePage.showLastCreated = function () {
    window.location.hash = 'last-created';
    var table = $('#issuesList');
    window.defaultIssues = table.html();
    table.find('tr:not(:first)').sort(function(a, b) {
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
    issuePage.filterByMemberId( memberId );
    $('#showIssues4MeLink').hide();
    $('#showIssues4AllLink').show();
    return false;
};

issuePage.filterByMemberId = function (userId) {
    var list = document.getElementById('issuesList'); 
    var rows = list.tBodies[0].children;
    var row,fields = null;
    var hide = true;
    
    for (var i = 0; i < rows.length; i++) {
        row = rows[i];
        hide = true;
        
        if (!row.classList.contains('verify-issue')) {
            fields_members = row.children[3].getElementsByTagName('a');        
            for (var j = 0; j < fields_members.length; j++) {
               if (fields_members[j].getAttribute('data-member-id') == userId) {
                  hide = false;   
                  break;  
               }
            }
        }

        if (hide) row.hide();
        else row.show();
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

issuePage.clearBoard = function () {
    if (confirm('Убрать все стикеры с доски?')) {
        var projectId = $('#scrumBoard').data('projectId');
        preloader.show();
        srv.issue.removeStickersFromBoard(projectId, function (res) {
            preloader.hide();
            if (res.success) {
                $('#scrumBoard .scrum-board-table .scrum-board-sticker').remove();
                issuePage.scumColUpdateInfo();
            } else {
                srv.err(res);
            }
        });
    }
}

issuePage.changeSPVisibility = function (value) {
    if (value)
        $('#scrumBoard').removeClass('hide-sp');
    else 
        $('#scrumBoard').addClass('hide-sp');
}

issuePage.addIssueBy = function (issueIdInProject) {
    issueIdInProject = parseInt(issueIdInProject);
    var projectId = parseInt($('#issueProjectID').val());

    if (issueIdInProject <= 0 || projectId <= 0)
        return;

    // показываем прелоадер
    preloader.show();

    // Пробуем загрузить данные задачи
    srv.issue.loadByIdInProject(
        issueIdInProject,
        projectId,
        function (res) {
            // скрываем прелоадер
            preloader.hide();

            if (res.success) {
                var issue = new Issue( res.issue );
                // console.log("issue-name: " + issue.name);

                issuePage.setIssueBy({
                    name: issue.name,
                    hours: issue.hours,
                    desc: issue.desc,
                    priority : issue.priority,
                    completeDate : issue.getCompleteDateInput(),
                    type : issue.type,
                    members : issue.getMemberIds(),
                    testers : issue.getTesterIds(),
                    parentId : issue.parentId,
                    issueId : issue.id,
                    images : issue.images,
                    isOnBoard : issue.isOnBoard,
                    baseIdInProject : 0
                });

            } else {
                srv.err( res );
            }
        }
    );
}

issuePage.finishedIssueBy = function (issueIdInProject) {
    issueIdInProject = parseInt(issueIdInProject);
    var projectId = parseInt($('#issueProjectID').val());

    if (issueIdInProject <= 0 || projectId <= 0)
        return;

    // показываем прелоадер
    preloader.show();

    // Пробуем загрузить данные задачи
    srv.issue.loadByIdInProject(
        issueIdInProject,
        projectId,
        function (res) {
            // скрываем прелоадер
            preloader.hide();

            // Если создаётся задача по доделкам
            if (res.success) {
                const issue = new Issue( res.issue );
                // var url = $("#projectView").data('projectUrl');

                issuePage.setIssueBy({
                    name: Issue.getCompletionName(issue.name),
                    hours: issue.hours,
                    desc: issue.desc + "\n\n" + "Оригинальная задача: " + issue.url,
                    priority : issue.priority,
                    completeDate : issue.getCompleteDateInput(),
                    type : issue.type,
                    members : issue.getMemberIds(),
                    testers : issue.getTesterIds(),
                    parentId : issue.parentId,
                    issueId : issue.id,
                    images : issue.images,
                    isOnBoard : issue.isOnBoard,
                    baseIdInProject : issueIdInProject
                });
            } else {
                srv.err( res );
            }
        }
    );
}

issuePage.showExportXls = function () {
    issuesExport2Excel.openWindow(parseInt($("#projectView").data('projectId')));
}

function Issue( obj ) {
    this._obj = obj;
    
    this.id           = obj.id;
    this.author       = obj.author;
    this.completeDate = obj.completeDate;
    this.completedDate = obj.completedDate;
    this.createDate   = obj.createDate;
    this.desc         = obj.desc;
    this.formattedDesc = obj.formattedDesc;
    this.name         = obj.name;
    this.status       = obj.status;
    this.type         = obj.type;
    this.members      = obj.members;
    this.priority     = obj.priority;
    this.hours        = obj.hours;
    this.testers      = obj.testers;
    this.images       = obj.images;
    this.isOnBoard    = obj.isOnBoard;
    this.url          = obj.url;
    
    this.getCompleteDate = function () {
        return this.getDate( this.completeDate );
    };

    this.getCompleteDateInput = function () {
        var d = this.getCompleteDate();

        if (d)
            d = d.replace(/-/g, '/');

        return d;
    };

    this.getCompletedDate = function () {
        return this.getDate( this.completedDate );
    };     

    this.getCreateDate = function () {
        return this.getDate( this.createDate );
    };
    
    this.getAuthor = function () {
        return this.author ? this.author.linkedName : '';
    };
    
    this.getPriority = function () {
        var val = ( this.priority + 1 );
        return '<span class="priority-val circle">' + this.priority + '</span>' +
               Issue.getPriorityStr( val ) + ' (' + val + '%)';
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
            case 1  : return 'Ожидает проверки';
            case 2  : return 'Завершена';
            default : return 'В работе';
        }
    };
    
    this.getType = function () {
        switch (this.type) {
            case 1  : return 'Ошибка';
            case 2  : return 'Поддержка';
            default : return 'Разработка';
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
        var date = new Date( ( value + 3600 ) * 1000 );
        // TODO разобраться что за хрень - почему на час разница?
        
        //return this._num2Str( date.getDate() ) + '-' + this._num2Str( date.getMonth() + 1 ) + '-' + date.getFullYear() + 
        //' ' + date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds() + ':' + date.getMilliseconds();
        
        return this._num2Str( date.getDate() ) + '-' + this._num2Str( date.getMonth() + 1 ) + '-' + date.getFullYear();
    };
    
    this._num2Str = function (val, dig) {
        if (!dig || dig < 1) dig = 1; 
        else dig -= 1;
        
        var str = '';
        if (val < 0) str += '-';        
        val = Math.abs( val );
        
        var i = dig - Math.floor( Math.log( val ) / Math.log( 10 ) );
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
        `${issueName.substring(0, lastTagIndex+1)} ${prefix} ${issueName.substring(lastTagIndex+1).trim()}`
        : `${prefix} ${issueName.trim()}`;
}

// Всплывающее окно скопировать commit сообщение

jQuery(function($) {

 $('.issues-list > tbody > tr > td:first-of-type a').mouseenter( 
    function() 
    {
        $(this).next('.issue_copy.popup-menu').slideDown(180);
    }
 );

$('.issues-list > tbody > tr > td:first-of-type').mouseleave( 
    function() 
    {
        $('.issue_copy.popup-menu').slideUp(180);
    }
);

 $('.issue_copy.popup-menu').hover(
    function() 
    {
        $(this).show();        
    },
    function() 
    {
        $(this).slideUp(180);
    }
);

});

function pasteClipboardImage( event ){
    var clipboard = event.clipboardData;

    if (clipboard && clipboard.items) {
        // В буфере обмена может быть только один элемент
        var item = clipboard.items[0];

        if (item && item.type.indexOf('image/') > -1) {
            // Получаем картинку в виде блога
            var blob = item.getAsFile();

            if (blob) {
                // Читаем файл и вставляем его в data:uri
                var reader = new FileReader();

                reader.onload = function(event) {
                    var img = new Image( 150 , 100 );
                    img.src = event.target.result;
                    $('input[type=file]').last().parent().before("<li id='current'><a></a></li>");
                    $('li#current a').append(img);
                    $('li#current').append("<a class='remove-btn' onclick='removeClipboardImage()'>");
                    var input = document.createElement( 'input' );
                    input.type  = 'hidden';
                    input.name  = 'clipboardImg[]';
                    input.value = img.src;
                    $('li#current').append(input);
                    $('li#current').removeAttr("id");
                }

                reader.readAsDataURL(blob);
            }
        }
    }   
};

function removeClipboardImage(){
    var elem = event.target.parentNode;
    elem.parentNode.removeChild(elem);
};

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
    let delay = (Number(startTimeInSeconds) + Number(delayTimeInSeconds))  * 1000 - Date.now();

    if (delay >= 0) {
        const timerId = setTimeout(() => {
            $('#' + elementId).remove();
            clearTimeout(timerId);
        }, delay);
    } else {
        $('#' + elementId).remove();
    }
}
