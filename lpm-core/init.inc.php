<?
require_once( dirname( __FILE__ ) . '/../lpm-config.inc.php' );
require_once( dirname( __FILE__ ) . '/version.inc.php'        );
require_once( dirname( __FILE__ ) . '/consts.inc.php'        );
require_once( dirname( __FILE__ ) . '/aliases.inc.php'       );

date_default_timezone_set('Etc/GMT-3'); 

// подключаем фреймворк
//require_once( ROOT . FRAMEWORK_DIR . 'GMFramework.class.php' );
require_once( ROOT . LIBS_DIR . 'gm-framework-v1.1.1.phar' );
// require_once(ROOT . LIBS_DIR . 'gm-framework-v1.1.1/GMFramework.class.php');

// Подключаем фреймворк
// if (!class_exists( 'GMFramework', false ))
require_once(ROOT . LIBS_DIR . 'framework/GMFramework.class.php');


/**
 * Функция инициализации сервера
 */
function init()
{	
	// подключаем фреймворк	
	GMFramework::useFramework();
	// инициализируем логи
	if (Globals::isDebugMode()) GMLog::getInstance()->init( LOGS_PATH );
	// инициализация времени
	DateTimeUtils::setTimeAdjust( TIMEADJUST * 3600 );

    // Будем потихоньку выпиливать старый фреймворк, 
    // так что подключаем новую версию вместе со старой
    GMFramework\GMFramework::useFramework();
    // инициализируем логи
    //GMFramework\Log::getInstance()->init(LOGS_PATH);
    // инициализация времени
    GMFramework\DateTimeUtils::setTimeAdjust(TIMEADJUST * 3600);
	
	// автозагрузка
    $importer = GMFramework\ImportClasses::createInstance(ROOT . CORE_DIR, '', false);
	// $importer = ImportClasses::createInstance( ROOT . CORE_DIR, false );
    $importer->enableUseAutoSearch( ROOT . CORE_DIR . 'classes.dump' );
	
	// здесь импортируются только общие классы
    /*$importer->importPackageClasses( 
    	'base/', 
    	'LightningEngine', 'LPMAuth', 'LPMBaseObject', 
    	'LPMBaseService', 'LPMOptions', 'LPMParams', 
    	'PageConstructor', 'PagePrinter' 
    );
    $importer->importPackageClasses( 'comments/'    , 'Comment' );
    $importer->importPackageClasses( 'const/'       , 'LPMBase', 'LPMTables' );
    $importer->importPackageClasses( 'htmlEntities/', 'Link' );
    $importer->importPackageClasses( 'notify/'      , 'EmailNotifier' );
    $importer->importPackageClasses( 
    	'pages/', 
    	'AuthPage', 'BasePage', 'PagesManager', 
    	'RegisterPage', 'ProfilePage', 'ProjectPage', 
    	'ProjectsPage', 'SubPage', 'UsersPage', 'WorkStudyPage' 
    );
    $importer->importPackageClasses( 'project/'     , 'Issue', 'MembersInstance', 'Project' );
    $importer->importPackageClasses( 
    	'services/', 
    	'IssueService', 'ProjectService', 'WorkStudyService' 
    );
    $importer->importPackageClasses( 'user/'        , 'Member', 'User', 'UserPref' );
    $importer->importPackageClasses( 
    	'workStudy/', 
    	'Worker', 'WorkStudy', 'WSDay', 'WSRecord', 'WSUtils' 
    );*/

    // импортируем дополнительные библиотеки
    $importer->import( 'upload', ROOT . LIBS_DIR . 'class.upload.php' );
    
    GMFramework::addAutoload( 'ImportClasses::load' );
	
    // инициализация таблицы опций
    //Options::$tableName = LPMTables::OPTIONS;
}

init();
?>