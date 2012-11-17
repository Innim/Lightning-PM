<?php
class IssuePage extends BasePage
{
	const UID = 'issue';
	
	/**
	 * 
	 * @var Project
	 */
	private $_project;
	
	function __construct()
	{
		parent::__construct( self::UID, '', true, true );
		
		array_push( $this->_js, 'project', 'issues' );
		$this->_pattern = 'project';
	}
	
	/*public function init() {
		if (!parent::init()) return false;
		
		$engine = LightningEngine::getInstance();
		// загружаем проект, на странице которого находимся
		if ($engine->getParams()->suid == '' || !$this->_project = Project::load( $engine->getParams()->suid )) return false;		
		
		Project::$currentProject = $this->_project;
		
		$this->_header = 'Проект &quot;' . $this->_project->name . '&quot;';
		$this->_title  = $this->_project->name;		
		
		// проверяем, не добавили ли задачу
		if (count( $_POST ) > 0) {					
			if (empty( $_POST['name'] ) || !isset( $_POST['members'] ) || !isset( $_POST['type'] ) || empty( $_POST['completeDate'] ))  {
				$engine->addError( 'Заполнены не все обязательные поля' );
			} elseif (preg_match( "/^([0-9]){4}\-([0-9]){2}\-([0-9]){2}$/", $_POST['completeDate'] ) == 0) {
				$engine->addError( 'Недопустимый формат даты. Требуется формат ГГГГ-ММ-ДД' );
			} elseif ($_POST['type'] != Issue::TYPE_BUG && $_POST['type'] != Issue::TYPE_DEVELOP) {
				$engine->addError( 'Недопустимый тип' );
			} elseif (!is_array( $_POST['members'] ) || count( $_POST['members'] ) == 0 ) {
				$engine->addError( 'Необходимо указать хотя бы одного исполнителя проекта' );
			} else {
				foreach ($_POST as $key => $value) {
					if ($key != 'members')
						$_POST[$key] = $this->_db->escape_string( $value );
				}
				$_POST['type'] = (int)$_POST['type'];
				
				// сохраняем задачу
				$sql = "insert into `%s` ( `projectId`, `name`, `desc`, `type`, `authorId`, `createDate`, `completeDate` ) " .
				           		 "values ( '" . $this->_project->id . "', '" . $_POST['name'] . "', '" . $_POST['desc'] . "', " .
				           		 		  "'" . $_POST['type'] . "', '" . $engine->getAuth()->getUserId() . "', " .
				           		 		  "'" . DateTimeUtils::mysqlDate() . "', '" . $_POST['completeDate'] . " 00:00:00' )";			
				
				if (!$this->_db->queryt( $sql, LPMTables::ISSUES )) {
					$engine->addError( 'Ошибка записи в базу' );
				} else {				
					$issueId = $this->_db->insert_id;
					// сохраняем исполнителей задачи
					$sql = "insert into `%s` ( `userId`, `instanceType`, `instanceId` ) " .
									 "values ( ?, '" . Issue::ITYPE_ISSUE . "', '" . $issueId . "' )";
					
					if (!$prepare = $this->_db->preparet( $sql, LPMTables::MEMBERS )) {
						$this->_db->queryt( "delete from `%s` where `id` = '" . $issueId . "'", LPMTables::ISSUES );
						$engine->addError( 'Ошибка при сохранении участников' );
					} else {
						$saved = array();
						foreach ($_POST['members'] as $memberId) {
							$memberId = (int)$memberId;
							if (!in_array( $memberId, $saved )) {
								$prepare->bind_param( 'd', $memberId );
								$prepare->execute();
								array_push( $saved, $memberId );
							}					
						}
						$prepare->close();
						LightningEngine::go2URL( $this->_project->getUrl() );
					}
				}
			}
		}
		
		// загружаем задачи
		$issues = Issue::getListByProject( $this->_project->id );
		
		return true;
	}*/
}
?>