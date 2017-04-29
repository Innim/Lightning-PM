if ('undefined' == typeof RegExp.escapeStr) {
    /**
     * 
     * @param {String} str
     * @return String
     */
    RegExp.escapeStr = function(str) {
        /*
        ( ) — круглые скобки;
        [ ] — квадратные скобки;
        \ — обраный слеш;
        . — точка;
        ^ — степень;
        $ — знак доллара;
        | — вертикальная черта;
        ? — вопросительный знак;
        + — плюс.*/

        return str.replace( /([\(\)\[\]\\\.\^\$\|\?\+]{1})/g, "\\$1" );
    };
};

if ('undefined' == typeof RegExp.createFromStr) {
    /**
     * 
     * @param {String} str
     * @return RegExp
     */
    RegExp.createFromStr = function(str, keys) {
        return new RegExp( RegExp.escapeStr( str ), keys );
    };
};
if ('undefined' == typeof Element.prototype.show) {
   Element.prototype.show = function() {
       this.style.display = '';
   };
};

if ('undefined' == typeof Element.prototype.hide) {
    Element.prototype.hide = function() {
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
function BaseService( service )
{
    this._service = service;
     
     /**
      * Вызов метода
      * @param {String} method вызываемый метод
      * @param {Array} params массив передаваемых параметров
      * @param {Function} onResult функция-обработчик ответа
      */
     this.call = function ( method, params, onResult ) 
     {         
         params.unshift( this._service, method, function( obj ){ 
                                             if (obj.errno == F2PInvoker.ERRNO_AUTH_BLOCKED) {
                                                 window.location.reload();
                                             } else {                                                                                                  
                                                try {
                                                 onResult( obj ); 
                                                } catch (e) {
                                                  srv.err({error:'Ошибка при обработке ответа'});
                                                }
                                             }
                                           } ); 
         srv.f2p.request.apply( null, params );         
     };
     
     this._ = function (name) {
         var func = arguments.callee.caller;
         //name = defaultValue( name, func.caller.name );    
         var args = [];
         for (var i = 0; i < func.arguments.length; i++) {
             args.push( func.arguments[i] );
         }

         var onResult = args.pop();
         
         this.call.apply( this, [name, args, onResult] );
     };
};

var srv = {
    f2p   : new ru.vbinc.net.F2PInvoker( window.lpmOptions.url + 'lpm-libs/flash2php/gateway.php' ),   
    issue : {
        s        : new BaseService( 'IssueService' ),
        complete : function ( issueId, onResult ) {
            this.s._( 'complete' );
        },
        restore  : function ( issueId, onResult ) {
            this.s._( 'restore' );
        },
        verify  : function ( issueId, onResult ) {
            this.s._( 'verify' );
        },
        load     : function ( issueId, onResult ) {
            this.s._( 'load' );
        },
        remove   : function ( issueId, onResult ) {
            this.s._( 'remove' );
        },
        comment  : function ( issueId, text, onResult ) {
            this.s._( 'comment' );
        },
        changeScrumState : function (issueId, state, onResult) {
            this.s._('changeScrumState');
        },
        putStickerOnBoard : function (issueId, onResult) {
            this.s._('putStickerOnBoard');
        },
        takeIssue : function (issueId, onResult) {
            this.s._('takeIssue');
        },
    },
    workStudy : {
        s         : new BaseService( 'WorkStudyService' ),
        addWorker : function ( userId, hours, comingTime, onResult ) {
            this.s._( 'addWorker' );
        }
    },
    project : {
        s          : new BaseService( 'ProjectService' ),
        addMembers : function ( projectId, userIds, onResult ) {
            this.s._( 'addMembers' );
        },
        getSumOpenedIssuesHours : function ( projectId, onResult ) {
            this.s._( 'getSumOpenedIssuesHours' );
        },
    },
    projects : {
        s          : new BaseService( 'ProjectsService' ),
        setIsArchive : function ( $projectId , $value , onResult) {
            this.s._( 'setIsArchive' );
        }
    },
    profile : {
        s          : new BaseService( 'ProfileService' ),
        emailPref : function (addIssue, editIssue, issueState, issueComment, onResult ) {
            this.s._( 'emailPref' );
        },
        newPass : function (curentPass, newPass, onResult){
            this.s._('newPass');
        }
    },
    users :{
        s   : new BaseService ('UsersService'),
        lockUser : function (userId, isLock){
            this.s._('lockUser');
        },
    },

    err : function (res) {
        alert( ( typeof res.error != 'undefined' ) ? res.error : 'Ошибка при запросе к серверу' );
    }
};

var states = {
    _list    : [],
    current  : null,
    // Будет показывать заданный элемент при включении указанного стейта
    // стейт может содержать параметры, при регистрации вместо каждого параметра надо указывать #
    // сами параметры должны быть перечислены через : (двоеточие)
    addState : function (element, state, showHandler) {
        var params = 0;
        if (typeof state == 'undefined' || state == '') state = '';
        else
        {
          var arr = state.split(':');
          params = arr.length - 1;
          state = '#' + arr[0];
        }
        
        for (var i = 0; i < this._list.length; i++) {
            if (this._list[i].st == state) return;
        }
        this._list.push( {el : element, st : state, sh : showHandler, p:params } );
    },
    updateView : function () {
        var item;
        this.current = null;
        var hash = window.location.hash;
        var hashArr = hash.split(':');
        var p = hashArr.length - 1;
        hash = hashArr.shift();
        for (var i = 0; i < this._list.length; i++) {
            item = this._list[i];
            if (hash === item.st && item.p === p)
            {
              this.activateState(item, p > 0 ? hashArr : null);
              break;
            }
        }

        if (!this.current)
        {
          if (this._list.length > 0) this.activateState(this._list[0]);
          else this.deactivateAll();
        }
    },
    deactivateAll : function ()
    {
        for (var i = 0, len = this._list.length; i < len; i++) 
        {
            var item = this._list[i];
            if (item.el) item.el.hide();
            $('.info-message', item.el).hide();
            //$( '.info-message', item.el ).hide();
        }
    },
    activateState : function (item, params)
    {
      try {
        this.deactivateAll();

        if (item.sh) item.sh.apply(item.sh, params);
        if (item.el) item.el.show();
        this.current = item;
      } catch (e) {
          // do something
      }
    }
};

var messages = { 
  _ito  : -1,
  /*error : function (text) {
      
  },*/
  info : function (text, _container) {
      if (!_container) 
          _container = $( '.info-message', states.current ? states.current.el : null );
      if (_container) {
          _container.html( text );
          _container.fadeIn( 'normal' );
          if (messages._ito != -1) {
              clearTimeout( messages._ito );
              messages._ito = -1;
          }
          messages._ito = setTimeout( function () {
              _container.fadeOut( 'slow' );
          }, 3000 );
      }
  }
};

var preloader = {
  _showed : 0,
  show : function () {
      this._showed++;
      if (this._showed == 1) {
          $( 'preloader' ).show();
      }
  },
  hide : function () {
      if (this._showed == 0) return;
      this._showed--;
      if (this._showed == 0) {
          $( 'preloader' ).hide();
      }
  }
};

var imgUpload = {
  onSelect : function (event, maxPhotos) {
    var input = event.currentTarget;
    var parent = input.parentNode.parentNode;
    if (typeof maxPhotos !== 'undefined' && maxPhotos <= parent.children.length) return;
    
    var inputs = parent.getElementsByTagName('input');
    for (var i = inputs.length - 1; i >= 0; i--) {
      if (input.type === 'file' && !inputs[i].value) return;
    }

    parent.appendChild( input.parentNode.cloneNode(true) );
  }
};

var lpInfo = {
        userId : 0
};

function User( obj ) {
    this._obj = obj;
    
    this.userId       = obj.userId;
    this.firstName    = obj.firstName;
    this.lastName     = obj.lastName;
    this.nick         = obj.nick;
    //this.        = obj.;
    
    this.getLinkedName = function() {
        return this.getName();
    };
    
    this.getName = function () {
        return this.firstName + ' ' + 
               ( this.nick != '' ? this.nick + ' ' : '' ) + 
               this.lastName;
    };
};

/*
function checkState( element, state ) {
    if (state == '' && window.location.hash == '' || window.location.hash == '#' + state) element.show();
    else element.hide();
};*/

window.onload = function () {
    var canvas = document.createElement( 'canvas' );
    if (!canvas || navigator.userAgent.match(/MSIE/i)) {
        $( '#content' ).hide();
        $( 'body > nav' ).hide();
        $( '#noway' ).show();
    }

    // galery
    L.path = window.lpmOptions.themeUrl + 'imgs/'; 
    L.create();
};

$(document).ready(
   function ()
   {
       $( "input.date" ).datepicker({ 
           dateFormat     : 'dd/mm/yy',
           dayNames       : ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 
                             'Четверг', 'Пятница', 'Суббота'],
           dayNamesMin    : ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
           dayNamesShort  : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
           currentText    : 'Сегодня',
           weekHeader     : 'Нед',
           prevText       : 'Предыдущий',
           nextText       : 'Следующий',
           monthNames     : ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 
                             'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
           monthNamesShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 
                             'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
           firstDay       : 1,
           closeText      : 'Готово'
       });

       if (hljs) hljs.initHighlightingOnLoad();
       
       
       window.lpInfo.userId = $( '#curUserId' ).val();
   }
);
