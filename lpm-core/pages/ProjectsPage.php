<?php
/**
 * Раздел проектов.
 */
class ProjectsPage extends BasePage {
	const UID = 'projects';
	const PUID_DEVL = 'develop';
	const PUID_ARCH = 'projects-archive';
	const PUID_USER_ISSUES = 'user-issues';

	// Количество важных задач, открытых для меня по всем проектам
	private $_myIssuesCount = -1;

	function  __construct() {
		parent::__construct(self::UID, 'Проекты', true , false, 'projects', 'Проекты');
		$this->_pattern = 'projects';
		
		$this->_js[] = 'projects';

		$this->_defaultPUID = self::PUID_DEVL;

		$this->addSubPage(self::PUID_DEVL, 'В разработке');
		$this->addSubPage(self::PUID_ARCH, 'Архив', 'projects-archive');
		$this->addSubPage(self::PUID_USER_ISSUES, 'Мои задачи', 'user-issues', ['issues']);
	}
	
	public function init() {
		if (!parent::init()) return false;
		
		$engine = LightningEngine::getInstance();
		// проверяем, не пришли ли данные формы
		if (count($_POST) > 0) {
			foreach ($_POST as $key => $value) {
				$_POST[$key] = trim($value);
			}

			// добавление нового проекта
			if (empty($_POST['name']) || empty($_POST['uid']) || empty($_POST['desc']))
				return $engine->addError('Заполнены не все поля');

			$uid  = strtolower($_POST['uid']);
			$name = mb_substr($_POST['name'], 0,   255);
			$desc = mb_substr($_POST['desc'], 0, 65535);

			if (!$this->validateProjectUid($uid)) {
				return $engine->addError(
					'Введён недопустимый идентификатор - используйте латинские буквы, цифры и тире');
			}
			
			$hash = [
				'INSERT' => [
					'uid'  => $uid,
					'name' => $name,
					'desc' => $desc,
					'date' => DateTimeUtils::mysqlDate(),
				],
				'INTO'   => LPMTables::PROJECTS
			];
			if (!$this->_db->queryb($hash)) {
				$errmsg = $this->_db->errno == 1062
					? 'Проект с таким идентификатором уже создан'
					: 'Ошибка записи в базу';
				$engine->addError($errmsg);
			} else {
				// переход на страницу проекта					
				LightningEngine::go2URL($this->getUrl());
			}
		}

		return $this;
	}

	public function getLabel() {
	    $label = parent::getLabel();

	    if ($this->_myIssuesCount === -1) {
	    	$userId = LightningEngine::getInstance()->getUserId();
	    	$this->_myIssuesCount = Issue::getCountImportantIssues($userId);
	    }

	    if ($this->_myIssuesCount > 0) $label .= ' (' . $this->_myIssuesCount . ')';

	    return $label;
	}

	private function validateProjectUid($value) {
		return Validation::checkStr($value, 255, 1, false, false, true);
	}
}