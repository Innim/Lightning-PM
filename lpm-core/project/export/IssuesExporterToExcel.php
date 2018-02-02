<?php
/**
 * Класс, позволяющий экспортировать задачи в Excel файл.
 */
class IssuesExporterToExcel extends IssuesExporter {
	protected function doExport($list, $filepath) {
		$data = '';
		foreach ($list as $issue) {
			$data .= $issue->name . ';' . $issue->hours . "\n";
		}
		if (!file_put_contents($filepath, $data))
			throw new Exception("Can't save file");

		$doc = new \PHPExcel();
		$sheet = $doc->getActiveSheet();
		$sheet->setTitle('Задачи');	

		$sheet->setCellValue('A1', 'Задача');
		$sheet->setCellValue('B1', 'SP'); // TODO: по идее надо проверять на scrum/не scrum

		$row = 2;
		foreach ($list as $issue) {
			$sheet->setCellValue('A' . $row, $issue->name);
			$sheet->setCellValue('B' . $row, $issue->hours);
			$row++;
		}

		// Устанавливаем формат, чтобы было красиво
		$row--;
		$sheet->getColumnDimension('A')->setWidth(100);
		$sheet->getStyle('A2:A' . $row)->getNumberFormat()->setFormatCode(
			\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		$sheet->getStyle('B2:B' . $row)->getNumberFormat()->setFormatCode(
			\PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);

		try {
			$objWriter = \PHPExcel_IOFactory::createWriter($doc, 'Excel2007');
			$objWriter->save($filepath);
		} catch (Exception $e) {
			throw new Exception("Ошибка при экспорте: " . $e->getMessage());
		}
	}

	protected function getFileExt() {
		return 'xlsx';
	}
}