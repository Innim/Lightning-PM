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

try {
	// Только POST: методы сервисов меняют состояние, а запрос, который можно
	// выполнить переходом по ссылке или загрузкой картинки, выполняется
	// с чужой страницы от имени залогиненного пользователя.
	if( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' )
		throw new F2PException( 'Request method not allowed', F2PException::ERRNO_WRONG_REQUEST_METHOD );

	$service->init( $_POST );
	unset( $_POST );
	$service->execute();
} catch( F2PException $e ) {
     $service->exception( $e );
} catch( Exception $e ) {
	$service->simpleException( $e );
}

?>