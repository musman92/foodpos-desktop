<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodClosingExcelExport
{
    private const HEADER_FILL = 'FFE5E7EB';

    private const STRIPE_FILL = 'FFF9FAFB';

    private const SECTION_FILL = 'FFF3F4F6';

    private const DAY_HEAD_FILL = 'FFF3F4F6';

    private const CLOSING_HIGHLIGHT_FILL = 'FFEEF2FF';

    private const PURCHASE_START = 'A';

    private const PURCHASE_END = 'E';

    private const DAILY_LABEL = 'G';

    private const DAILY_VALUE = 'H';

    private const CLOSING_LABEL = 'J';

    private const CLOSING_VALUE = 'K';

    private const FULL_WIDTH_START = 'A';

    private const FULL_WIDTH_END = 'K';

    private int $decimals;

    private string $amountFormat;

    public function __construct(
        private array $report,
        private string $reportTitle,
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
        $row = $this->writeReportHeader($sheet, $row);
        $row += 2;

        $firstContentRow = null;

        foreach ($this->report['periods'] as $section) {
            if ($firstContentRow === null) {
                $firstContentRow = $row + 2;
            }

            $row = $this->writePeriodBlock($sheet, $row, $section);
            $row += 2;
        }

        if (count($this->report['periods']) > 1) {
            $row = $this->writeGrandTotalBlock($sheet, $row);
            $row += 2;
        }

        $this->writeMergedRow($sheet, $row, 'Stock in hand uses current inventory value at report time, not a historical snapshot.');
        $sheet->getStyle(self::FULL_WIDTH_START.$row)->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle($this->fullWidthRange($row))->getAlignment()->setWrapText(true);

        $this->autoSizeColumns($sheet, self::PURCHASE_START, self::PURCHASE_END);
        $this->autoSizeColumns($sheet, self::DAILY_LABEL, self::DAILY_VALUE);
        $this->autoSizeColumns($sheet, self::CLOSING_LABEL, self::CLOSING_VALUE);
        $sheet->getColumnDimension('F')->setWidth(2);
        $sheet->getColumnDimension('I')->setWidth(2);

        if ($firstContentRow !== null) {
            $sheet->freezePane('A'.$firstContentRow);
        }

        return $spreadsheet;
    }

    private function writeReportHeader(Worksheet $sheet, int $row): int
    {
        $this->writeMergedRow($sheet, $row, $this->reportTitle, bold: true, size: 14);
        $row++;

        $this->writeMergedRow($sheet, $row, $this->businessName);
        $row++;

        if ($this->branchLabel) {
            $this->writeMergedRow($sheet, $row, 'Branch: '.$this->branchLabel);
            $row++;
        }

        $this->writeMergedRow($sheet, $row, 'Generated: '.format_datetime($this->generatedAt));

        return $row;
    }

    private function writePeriodBlock(Worksheet $sheet, int $row, array $section): int
    {
        $currencySymbol = currency_symbol();

        $this->writeMergedRow(
            $sheet,
            $row,
            $section['label'].' · '.format_date($section['from']).' – '.format_date($section['to']),
            bold: true,
            size: 11,
            fill: self::SECTION_FILL
        );
        $row++;

        $showStock = (bool) ($section['show_stock'] ?? true);
        $sectionRow = $row;

        if ($showStock) {
            $sheet->setCellValue('A'.$sectionRow, 'Available stock');
            $sheet->mergeCells('A'.$sectionRow.':'.self::PURCHASE_END.$sectionRow);
            $this->styleSectionTitle($sheet, 'A'.$sectionRow.':'.self::PURCHASE_END.$sectionRow);
        }

        $sheet->setCellValue(self::DAILY_LABEL.$sectionRow, 'Daily sales ('.$currencySymbol.')');
        $sheet->mergeCells(self::DAILY_LABEL.$sectionRow.':'.self::DAILY_VALUE.$sectionRow);
        $sheet->setCellValue(self::CLOSING_LABEL.$sectionRow, 'Closing ('.$currencySymbol.')');
        $sheet->mergeCells(self::CLOSING_LABEL.$sectionRow.':'.self::CLOSING_VALUE.$sectionRow);
        $this->styleSectionTitle($sheet, self::DAILY_LABEL.$sectionRow.':'.self::DAILY_VALUE.$sectionRow);
        $this->styleSectionTitle($sheet, self::CLOSING_LABEL.$sectionRow.':'.self::CLOSING_VALUE.$sectionRow);

        $contentRow = $sectionRow + 1;
        $purchaseEnd = $showStock
            ? $this->writeStockBlock($sheet, $contentRow, $section, $currencySymbol)
            : $contentRow;
        $dailyEnd = $this->writeDailySalesCardsBlock($sheet, $contentRow, $section);
        $closingEnd = $this->writeClosingBlock($sheet, $contentRow, $section['closing']);

        return max($purchaseEnd, $dailyEnd, $closingEnd);
    }

    private function writeGrandTotalBlock(Worksheet $sheet, int $row): int
    {
        $currencySymbol = currency_symbol();

        $this->writeMergedRow(
            $sheet,
            $row,
            'Grand total ('.$currencySymbol.')',
            bold: true,
            size: 11,
            fill: 'FFEEF2FF'
        );
        $row++;

        $sectionRow = $row;
        $sheet->setCellValue(self::CLOSING_LABEL.$sectionRow, 'Closing ('.$currencySymbol.')');
        $sheet->mergeCells(self::CLOSING_LABEL.$sectionRow.':'.self::CLOSING_VALUE.$sectionRow);
        $this->styleSectionTitle($sheet, self::CLOSING_LABEL.$sectionRow.':'.self::CLOSING_VALUE.$sectionRow);

        return $this->writeClosingBlock($sheet, $sectionRow + 1, $this->report['grand_closing']);
    }

    private function writeMergedRow(
        Worksheet $sheet,
        int $row,
        string $text,
        bool $bold = false,
        int $size = 10,
        ?string $fill = null
    ): void {
        $sheet->setCellValue(self::FULL_WIDTH_START.$row, $text);
        $sheet->mergeCells($this->fullWidthRange($row));

        $style = $sheet->getStyle($this->fullWidthRange($row));
        $style->getFont()->setSize($size);
        if ($bold) {
            $style->getFont()->setBold(true);
        }
        if ($fill !== null) {
            $style->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($fill);
        }
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function fullWidthRange(int $row): string
    {
        return self::FULL_WIDTH_START.$row.':'.self::FULL_WIDTH_END.$row;
    }

    private function writeStockBlock(Worksheet $sheet, int $startRow, array $section, string $currencySymbol): int
    {
        $row = $startRow;
        $headers = ['S/No', 'Product', 'Rate ('.$currencySymbol.')', 'Qty', 'Amount ('.$currencySymbol.')'];
        $sheet->fromArray($headers, null, self::PURCHASE_START.$row);
        $this->styleTableHeader($sheet, self::PURCHASE_START.$row.':'.self::PURCHASE_END.$row);
        $row++;

        $dataStart = $row;
        $stockLines = $section['stock'] ?? [];

        if (count($stockLines) === 0) {
            $sheet->setCellValue(self::PURCHASE_START.$row, 'No available stock.');
            $sheet->mergeCells(self::PURCHASE_START.$row.':'.self::PURCHASE_END.$row);
            $row++;
        } else {
            foreach ($stockLines as $line) {
                $sheet->fromArray([
                    $line['sno'],
                    $line['product'],
                    (float) $line['rate'],
                    (float) $line['qty'],
                    (float) $line['amount'],
                ], null, self::PURCHASE_START.$row);
                $row++;
            }

            $sheet->setCellValue('D'.$row, 'Total');
            $sheet->setCellValue('E'.$row, (float) ($section['stock_total'] ?? 0));
            $sheet->getStyle('D'.$row.':E'.$row)->getFont()->setBold(true);
            $this->applyAmountFormat($sheet, 'C'.$dataStart.':C'.($row - 1));
            $this->applyAmountFormat($sheet, 'E'.$dataStart.':E'.$row);
            $this->applyQuantityFormat($sheet, 'D'.$dataStart.':D'.($row - 1));
            $row++;
        }

        $this->styleTableBorders($sheet, self::PURCHASE_START.$startRow.':'.self::PURCHASE_END.($row - 1));
        $this->applyStripes(
            $sheet,
            $dataStart,
            $row - (count($stockLines) > 0 ? 2 : 1),
            self::PURCHASE_START,
            self::PURCHASE_END
        );

        return $row;
    }

    private function writeDailySalesCardsBlock(Worksheet $sheet, int $startRow, array $section): int
    {
        $row = $startRow;

        foreach ($section['daily_sales'] as $day) {
            $cardStart = $row;

            $sheet->setCellValue(self::DAILY_LABEL.$row, $day['label']);
            $sheet->setCellValue(self::DAILY_VALUE.$row, format_date($day['date']));
            $sheet->getStyle(self::DAILY_LABEL.$row.':'.self::DAILY_VALUE.$row)->getFont()->setBold(true);
            $sheet->getStyle(self::DAILY_LABEL.$row.':'.self::DAILY_VALUE.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::DAY_HEAD_FILL);
            $sheet->getStyle(self::DAILY_VALUE.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;

            $sheet->setCellValue(self::DAILY_LABEL.$row, 'Total daily sale');
            $sheet->setCellValue(self::DAILY_VALUE.$row, (float) $day['total_sale']);
            $sheet->getStyle(self::DAILY_LABEL.$row.':'.self::DAILY_VALUE.$row)->getFont()->setBold(true);
            $this->applyAmountFormat($sheet, self::DAILY_VALUE.$row);
            $row++;

            foreach ($day['payments'] as $payment) {
                $sheet->setCellValue(self::DAILY_LABEL.$row, $payment['label']);
                $sheet->setCellValue(self::DAILY_VALUE.$row, (float) $payment['amount']);
                $this->applyAmountFormat($sheet, self::DAILY_VALUE.$row);
                $row++;
            }

            $sheet->setCellValue(self::DAILY_LABEL.$row, 'Cash receivable');
            $sheet->setCellValue(self::DAILY_VALUE.$row, (float) ($day['cash_receivable'] ?? 0));
            $this->applyAmountFormat($sheet, self::DAILY_VALUE.$row);
            $row++;

            $sheet->setCellValue(self::DAILY_LABEL.$row, 'Total receivable');
            $sheet->setCellValue(self::DAILY_VALUE.$row, (float) ($day['total_receivable'] ?? 0));
            $this->applyAmountFormat($sheet, self::DAILY_VALUE.$row);
            $row++;

            $sheet->setCellValue(self::DAILY_LABEL.$row, 'Expenses');
            $sheet->setCellValue(self::DAILY_VALUE.$row, (float) ($day['expense_total'] ?? 0));
            $this->applyAmountFormat($sheet, self::DAILY_VALUE.$row);
            $row++;

            $sheet->setCellValue(self::DAILY_LABEL.$row, 'Cash in hand');
            $sheet->setCellValue(self::DAILY_VALUE.$row, (float) ($day['cash_in_hand'] ?? 0));
            $sheet->getStyle(self::DAILY_LABEL.$row.':'.self::DAILY_VALUE.$row)->getFont()->setBold(true);
            $sheet->getStyle(self::DAILY_LABEL.$row.':'.self::DAILY_VALUE.$row)->getFont()->getColor()
                ->setARGB('FF4338CA');
            $this->applyAmountFormat($sheet, self::DAILY_VALUE.$row);
            $row++;

            $cardEnd = $row - 1;
            $this->styleTableBorders($sheet, self::DAILY_LABEL.$cardStart.':'.self::DAILY_VALUE.$cardEnd);
            $row++;
        }

        return $row;
    }

    private function writeClosingBlock(Worksheet $sheet, int $startRow, array $closing): int
    {
        $row = $startRow;

        $rows = [
            ['Total sale', (float) $closing['total_sale'], false],
            ['COGS', (float) ($closing['cogs_total'] ?? $closing['purchase_total']), false],
            ['Expenses', (float) $closing['expense_total'], false],
            ['Total', (float) $closing['pnl'], true],
        ];

        if ($closing['stock_in_hand'] !== null) {
            $rows[] = ['Stock in hand', (float) $closing['stock_in_hand'], false];
            $rows[] = ['Closing amount', (float) $closing['closing_amount'], true, true];
        }

        $dataStart = $row;
        $closingAmountRow = null;

        foreach ($rows as $line) {
            $sheet->setCellValue(self::CLOSING_LABEL.$row, $line[0]);
            $sheet->setCellValue(self::CLOSING_VALUE.$row, $line[1]);

            if ($line[2]) {
                $sheet->getStyle(self::CLOSING_LABEL.$row.':'.self::CLOSING_VALUE.$row)->getFont()->setBold(true);
            }

            if (($line[3] ?? false) === true) {
                $closingAmountRow = $row;
            }

            if ($line[0] === 'Total') {
                $color = $closing['pnl'] >= 0 ? 'FF15803D' : 'FFB91C1C';
                $sheet->getStyle(self::CLOSING_VALUE.$row)->getFont()->getColor()->setARGB($color);
            }

            $row++;
        }

        $dataEnd = $row - 1;
        $this->applyAmountFormat($sheet, self::CLOSING_VALUE.$dataStart.':'.self::CLOSING_VALUE.$dataEnd);
        $this->styleTableBorders($sheet, self::CLOSING_LABEL.$dataStart.':'.self::CLOSING_VALUE.$dataEnd);
        $this->applyStripes($sheet, $dataStart, $dataEnd, self::CLOSING_LABEL, self::CLOSING_VALUE);

        if ($closingAmountRow !== null) {
            $sheet->getStyle(self::CLOSING_LABEL.$closingAmountRow.':'.self::CLOSING_VALUE.$closingAmountRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLOSING_HIGHLIGHT_FILL);
            $sheet->getStyle(self::CLOSING_LABEL.$closingAmountRow.':'.self::CLOSING_VALUE.$closingAmountRow)->getFont()->setBold(true);
            $sheet->getStyle(self::CLOSING_LABEL.$closingAmountRow.':'.self::CLOSING_VALUE.$closingAmountRow)->getFont()->getColor()
                ->setARGB('FF312E81');
        }

        return $row;
    }

    private function styleSectionTitle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::SECTION_FILL);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleTableHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleTableBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFD1D5DB'));
    }

    private function applyStripes(Worksheet $sheet, int $startRow, int $endRow, string $firstCol, string $lastCol): void
    {
        if ($endRow < $startRow) {
            return;
        }

        for ($row = $startRow; $row <= $endRow; $row++) {
            if (($row - $startRow) % 2 === 1) {
                $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(self::STRIPE_FILL);
            }
        }
    }

    private function applyAmountFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode($this->amountFormat);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function applyQuantityFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.##');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function autoSizeColumns(Worksheet $sheet, string $from, string $to): void
    {
        for ($col = $from; $col <= $to; $col++) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
