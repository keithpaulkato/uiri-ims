<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_switch_branch(): void
    {
        $branch = Branch::create([
            'name' => 'UIRI Namanve',
            'location' => 'Namanve, Mukono',
            'is_headquarters' => false,
        ]);

        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this
            ->actingAs($admin)
            ->post(route('branch.switch'), ['branch_id' => $branch->id]);

        $response->assertRedirect();
        $this->assertSame($branch->id, session('active_branch_id'));
    }

    public function test_store_manager_is_forbidden_from_switching_branch(): void
    {
        $branch = Branch::create([
            'name' => 'UIRI Namanve',
            'location' => 'Namanve, Mukono',
            'is_headquarters' => false,
        ]);

        $manager = User::factory()->create(['role' => 'Store Manager']);

        $response = $this
            ->actingAs($manager)
            ->post(route('branch.switch'), ['branch_id' => $branch->id]);

        $response->assertForbidden();
    }
}
