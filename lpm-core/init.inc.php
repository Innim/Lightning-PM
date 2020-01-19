<?php
require_once(__DIR__ . '/../lpm-config.inc.php');
require_once(__DIR__ . '/version.inc.php'      );
require_once(__DIR__ . '/consts.inc.php'       );
require_once(__DIR__ . '/aliases.inc.php'      );

date_default_timezone_set('Etc/GMT-3'); 

// подключаем фреймворк
require_once(ROOT . LIBS_DIR . 'gm-framework-v1.1.1.phar');

// Подключаем фреймворк
// if (!class_exists( 'GMFramework', false ))
require_once(ROOT . LIBS_DIR . 'framework/GMFramework.class.php');

/**
 * Функция инициализации сервера
 */
function init() {
	// подключаем фреймворк	
	GMFramework::useFramework();
	// инициализируем логи
	if (Globals::isDebugMode())
        GMLog::getInstance()->init(LOGS_PATH);
	// инициализация времени
	DateTimeUtils::setTimeAdjust(TIMEADJUST * 3600);

    // Будем потихоньку выпиливать старый фреймворк, 
    // так что подключаем новую версию вместе со старой
    GMFramework\GMFramework::useFramework();
    // инициализируем логи
    //GMFramework\Log::getInstance()->init(LOGS_PATH);
    // инициализация времени
    GMFramework\DateTimeUtils::setTimeAdjust(TIMEADJUST * 3600);
	
	// автозагрузка
    $importer = GMFramework\ImportClasses::createInstance(ROOT . CORE_DIR, '', false);
    $importer->enableUseAutoSearch(ROOT . CORE_DIR . 'classes.dump');
    $importer->import('PHPExcel', ROOT . LIBS_DIR . 'PHPExcel.php');

    // импортируем дополнительные библиотеки
    $importer->import('upload', ROOT . LIBS_DIR . 'class.upload.php');
    
    GMFramework::addAutoload('ImportClasses::load');

    require_once ROOT . LIBS_DIR . '/vendor/autoload.php';
}

init();