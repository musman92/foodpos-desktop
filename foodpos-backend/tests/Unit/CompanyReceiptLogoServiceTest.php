<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\CompanyReceiptLogoService;
use Tests\TestCase;

class CompanyReceiptLogoServiceTest extends TestCase
{
    public function test_regenerate_fails_when_company_has_no_logo(): void
    {
        $company = new Company(['logo' => null, 'logo_print' => null]);

        $result = (new CompanyReceiptLogoService)->regenerateForCompany($company);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no logo', strtolower($result['message']));
    }

    public function test_regenerate_fails_for_svg_logo(): void
    {
        $company = new Company(['logo' => 'companies/logos/acme.svg', 'logo_print' => null]);

        $result = (new CompanyReceiptLogoService)->regenerateForCompany($company);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('svg', strtolower($result['message']));
    }
}
