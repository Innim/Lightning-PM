$(document).ready(
    function ()
    {
        //$( '#issueView .comments form.add-comment' ).hide();
        issuePage.updatePriorityVals();
        issuePage.scumColUpdateSP();
        var dd = new DropDown($('#dropdown'));
        document.addEventListener('paste', pasteClipboardImage);
        
        $('#issuesList .member-list a').click(function (e) {
            issuePage.showIssuesByUser($(e.currentTarget).data('memberId'));
        });

        $('#issueForm .note.tags-line a.tag').click(function (e) {
            var a = e.currentTarget;
            issuePage.insertTag(a.innerText);
        });
    }
);

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
issuePage.addIssueMember = function() {
    /**
     * @type HTMLSelectElement
     */
    var selectElement = document.getElementById( 'addIssueMembers' );
    
    var option = selectElement.options[selectElement.selectedIndex];
    
    /**
     * @type HTMLOListElement
     */
    
    var members = document.getElementById( 'issueMembers' );

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
    idField.name  = 'members[]';
    idField.value = option.value;
    
    /**
     * @type HTMLButtonElement
     */
    var removeBtn = document.createElement( 'a' );
    //removeBtn.innerHTML = 'Удалить';
    removeBtn.className = 'remove-btn';    
    removeBtn.onclick   = issuePage.removeIssueMember;
    
    li.appendChild( nameLabel );
    li.appendChild( idField   );
    li.appendChild( removeBtn );
    
    members.appendChild( li );
    
    selectElement.removeChild( option );
    selectElement.selectedIndex = 0;
    
    //$( '#issueMembers' )
};

issuePage.removeIssueMember = function(e) {
    var li           = e.currentTarget.parentNode;
    
    var userId       = $( 'input[type=hidden][type=members\[\]]', li ).attr( 'value' );
    var userName     = $( 'span.user-name', li ).html();
    
    var option       = document.createElement( 'option' );
    option.value     = userId;
    option.innerHTML = userName;
    
    if (li.parentNode) li.parentNode.removeChild( li );
    
    var selectElement = document.getElementById( 'addIssueMembers' );
    for (var i = 1; i < selectElement.options.length; i++) {
        if (userName < selectElement.options[i].innerHTML) break;
    }
    selectElement.appendChild( option, i );
};

issuePage.updatePriorityVals = function () {
    issuePage.setPriorityVal( $('input[type=range]#priority').val() );
    //issuePage.setPriorityVal( $('input[type=range]#priority').val() );
    $('.priority-val.circle').each( function (i) {
        $(this).css( 
            'backgroundColor', 
            issuePage.getPriorityColor( parseInt( $(this).text() ) ) 
        );
        $(this).text( '' );
    });
};

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

issuePage.insertTag = function(tag){
    var text = document.getElementsByName('desc').item(0);
    var subtext = text.value.substring(text.selectionStart, text.selectionEnd);
    var caretPos = 0;
    const closetag = '</' +tag+ '>';
    //Если в описании задачи есть текст
    if (!$.isEmptyObject({text})) { 
        // берем все, что до выделения
        var desc = text.value.substring(0,text.selectionStart)+
        // вставляем стартовый тег
        '<'+tag+'>'+
        // вставляем выделенный текст
        subtext +
        // вставляем закрывающий тег
        closetag +
        // вставляем все, что после выделения
        text.value.substring(text.selectionEnd,text.value.length);
        //определяем позицию курсора(перед закрывающим тэгом)
        //если есть выделенный текст
        if (subtext == "")
            //определяем фокус перед '/' тэгом
            caretPos = text.selectionStart + closetag.length-1;
        else //после тэга       
            caretPos = text.selectionStart + subtext.length + closetag.length*2;
        //добавляем итог в описание задачи
        $('#issueForm textarea.desc').val(desc);
        //устанавливаем курсор на полученную позицию
        setCaretPosition(text,caretPos);
    }
}
    
function setCaretPosition(elem, pos ) {
    //если есть выделение
    if(elem.selectionStart) {
        //фокусим курсор на нужной позиции   
        elem.setSelectionRange(pos, pos);
        elem.focus();
    }
    //иначе фокусим сам элемент
    else elem.focus();
};

function completeIssue( e ) {    
    var parent   = e.currentTarget.parentElement;
    //var fields   = cell.getElementsByTagName( 'input' );
   // var btn      = cell.getElementsByTagName( 'button' )[0];
    //btn.disabled = "disabled";
    
    var issueId  = $( 'input[name=issueId]', parent ).attr( 'value' );
    preloader.show();
    
    /*for (var i = 0; i < fields.length; i++) {
        if (fields[i].name == 'issueId') {
            issueId = fields[i].value;
            break;
        }
    }*/
    
    if (issueId > 0) {
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
};

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
            issuePage.scumColUpdateSP();
        }
    });
};

