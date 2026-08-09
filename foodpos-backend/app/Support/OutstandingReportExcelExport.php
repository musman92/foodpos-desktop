<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OutstandingReportExcelExport
{
    private const HEADER_FILL = 'FFE5E7EB';

    private int $decimals;

    private string $amountFormat;

    public function __construct(
        private array $report,
        private string $reportTitle,
        private string $partyLabel,
        private string $amountLabel,
        private string $businessName,
        private ?string $branchLabel,
        private mixed $generatedAt,
    ) {
        $this->decimals = (int) (get_company_config()['decimal_points'] ?? 2);
        $this->amountFormat = '#,##0'.($this->decimals > 0 ? '.'.str_repeat('0', $this->decimals) : '');
    }

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator($this->businessName)
            ->setTitle($this->reportTitle);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($this->reportTitle, 0, 31));

        $row = 1;
        $sheet->setCellValue("A{$row}", $this->businessName);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue("A{$row}", $this->reportTitle);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $sheet->setCellValue("A{$row}", 'As of '.format_date($this->report['as_of']));
        $row++;

        if ($this->branchLabel) {
            $sheet->setCellValue("A{$row}", 'Branch: '.$this->branchLabel);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Generated: '.format_datetime($this->generatedAt));
        $row += 2;

        $sheet->setCellValue("A{$row}", Str::plural($this->partyLabel));
        $sheet->setCellValue("B{$row}", (string) $this->report['party_count']);
        $sheet->setCellValue("C{$row}", 'Total '.$this->amountLabel);
        $sheet->setCellValue("D{$row}", (float) $this->report['total']);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row += 2;

        $headerRow = $row;
        $sheet->setCellValue("A{$row}", $this->partyLabel);
        $sheet->setCellValue("B{$row}", 'Contact');
        $sheet->setCellValue("C{$row}", $this->amountLabel);
        $this->styleHeaderRow($sheet, $row, 'C');
        $row++;

        foreach ($this->report['rows'] as $entry) {
            $sheet->setCellValue("A{$row}", $entry['name']);
            $sheet->setCellValue("B{$row}", $entry['contact'] ?? '—');
            $sheet->setCellValue("C{$row}", (float) $entry['balance']);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $row++;
        }

        if ($this->report['party_count'] > 0) {
            $sheet->setCellValue("A{$row}", 'Total');
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValue("C{$row}", (float) $this->report['total']);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:C{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(36);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getStyle("C{$headerRow}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return $spreadsheet;
    }

    private function styleHeaderRow(Worksheet $sheet, int $row, string $lastColumn): void
    {
        $range = "A{$row}:{$lastColumn}{$row}";
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
    }
}
