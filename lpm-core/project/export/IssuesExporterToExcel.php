<?php
use \GMFramework\DateTimeUtils as DTU;

/**
 * Класс, позволяющий экспортировать задачи в Excel файл.
 */
class IssuesExporterToExcel extends IssuesExporter {
	protected function doExport($list, $filepath) {
		$doc = new \PHPExcel();
		$sheet = $doc->getActiveSheet();
		$sheet->setTitle('Задачи');	

		$sheet->setCellValue('A1', 'Дата завершения');
		$sheet->setCellValue('B1', 'Задача');
		$sheet->setCellValue('C1', 'SP'); // TODO: по идее надо проверять на scrum/не scrum

		$row = 2;
		foreach ($list as $issue) {
			$sheet->setCellValue('A' . $row, DTU::date('Y-m-d', $issue->completedDate));
			$sheet->setCellValue('B' . $row, $issue->name);
			$sheet->setCellValue('C' . $row, $issue->hours);
			$row++;
		}

		// Устанавливаем формат, чтобы было красиво
		$row--;
		$sheet->getColumnDimension('A')->setAutoSize(true);
		$sheet->getColumnDimension('B')->setAutoSize(true);
		//$sheet->getColumnDimension('B')->setWidth(100);
		$sheet->getStyle('A2:A' . $row)->getNumberFormat()->setFormatCode(
			\PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2);
		$sheet->getStyle('B2:B' . $row)->getNumberFormat()->setFormatCode(
			\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		$sheet->getStyle('C2:C' . $row)->getNumberFormat()->setFormatCode(
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