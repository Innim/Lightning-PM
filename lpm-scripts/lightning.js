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
                                                 onResult( obj ); 
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
    f2p   : new ru.vbinc.net.F2PInvoker( 'lpm-flash2php/gateway.php' ),   
    issue : {
        s        : new BaseService( 'IssueService' ),
        complete : function ( issueId, onResult ) {
            this.s._( 'complete' );
        },
        restore  : function ( issueId, onResult ) {
            this.s._( 'restore' );
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
        }
    },
    profile : {
        s          : new BaseService( 'ProfileService' ),
        emailPref : function (addIssue, editIssue, issueState, issueComment, onResult ) {
            this.s._( 'emailPref' );
        }
    },
    err : function (res) {
        alert( ( typeof res.error != 'undefined' ) ? res.error : 'Ошибка при запросе к серверу' );
    }
};

var states = {
    _list    : [],
    current  : null,
    addState : function (element, state, showHandler) {
        if (typeof state == 'undefined' || state == '') state = '';
        else state = '#' + state;
        
        for (var i = 0; i < this._list.length; i++) {
            if (this._list[i].st == state) return;
        }
        this._list.push( {el : element, st : state, sh : showHandler } );
    },
    updateView : function () {
        var item;
        for (var i = 0; i < this._list.length; i++) {
            item = this._list[i];
            try {
                if (window.location.hash == item.st) {
                    if (item.sh) item.sh();
                    item.el.show();
                    current = item;
                }
                else {
                    item.el.hide();
                    $( '.info-message', item.el ).hide();
                    //$( '.info-message', item.el ).hide();
                }
            } catch (e) {
                // do something
            }
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
       
       
       window.lpInfo.userId = $( '#curUserId' ).val();
   }
);
