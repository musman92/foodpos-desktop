<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Company;
use App\Services\TimezoneService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class PosBranchCollectionTest extends TestCase
{
    public function test_plain_collection_does_not_support_load_missing(): void
    {
        $branch = new Branch(['id' => 1, 'timezone' => 'Asia/Karachi']);
        $branches = collect([$branch]);

        $this->assertInstanceOf(Collection::class, $branches);
        $this->assertFalse(method_exists($branches, 'loadMissing'));
    }

    public function test_branch_timezones_map_works_with_plain_collection(): void
    {
        $company = new Company(['timezone' => 'Asia/Karachi']);
        $company->id = 1;

        $branch = new Branch(['timezone' => 'UTC', 'company_id' => 1]);
        $branch->id = 10;
        $branch->setRelation('company', $company);

        $service = new TimezoneService();
        $map = $service->branchTimezonesMap(collect([$branch]));

        $this->assertSame('Asia/Karachi', $map[10]);
    }

    public function test_branch_timezones_map_works_with_eloquent_collection(): void
    {
        $company = new Company(['timezone' => 'Asia/Karachi']);
        $company->id = 1;

        $branch = new Branch(['timezone' => 'Asia/Karachi', 'company_id' => 1]);
        $branch->id = 20;
        $branch->setRelation('company', $company);

        $branches = (new Branch)->newCollection([$branch]);

        $service = new TimezoneService();
        $map = $service->branchTimezonesMap($branches);

        $this->assertSame('Asia/Karachi', $map[20]);
    }
}
