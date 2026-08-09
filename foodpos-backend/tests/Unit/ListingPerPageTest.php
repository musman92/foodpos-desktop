<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ListingPerPageTest extends TestCase
{
    public function test_normalize_accepts_allowed_sizes_only(): void
    {
        $this->assertSame(15, ListingPerPage::normalize(15));
        $this->assertSame(15, ListingPerPage::normalize(99));
    }

    public function test_for_company_uses_tenant_setting(): void
    {
        $company = new Company([
            'settings' => ['listing_per_page' => 25],
        ]);

        $this->assertSame(25, ListingPerPage::forCompany($company));
    }

    public function test_from_request_prefers_query_param_over_company_setting(): void
    {
        $company = new Company([
            'settings' => ['listing_per_page' => 25],
        ]);

        $request = Request::create('/', 'GET', ['per_page' => 10]);

        $this->assertSame(10, ListingPerPage::fromRequest($request, $company));
    }
}
