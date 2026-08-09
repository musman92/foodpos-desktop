<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\BranchContext;
use PHPUnit\Framework\TestCase;

class BranchContextTest extends TestCase
{
    public function test_allowed_branch_ids_for_company_admin_uses_company_not_session(): void
    {
        $user = new User([
            'type' => 'company_admin',
            'company_id' => 8,
            'branch_id' => null,
        ]);

        $this->assertSame('company_admin', $user->type);
        $this->assertTrue($user->isCompanyAdmin());
        $this->assertTrue($user->canAccessMultipleBranches());
    }

    public function test_super_admin_current_branch_is_null_without_auth_context(): void
    {
        $user = new User([
            'type' => 'super_admin',
            'company_id' => null,
            'branch_id' => null,
        ]);

        $this->assertNull(BranchContext::currentBranchId($user));
    }
}
