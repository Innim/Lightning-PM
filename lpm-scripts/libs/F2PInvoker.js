if (!ru) var ru = {};
if (!('vbinc' in ru)) ru.vbinc = {};
if (!('net' in ru.vbinc)) ru.vbinc.net = {}; 

/**
 * Создаёт новый класс для посылки запросов к F2P серверу
 * и обработки ответов от него.
 * Можно делать сколько угодно запросов подряд, но при этом
 * они всё равно буде выполнены по очереди
 * @class 
 * @param {String} gateway адрес сервиса для запросов
 * @param {String} [defaultPackage=''] адрес сервиса для запросов
 * @param {Boolean} [useShort=false] использовать которкие имена или нет
 */
var F2PInvoker = ru.vbinc.net.F2PInvoker = function( gateway, defaultPackage, useShort )
{    
    /**
     * Код ошибки неудачного запроса  
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_REQUEST_FAILED = 1000001;
    /**
     * Код ошибки превышения таймаута ожидания
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_REQUEST_TIMEOUT = 1000002;
    /**
     * Код ошибки при парсинге результата
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_PARSE_RESULT = 1000003;
    /**
     * Неизвестный сервис
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_UNKNOWN_SERVICE      = 19001;
    /**
     * Неизвестный метод
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_UNKNOWN_METHOD       = 19002; 
    /**
     * Неверные аргументы
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_WRONG_PARAMS         = 19003;
    /**
     * Неверное количество аргументов
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_WRONG_NUM_ARGS       = 19004;   
    /**
     * Вызов заблокирован функцией beforeFilter
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_AUTH_BLOCKED         = 19005; 
    /**
     * Ошибка при вызове метода
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_CALL_METHOD_ERROR    = 19006;   
    /**
     * Ошибка при исполнении метода
     * @constant
     * @type Number 
     */
    F2PInvoker.ERRNO_EXECUTE_METHOD_ERROR = 19007; 
    
    /**
     * Время ожидания ответа с сервера
     * в секундах
     * @type Number
     */
    var timeout = 30;
    
    /**
     * Адрес сервера
     * @private
     * @type String
     */
    var host = '';    
    /**
     * Package сервисов
     * @private
     * @type String
     */
    var servicePackage = '';
    /**
     * Использовать короткие имена или нет
     * @private
     * @type Boolean
     */
    var useShortNames = false;
    /**
     * Объект для запросов
     * @private
     * @type XMLHttpRequest
     */
    var xhr  = new XMLHttpRequest();
    /**
     * Идентификатор таймера ожидания ответа с сервера
     * @private
     * @type Number
     */
    var timeoutId = -1;
    /**
     * Массив объектов для запросов
     * @private
     * @type Array
     */
    var queue = [];
    /**
     * В данное время отправлен запрос на сервер
     * @private
     * @type Boolean
     */
    var busy = false;
    
    var aborted = false;
    
    var _serviceParam = '';
    var _methodParam = '';
    var _paramsParam = '';

    // конструктор
    (function() 
     {
        if (!JSON) throw 'Для корректной работы необходима библиотека JSON (http://www.json.org/js.html)';
        
        host = gateway;
        servicePackage = defaultPackage || '';
        useShortNames  = useShortNames  || false;
        
        if (useShortNames) {
            _serviceParam = 's';
            _methodParam  = 'm';
            _paramsParam  = 'p';
        } else {
            _serviceParam = 'service';
            _methodParam  = 'method';
            _paramsParam  = 'params';
        }
                              
        //xhr.open( 'POST', host, true );
        xhr.onreadystatechange = function(){ 
            // readyState может принимать следующие значения
            // 0 - Unitialized
            // 1 - Loading
            // 2 - Loaded
            // 3 - Interactive
            // 4 - Complete
            if (xhr.readyState == 4) onRequestComplete();
        };

        //xhr.setRequestHeader( 'Content-Type', /*'text/plain' );//*/'application/x-www-form-urlencoded' );
     })();     
    
    /**
     * Отсылает запрос на сервер
     * @param {String} service Имя сервиса
     * @param {String} method Название метода на сервере
     * @param onResult функция - обработчик ответа с сервера. 
     * Если с сервера приходит ответ - в обработчик передаётся распарсенный объект,
     * если ошибка запроса - передаётся объект с номером полями error и errno
     * (для номера ошибки используются 
     * <tt>F2PInvoket.ERRNO_REQUEST_FAILED</tt>, 
     * <tt>F2PInvoket.ERRNO_REQUEST_TIMEOUT</tt>, 
     * <tt>F2PInvoket.ERRNO_PARSE_RESULT</tt>)
     * @param _param любое число параметров для передачи на сервер
     */
    this.request = function( service, method, onResult, _param ) {
        var params = [];
        for(var i=3; i<arguments.length; i++) {
            params.push( arguments[i] );
        }
        
        if (servicePackage != '') service = servicePackage + '.' + service;
        // добавляем запрос в очередь        
        queue.push( {
            s : service,
            m : method, 
            h : onResult,
            p : params    
        } ); 
         
        // если не заняты - отправляем сразу
        sendRequest();
    };
    
    /**
     * Устанавливает значение таймуата ожидания ответа от сервера
     * @param {Number} value время в секундах
     */
    this.setTimeout = function( value ) {
        timeout = value;
    };
    
    var sendRequest = function() {
        // если в очереди есть элементы 
        // - выполняем следующий запрос
        if (!busy && queue.length > 0) {
            reqData = queue[0];
            busy = true;
            
            // формируем параметры запроса
            var params = ''; 
            
            for (var i=0; i < reqData.p.length; i++) {
                if (params != '') params += ',';
                params += JSON.stringify( reqData.p[i] );
            };
            
            params = _serviceParam + '=' + reqData.s +
               '&' + _methodParam + '=' + reqData.m + 
                    ( params != '' ? '&' + _paramsParam + '=[' + encodeURIComponent( params ) + ']' : '' );
                                    
            timeoutId = setTimeout( onTimeout, timeout * 1000 );
            
            xhr.open( 'POST', host, true );
            xhr.setRequestHeader( 'Content-Type', /*'text/plain' );//*/'application/x-www-form-urlencoded' );
            xhr.send( params );                        
        }
    };
    
    var onRequestComplete = function() {       
        // если статус ответа 200 - значит пришёл корректный ответ
        if (xhr.status == 200) {                       
            try {
                var result = JSON.parse( xhr.responseText );                        
            } catch (e) {
               // ошибка парсинга данных
               onError( F2PInvoker.ERRNO_PARSE_RESULT );
               return;
            }
            // вызов обработчкиа 
            callHandler( result );
            requestComplete();
        } else if (aborted) {
          aborted = false;  
        } else {
            onError( F2PInvoker.ERRNO_REQUEST_FAILED );
        }

    };
    
    var onError = function( errno ) {
        callHandler( { errno : errno } ); 
        requestComplete();
    };
    
    var callHandler = function( obj ) {
       // try {
            queue[0].h( obj );
            return true;
       /* } catch (e) {

            return false;
        }*/
    };
    
    function onTimeout( errno ) {
        aborted = true;
        xhr.abort();
        onError( F2PInvoker.ERRNO_REQUEST_TIMEOUT );
    };
    
    var requestComplete = function() {
        // убираем первый элемент из списка, 
        // т.к. считаем что этот запрос выполнен
        queue.shift();
        
        busy = false;
        clearTimeout( timeoutId );
        timeoutId = -1;
        
        // выполняем следующий запрос
        sendRequest();
    };
};
