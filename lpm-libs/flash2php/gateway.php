<?php
/**
 * Скрипт, принимающий данные и возвращающий ответ 
 * Этому скрипту должны быть переданы следующие параметры:
 * <code>service</code> - имя сервиса, включая пакет 
 * (пакет представляется собой путь до файла сервиса от директории сервсивов,
 * где каждая поддиректория отделена от следующей точкой: example.package.TestService)<br/>
 * <code>method</code> - вызываемый метод<br/>
 * <code>params</code> - массив аргументов, которые будут переданы методу (необязательный параметр)<br/>
 * Параметры передаются методом POST или GET.
 * Допускается также использовать сокращённые имена параметров, при включении соответствующей опции - 
 * см. <code>F2P_USE_SHORT_NAMES</code> в config.inc.php<br/>
 * Все данные принимаются и возвращаются в формате JSON.
 */

// определяем корневой путь до Flash2PHP
define( 'F2P_ROOT', dirname( __FILE__ ) . '/' );

// подключаем конфиги
require_once( F2P_ROOT . 'config.inc.php' );
// подключаем основной класс
require_once( F2P_ROOT . 'core/Flash2PHP.php' );
// подключаеми нтерфейс сервиса
//require_once( F2P_ROOT . 'core/IF2PService.php' );

// TODO проверку на то что загружен класс
$service = new Flash2PHP();

// удаляем слюши если надо
if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
      foreach ($val as $k => $v) {
          unset($process[$key][$k]);
          if (is_array($v)) {
              $process[$key][stripslashes($k)] = $v;
              $process[] = &$process[$key][stripslashes($k)];
          } else {
              $process[$key][stripslashes($k)] = stripslashes($v);
          }
       }
    }
    unset($process);
}

try {
	if( count( $_POST ) == 0 ) $_POST = $_GET;
	$service->init( $_POST );
	unset( $_POST );
	$service->execute();
} catch( F2PException $e ) {
     $service->exception( $e );
} catch( Exception $e ) {
	$service->simpleException( $e );
}

?>