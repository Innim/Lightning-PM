<?php
class ProjectsPage extends BasePage
{
	const UID = 'projects';
	const PUID_DEVL = 'develop';
	const PUID_ARCH = 'projects-archive';
	const PUID_USER_ISSUES = 'user-issues';

	// Количество важных задач, открытых для меня по всем проектам
	private $_myIssuesCount = -1;

	function  __construct()
	{
		parent::__construct( self::UID, 'Проекты', true , false, 'projects', 'Проекты' );
		$this->_pattern = 'projects';
		
		array_push( $this->_js,'projects' );

		$this->_defaultPUID = self::PUID_DEVL;

		$this->addSubPage( self::PUID_DEVL , 'В разработке' );
		$this->addSubPage( self::PUID_ARCH , 'Архив' , 'projects-archive');
		$this->addSubPage( self::PUID_USER_ISSUES , 'Мои задачи' , 'user-issues', array( 'issues' ));
	}
	
	public function init() {
		if (!parent::init()) return false;
		
		$engine = LightningEngine::getInstance();
		// проверяем, не пришли ли данные формы
		if (count( $_POST ) > 0) {
			foreach ($_POST as $key => $value) {
				$_POST[$key] = trim( $value );
			}

			// добавление нового проекта
			if (empty( $_POST['name'] ) || empty( $_POST['uid'] ) || empty( $_POST['desc'] ))  {
				$engine->addError( 'Заполнены не все поля' );
			} elseif (!Validation::checkStr( $_POST['uid'], 255, 1, false, false, true )) {
				$engine->addError( 'Введён недопустимый идентификатор - используйте латинские буквы, цифры и тире' );
			} else {			
				$_POST['name'] = mb_substr( $_POST['name'], 0,   255 );
				$_POST['desc'] = mb_substr( $_POST['desc'], 0, 65535 );
					
				foreach ($_POST as $key => $value) {
					$_POST[$key] = $this->_db->escape_string( $value );
				}
					
				// пытаемся записать в базу
				$sql = "insert into `%s` ( `uid`, `name`, `desc`, `date` ) " .
									 "values ( '" . strtolower( $_POST['uid'] ) . "', '" . $_POST['name'] . "', '" . $_POST['desc'] . "', '" . DateTimeUtils::mysqlDate() . "' )";
				if (!$this->_db->queryt( $sql, LPMTables::PROJECTS )) {
					if ($this->_db->errno == 1062) {
						$engine->addError( 'Проект с таким идентификатором уже создан' );
					} else {
						$engine->addError( 'Ошибка записи в базу' );
					}
				} else {
					// переход на страницу проекта					
					LightningEngine::go2URL( $this->getUrl() );
				}
			}
		}
		return $this;
	}

	public function getLabel()
	{
	    $label = parent::getLabel();

	    if ($this->_myIssuesCount === -1)
	    {
	    	$userId = LightningEngine::getInstance()->getUserId();
	    	$this->_myIssuesCount = Issue::getCountImportantIssues($userId);
	    }

	    if ($this->_myIssuesCount > 0) $label .= ' (' . $this->_myIssuesCount . ')';

	    return $label;
	}
}