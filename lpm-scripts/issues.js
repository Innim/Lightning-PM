$(document).ready(
    function ()
    {
        //$( '#issueView .comments form.add-comment' ).hide();
        issuePage.updatePriorityVals();
        var dd = new DropDown($('#dropdown'));
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
    $( ".project-stat .issues-total" ).text( $( "#issuesList > tbody > tr" ).size() );
    $( ".project-stat .issues-opened" ).text( $( "#issuesList > tbody > tr.active-issue" ).size() );
};

issuePage.validateIssueForm = function () {
    var errors = [];
    
    /*if ((/^([a-z0-9\-]){1,255}$/i).test( ('input[name=uid]', "#addProjectForm" ).val() )) {
        errors.push( 'Введён недопустимый идентификатор - используйте латинские буквы, цифры и тире' );
    }*/
    if ($('#issueForm #issueMembers input[type=hidden][name=members\[\]]').size() == 0)
        errors.push( 'Задаче должен быть назначен хотя бы один исполнитель' );
    
    $('#issueForm > div.validateError' ).html( errors.join( '<br/>' ) );
    
    if (errors.length == 0) {
        $('#issueForm > div.validateError' ).hide();
        return true;
    } else {
        $('#issueForm > div.validateError' ).show();
        return false;
    }
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
                        $( "#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])" ).
                        addClass   ( 'completed-issue'     ).
                        removeClass( 'active-issue'        ).
                        appendTo   ( '#issuesList > tbody' );
                                            
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
                    $( "#issuesList > tbody > tr:has( td > input[name=issueId][value=" + issueId + "])" ).
                    addClass   ( 'active-issue'        ).
                    removeClass( 'completed-issue'     ).
                    prependTo  ( '#issuesList > tbody' );
                    
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
    $( "#issueForm form textarea[name=desc]" ).val( $( "#issueInfo li.desc .value" ).text() );
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
    if (l >= window.lpmOptions.issueImgsCount)
        $("#issueForm form .images-list > li input[type=file]").hide();
    // родитель
    $( "#issueForm form input[name=parentId]" ).val( $( "#issueInfo input[name=parentId]" ).val() );
    // идентификатор задачи
    $( "#issueForm form input[name=issueId]" ).val( $( "#issueInfo input[name=issueId]" ).val() );
    // действие меняем на редактирование
    $( "#issueForm form input[name=actionType]" ).val( 'editIssue' );
    // меняем заголовок кнопки сохранения    
    $( "#issueForm form .save-line button[type=submit]" ).text( "Сохранить" );
    
};

function removeImage(imageId)
{
    if (confirm('Вы действительно хотите удалить это изображение?'))
    {
        $('#issueForm form .images-list > li').has('input[name=imgId][value=' + imageId + ']').remove();
        var val = $('#issueForm form input[name=removedImages]').val();
        if (val != '') val += ',';
        val += imageId;
        $('#issueForm form input[name=removedImages]').val(val);
    } 
    
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
    
    $( "#issueInfo .buttons-bar"   ).
    removeClass( 'active-issue'    ).
    removeClass( 'completed-issue' );
    
    if (issue.isCompleted()) {
        //$( "#issueInfo .buttons-bar > button.restore-btn" ).show();
        $( "#issueInfo .buttons-bar" ).addClass( 'completed-issue' );
    }
    else if (issue.isOpened()) {
        //$( "#issueInfo .buttons-bar > button.complete-btn" ).show();
        $( "#issueInfo .buttons-bar" ).addClass( 'active-issue' );
    } 
    
    var values = [
        issue.getStatus(),
        issue.getType(),
        issue.getPriority(),
        issue.getCreateDate(),
        issue.getCompleteDate(),
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
                        '<p class="date">' + res.comment.dateLabel + '</p>' +
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

issuePage.showIssues4Me = function (e) {
    issuePage.filterByMemberId( lpInfo.userId );
    e.currentTarget.innerText = 'Показать все';
    e.currentTarget.onclick=issuePage.resetFilter;//"issuePage.resetFilter(event); return false;";
    return false;
};

issuePage.filterByMemberId = function (userId) {
    /*$( '#issuesList > tbody > tr' ).each(
        function (index) {
            var fields = $( "td > input[name=memberId][type=hidden]", this ); 
            for (var i = 0; i < fields.size(); i++) {
                if (fields.get( i ).value == userId) {
                    $(this).show();
                    return;
                }
            }
            $(this).hide();
        }
    );*/
    var list = document.getElementById('issuesList'); 
    var rows = list.tBodies[0].children;
    var row,fields = null;
    var hide = true;
    for (var i =0; i < rows.length; i++) {
        row = rows[i];
        hide = true;
        fields = row.getElementsByTagName('input');
        for (var j = 0; j < fields.length; j++) {
           if (fields[j].name === 'memberId' && fields[j].value == userId) {
                hide = false;
                break;
           }
        }
        if (hide) row.hide();
        else row.show();
    }
};

issuePage.resetFilter = function (e) {
    //$( '#issuesList > tbody > tr' ).show();
    var rows = document.getElementById('issuesList').tBodies[0].children;
    for (var i =0; i < rows.length; i++) {
        rows[i].show();
    }
    e.currentTarget.onclick =issuePage.showIssues4Me;//= "issuePage.showIssues4Me(event); return false;";
    e.currentTarget.innerText = 'Показать только мои задачи';
    return false;
};

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
            case 1  : return 'Ожидает';
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