issuePage.putStickerOnBoard = function (issueId) {
    preloader.show();
    srv.issue.putStickerOnBoard(issueId, function (res) {
        preloader.hide();
        if (res.success) {
            $('#issueInfo h3 .scrum-put-sticker').remove();
            issuePage.scumColUpdateSP();
        }
    });
}

issuePage.takeIssue = function (e) {
    var $control = $(e.currentTarget);
    var $sticker = $control.parents('.scrum-board-sticker');
    var issueId = $sticker.data('issueId');
    preloader.show();
    srv.issue.takeIssue(issueId, function (res) {
        preloader.hide();
        if (res.success) {
            $sticker.addClass('mine');
            issuePage.scumColUpdateSP();
        }
    });
}

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
    $( "#issueForm form input[name=name]" ).val( $( "#issueInfo > h3 > .issue-name" ).text() );
    // часы
    $( "#issueForm form input[name=hours]" ).val( $( "#issueInfo > h3 .issue-hours" ).text() );

    // тип
    $('form input:radio[name=type]:checked', "#issueForm").removeAttr( 'checked' );
    $('form input:radio[name=type][value=' + $( "#issueInfo li input[name=type]" ).val() + ']', 
       "#issueForm" ).attr( 'checked', 'checked' ); 
    // приоритет
    var priorityVal = $( "#issueInfo li input[name=priority]" ).val();
    $( "#issueForm form input[name=priority]" ).val( priorityVal );
    issuePage.setPriorityVal( priorityVal );
    // дата окончания
    $( "#issueForm form input[name=completeDate]" ).val( 
        $( "#issueInfo li input[name=completeDate]" ).val() 
    );
    // исполнители
    var memberIds = $( "#issueInfo li input[name=members]" ).val() .split( ',' );
    var i,l = 0;
    l = memberIds.length;
    for (i = 0; i < l; i++) {
        $( "#addIssueMembers option[value=" + memberIds[i] + "]" ).attr( 'selected', 'selected' );
        issuePage.addIssueMember();
    }
    //$( "#issueForm form" ).value( $( "" ) );
    // описание
    // пришлось убрать, потому что там уже обработанное описание - с ссылками и тп
    // вообще видимо надо переделать это все
    //$( "#issueForm form textarea[name=desc]" ).val( $( "#issueInfo li.desc .value" ).html() );
    // изображения
    var imgs = $("#issueInfo li > .images-line > li");
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

function removeImage(imageId) {
    if (confirm('Вы действительно хотите удалить это изображение?')) {
        $('#issueForm form .images-list > li').has('input[name=imgId][value=' + imageId + ']').remove();
        var val = $('#issueForm form input[name=removedImages]').val();
        if (val != '') val += ',';
        val += imageId;
        $('#issueForm form input[name=removedImages]').val(val);
    }
}

function addImagebyUrl() {
    // $("#issueForm li > ul.images-url > li input").removeAttr('autofocus');
    var urlLI = $("#issueForm li > ul.images-url > li.imgUrlTempl").clone().show();
    var imgInput = $("#issueForm ul.images-url");
    urlLI.removeAttr('class');
    //urlLI.("input").attr('autofocus','true');
    //добавляем в контейнер
    imgInput.append(urlLI);
    setCaretPosition(urlLI.find("input"));
    urlLI.find("a").click(function (event) {
        urlLI.remove();    
    });
};

/**
 * 
 * @param {Issue} issue
 */
function setIssueInfo( issue ) {        
    $("#issueInfo > h3 .issue-name").text( issue.name );
    var fields = $("#issueInfo > ol > li > .value");
    
    //$( "#issueInfo .buttons-bar > button.restore-btn"  ).hide();
    //$( "#issueInfo .buttons-bar > button.complete-btn" ).hide();
    
    $( "#issueInfo .info-list"   ).
    removeClass( 'active-issue'    ).
    removeClass( 'verify-issue'    ).
    removeClass( 'completed-issue' );


    $( "#issueInfo .buttons-bar"   ).
    removeClass( 'active-issue'    ).
    removeClass( 'verify-issue'    ).
    removeClass( 'completed-issue' );
    
    if (issue.isCompleted()) {
        //$( "#issueInfo .buttons-bar > button.restore-btn" ).show();
        $( "#issueInfo .buttons-bar" ).addClass( 'completed-issue' );
        $( "#issueInfo .info-list" ).addClass( 'completed-issue' );
    }
    else if (issue.isOpened()) {
        //$( "#issueInfo .buttons-bar > button.complete-btn" ).show();
        $( "#issueInfo .buttons-bar" ).addClass( 'active-issue' );
        //$( "#issueInfo .buttons-bar" ).addClass( 'verify-issue' );
        $( "#issueInfo .info-list" ).addClass( 'active-issue' );
    } 

    else if (issue.isVerify()) {
        $( "#issueInfo .buttons-bar" ).addClass( 'verify-issue' );
        $( "#issueInfo .info-list" ).addClass( 'verify-issue' );
    }
    
    var values = [
        issue.getStatus(),
        issue.getType(),
        issue.getPriority(),
        issue.getCreateDate(),
        issue.getCompleteDate(),
        '',//issue.getCompletedDate(), // TODO выставлять настоящее значение
        issue.getAuthor(),
        issue.getMembers(),
        issue.getDesc()
    ];
    
    for (var i = 0; i < values.length; i++) {
        fields[i].innerHTML = values[i];
    }
    
    issuePage.updatePriorityVals();
    
    $("#issueInfo > p > input[name=issueId]").attr( 'value', issue.id );
};

