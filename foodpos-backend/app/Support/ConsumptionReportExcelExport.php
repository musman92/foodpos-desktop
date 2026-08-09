<?php

namespace App\Support;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsumptionReportExcelExport
{
    private const HEADER_FILL = 'FFE5E7EB';

    private int $decimals;

    private string $amountFormat;

    private string $qtyFormat;

    /**
     * @param  array{total_cost: float, sales_cost: float, adjustment_cost: float, item_count: int}  $summary
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private array $summary,
        private Collection $rows,
        private string $businessName,
        private ?string $branchLabel,
        private string $from,
        private string $to,
        private mixed $generatedAt,
        private string $search = '',
    ) {
        $this->decimals = (int) (get_company_config()['decimal_points'] ?? 2);
        $this->amountFormat = '#,##0'.($this->decimals > 0 ? '.'.str_repeat('0', $this->decimals) : '');
        $this->qtyFormat = '#,##0.00';
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
            ->setTitle('Consumption Report');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Consumption');

        $row = 1;
        $sheet->setCellValue("A{$row}", $this->businessName);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue("A{$row}", 'Consumption Report');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $sheet->setCellValue("A{$row}", 'Period: '.format_date($this->from).' – '.format_date($this->to));
        $row++;

        if ($this->branchLabel) {
            $sheet->setCellValue("A{$row}", 'Branch: '.$this->branchLabel);
            $row++;
        }

        if ($this->search !== '') {
            $sheet->setCellValue("A{$row}", 'Search: '.$this->search);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Generated: '.format_datetime($this->generatedAt));
        $row += 2;

        $sheet->setCellValue("A{$row}", 'Total consumption value');
        $sheet->setCellValue("B{$row}", (float) $this->summary['total_cost']);
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
        $sheet->setCellValue("C{$row}", 'From sales');
        $sheet->setCellValue("D{$row}", (float) $this->summary['sales_cost']);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
        $sheet->setCellValue("E{$row}", 'From adjustments');
        $sheet->setCellValue("F{$row}", (float) $this->summary['adjustment_cost']);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
        $sheet->setCellValue("G{$row}", 'Items');
        $sheet->setCellValue("H{$row}", (int) $this->summary['item_count']);
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $row += 2;

        $headerRow = $row;
        $headers = [
            'A' => 'Type',
            'B' => 'Item',
            'C' => 'Code',
            'D' => 'Category',
            'E' => 'Qty used',
            'F' => 'Qty unit',
            'G' => 'Remaining stock',
            'H' => 'Remaining unit',
            'I' => 'Avg unit cost',
            'J' => 'Total cost',
            'K' => 'Sales cost',
            'L' => 'Adjustment cost',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $this->styleHeaderRow($sheet, $row, 'L');
        $row++;

        foreach ($this->rows as $entry) {
            $sheet->setCellValue("A{$row}", $entry['item_type_label'] ?? '');
            $sheet->setCellValue("B{$row}", $entry['name'] ?? '');
            $sheet->setCellValue("C{$row}", $entry['code'] ?? '—');
            $sheet->setCellValue("D{$row}", $entry['category'] ?? '—');
            $sheet->setCellValue("E{$row}", (float) ($entry['quantity'] ?? 0));
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode($this->qtyFormat);
            $sheet->setCellValue("F{$row}", $entry['quantity_unit'] ?? '');
            $sheet->setCellValue("G{$row}", (float) ($entry['remaining_stock'] ?? 0));
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode($this->qtyFormat);
            $sheet->setCellValue("H{$row}", $entry['remaining_stock_unit'] ?? '');
            $sheet->setCellValue("I{$row}", (float) ($entry['avg_unit_cost'] ?? 0));
            $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $sheet->setCellValue("J{$row}", (float) ($entry['total_cost'] ?? 0));
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $sheet->setCellValue("K{$row}", (float) ($entry['sales_cost'] ?? 0));
            $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $sheet->setCellValue("L{$row}", (float) ($entry['adjustment_cost'] ?? 0));
            $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $row++;
        }

        if ($this->rows->isNotEmpty()) {
            $sheet->setCellValue("A{$row}", 'Total');
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValue("J{$row}", (float) $this->summary['total_cost']);
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $sheet->setCellValue("K{$row}", (float) $this->summary['sales_cost']);
            $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $sheet->setCellValue("L{$row}", (float) $this->summary['adjustment_cost']);
            $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode($this->amountFormat);
            $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle("E{$headerRow}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$headerRow}:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("I{$headerRow}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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
