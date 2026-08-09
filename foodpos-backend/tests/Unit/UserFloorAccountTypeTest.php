<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserFloorAccountTypeTest extends TestCase
{
    public function test_waiter_and_rider_account_helpers(): void
    {
        $waiter = new User(['type' => 'waiter']);
        $rider = new User(['type' => 'rider']);
        $both = new User(['type' => 'waiter_rider']);
        $staff = new User(['type' => 'staff']);
        $admin = new User(['type' => 'company_admin']);

        $this->assertTrue($waiter->isStaffLike());
        $this->assertTrue($waiter->canServeAsWaiter());
        $this->assertFalse($waiter->canServeAsRider());

        $this->assertTrue($rider->isStaffLike());
        $this->assertFalse($rider->canServeAsWaiter());
        $this->assertTrue($rider->canServeAsRider());

        $this->assertTrue($both->isStaffLike());
        $this->assertTrue($both->canServeAsWaiter());
        $this->assertTrue($both->canServeAsRider());

        $this->assertTrue($staff->isStaffLike());
        $this->assertFalse($staff->canServeAsWaiter());
        $this->assertFalse($staff->canServeAsRider());

        $this->assertFalse($admin->isStaffLike());
        $this->assertSame('Waiter / Rider', $both->accountTypeLabel());
    }
}