issuePage.showCommentForm = function () {
    $( '#issueView .comments form.add-comment' ).show();
    $( '#issueView .comments .links-bar a'     ).hide();
    $( '#issueView .comments form.add-comment textarea[name=commentText]' ).focus();
};

issuePage.hideCommentForm = function () {
    $( '#issueView .comments form.add-comment' ).hide();
    $( '#issueView .comments .links-bar a'     ).show();
};

issuePage.toogleCommentForm = function () {
    var link = $( '#issueView .comments .links-bar a.toggle-comments' );
    var comments = $( '#issueView .comments .comments-list' );
    if (!comments.is(':visible'))
        link.html( 'Свернуть комментарии' );
    else 
        link.html( 'Показать комментарии (' 
                +  $( '#issueView .comments .comments-list li' ).size() 
                + ')' );
    link.show();
    comments.slideToggle( 'normal' );
};

issuePage.postComment = function () {
    var text    = $( '#issueView .comments form.add-comment textarea[name=commentText]' ).val();
    var issueId = $( '#issueView .comments form.add-comment input[name=issueId]'        ).val();
    
    // TODO проверку на пустоту
    if (issueId > 0 && text != '') { 
     preloader.show();
     srv.issue.comment( 
        issueId, 
        text, 
        function (res) {
            preloader.hide();
            if (res.success) {
                $( '#issueView .comments form.add-comment textarea[name=commentText]' ).val( '' );
                $( '#issueView .comments ol.comments-list' ).prepend( 
                       '<li>' +  
                        '<img src="' + res.comment.author.avatarUrl + '" class="user-avatar small"/>' +
                        '<p class="author">' + res.comment.author.linkedName + '</p> ' +
                        '<p class="date"><a class="anchor" id="'+res.comment.id+
                        '"href="#comment-'+res.comment.id+'">'+res.comment.dateLabel+'</a></p>' +
                        '<p class="text">' + res.comment.text + '</p>' +
                       '</li>' 
                );
                issuePage.hideCommentForm();
                $( '#issueView .comments .links-bar a.toggle-comments' ).show();
                
                if (!$( '#issueView .comments .comments-list' ).is(':visible')) 
                    issuePage.toogleCommentForm();
            } else {
                srv.err( res );
            }
        } 
     );
    }
    return false;
};

issuePage.showIssues4Me = function () {
    window.location.hash = 'only-my';
    issuePage.filterByMemberId( lpInfo.userId );

    $('#showIssues4MeLink').hide();
    $('#showIssues4AllLink').show();
    return false;
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

issuePage.scumColUpdateSP = function () {
    var cols = ['col-todo', 'col-in_progress', 'col-testing', 'col-done'];
    for (var i = 0; i < cols.length; ++i) {
        var col = cols[i];

        var sp = 0;
        $('#scrumBoard .scrub-board-table .scrum-board-col.' + col + ' .scrum-board-sticker').
            each(function (i, el) {
                sp += parseInt($(el).data('stickerSp'));
        });

        var selector = '#scrumBoard .scrub-board-table .scrub-col-sp.' + col;
        if (sp > 0)
            $(selector).show();
        else 
            $(selector).hide();
        $(selector + ' .value').html(sp);
    }
    
}

function Issue( obj ) {
    this._obj = obj;
    
    this.id           = obj.id;
    this.author       = obj.author;
    this.completeDate = obj.completeDate;
    this.createDate   = obj.createDate;
    this.desc         = obj.desc;
    this.name         = obj.name;
    this.status       = obj.status;
    this.type         = obj.type;
    this.members      = obj.members;
    this.priority     = obj.priority;
    this.hours        = obj.hours;
    
    this.getCompleteDate = function () {
        return this.getDate( this.completeDate );
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
            if (i > 0) str += ', ';
            str += this.members[i].linkedName;
        }
        return str;
    };
    
    this.getDesc = function () {
        return this.desc;
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

$('.issues-list > tbody > tr > td:first-of-type a').mouseleave( 
    function()
    {
        $('a.issue-commit-copy-link').zclip(
        {
            path : window.lpmOptions.url+'lpm-scripts/libs/ZeroClipboard.swf',
            copy : function()
            { 
                var a = $(this).parent().prev('a').text();
                var b = $(this).parent().parent().next('td').next('td').children('a').children('.issue-name').text();
                return Issue.getCommitMessage(a, b);
            }
        });
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