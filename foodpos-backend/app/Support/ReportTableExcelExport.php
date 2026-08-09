<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportTableExcelExport
{
    private const HEADER_FILL = 'FFE5E7EB';

    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     * @param  list<list<scalar|null>>  $summaryRows
     */
    public function __construct(
        private string $businessName,
        private string $title,
        private ?string $branchLabel,
        private ?string $from,
        private ?string $to,
        private mixed $generatedAt,
        private array $headers,
        private array $rows,
        private array $summaryRows = [],
    ) {}

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
            ->setTitle($this->title);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($this->title, 0, 31));

        $columnCount = max(count($this->headers), 1);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $row = 1;
        $sheet->setCellValue("A{$row}", $this->businessName);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue("A{$row}", $this->title);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        if ($this->from !== null && $this->from !== '' && $this->to !== null && $this->to !== '') {
            $sheet->setCellValue("A{$row}", 'Period: '.format_date($this->from).' – '.format_date($this->to));
            $row++;
        } elseif ($this->from !== null && $this->from !== '') {
            $sheet->setCellValue("A{$row}", 'From: '.format_date($this->from));
            $row++;
        } elseif ($this->to !== null && $this->to !== '') {
            $sheet->setCellValue("A{$row}", 'To: '.format_date($this->to));
            $row++;
        }

        if ($this->branchLabel) {
            $sheet->setCellValue("A{$row}", 'Branch: '.$this->branchLabel);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Generated: '.format_datetime($this->generatedAt));
        $row += 2;

        if ($this->summaryRows !== []) {
            foreach ($this->summaryRows as $summaryRow) {
                foreach (array_values($summaryRow) as $columnIndex => $value) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($columnIndex + 1).$row,
                        $value
                    );
                }
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFont()->setBold(true);
                $row++;
            }
            $row++;
        }

        $headerRow = $row;
        foreach (array_values($this->headers) as $columnIndex => $header) {
            $sheet->setCellValue(
                Coordinate::stringFromColumnIndex($columnIndex + 1).$row,
                $header
            );
        }
        $this->styleHeaderRow($sheet, $row, $lastColumn);
        $row++;

        foreach ($this->rows as $dataRow) {
            foreach (array_values($dataRow) as $columnIndex => $value) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($columnIndex + 1).$row,
                    $value
                );
            }
            $row++;
        }

        for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        if ($headerRow < $row) {
            $sheet->freezePane('A'.($headerRow + 1));
        }

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
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }
}
