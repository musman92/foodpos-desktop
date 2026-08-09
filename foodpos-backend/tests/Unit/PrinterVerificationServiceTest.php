<?php

namespace Tests\Unit;

use App\Models\Printer;
use App\Services\PrinterVerificationService;
use Tests\TestCase;

class PrinterVerificationServiceTest extends TestCase
{
    public function test_verify_fails_when_os_name_missing_on_desktop_printer(): void
    {
        $printer = new Printer([
            'printing_mode' => Printer::MODE_DIRECT,
            'device_name' => '',
            'branch_id' => 1,
        ]);

        $result = (new PrinterVerificationService)->verify($printer);

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_config', $result['match_type']);
    }

    public function test_verify_finds_exact_match_in_branch_catalog(): void
    {
        $printer = new Printer([
            'printing_mode' => Printer::MODE_DIRECT,
            'device_name' => 'Kitchen-80mm',
            'branch_id' => 1,
        ]);

        $mock = $this->getMockBuilder(PrinterVerificationService::class)
            ->onlyMethods(['branchPrinterCatalog', 'latestPrinterListTimestamp'])
            ->getMock();
        $mock->method('branchPrinterCatalog')->willReturn([
            ['name' => 'Kitchen-80mm', 'source' => 'Counter PC', 'is_default' => false],
        ]);
        $mock->method('latestPrinterListTimestamp')->willReturn('2026-01-01 00:00:00');

        $result = $mock->verify($printer);

        $this->assertTrue($result['ok']);
        $this->assertSame('exact', $result['match_type']);
    }

    public function test_verify_detects_case_mismatch(): void
    {
        $mock = $this->getMockBuilder(PrinterVerificationService::class)
            ->onlyMethods(['branchPrinterCatalog', 'latestPrinterListTimestamp'])
            ->getMock();
        $mock->method('branchPrinterCatalog')->willReturn([
            ['name' => 'Receipt Printer', 'source' => 'Counter PC', 'is_default' => true],
        ]);
        $mock->method('latestPrinterListTimestamp')->willReturn('2026-01-01 00:00:00');

        $printer = new Printer([
            'printing_mode' => Printer::MODE_DIRECT,
            'device_name' => 'receipt printer',
            'branch_id' => 1,
        ]);

        $result = $mock->verify($printer);

        $this->assertFalse($result['ok']);
        $this->assertSame('case', $result['match_type']);
        $this->assertSame('Receipt Printer', $result['suggested_name']);
    }
}
