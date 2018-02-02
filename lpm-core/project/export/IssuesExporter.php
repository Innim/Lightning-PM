<?php
/**
 * Класс, позволяющий экспортировать задачи.
 */
abstract class IssuesExporter {
	private $_list;
	private $_filename;
	
	/**
	 * Создает экземпляр класса.
	 * @param array<Issue> $list     Список задач.
	 * @param string|null $filename Имя файла, без расширения.
	 */
	function __construct($list, $filename = null) {
		$this->_list = $list;
		$this->_filename = $filename;
	}

	/**
	 * Выполняет экспорт.
	 * @return string Имя файла, в которой записан результат экспорта.
	 * @throws Exception В случае ошибки при экспорте.
	 */
	public function export() {
		$filepath = $this->getFileRelativePath();
		if ($filepath{0} != '/')
			$filepath = '/' . $filepath;

		$fullFilepath = ROOT . $filepath;
		// Предварительно создаем директорию
		if (!\GMFramework\FileSystemUtils::createPath(dirname($fullFilepath)))
			throw new Exception("Не удалось создать директорию для записи файла экспорта.");

		$this->doExport($this->_list, $fullFilepath);

		return SITE_URL . $filepath;
	}

	protected abstract function doExport($list, $filepath);

	protected function getFileRelativePath() {
		return FILES_DIR . 'export/' . $this->getFileName();
	}

	protected function getFileName() {
		return ($this->_filename ? $this->_filename : date('YmdHis')) . '.' . $this->getFileExt();
	}

	/**
	 * Возвращает расширение файла.
	 * @return string Расширение файла.
	 */
	protected abstract function getFileExt();
}