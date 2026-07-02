<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_active_branch_follows_session_with_home_fallback(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'branch_id' => 1]);

        // No switch yet -> falls back to home branch.
        $this->assertSame(1, $admin->activeBranchId());

        // After switching -> follows the session value.
        session(['active_branch_id' => 2]);
        $this->assertSame(2, $admin->activeBranchId());
    }

    public function test_non_administrator_ignores_session_and_uses_home_branch(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => 1]);

        session(['active_branch_id' => 2]);

        // Non-admins cannot switch; always their assigned branch.
        $this->assertSame(1, $manager->activeBranchId());
    }
}
