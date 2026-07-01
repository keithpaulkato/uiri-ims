<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_is_blocked_from_store_manager_route(): void
    {
        Route::middleware(['web', 'auth', 'role.min:Store Manager'])->get('/_probe', fn () => 'ok');

        $response = $this
            ->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get('/_probe');

        $response->assertForbidden();
    }

    public function test_administrator_passes_store_manager_route(): void
    {
        Route::middleware(['web', 'auth', 'role.min:Store Manager'])->get('/_probe', fn () => 'ok');

        $response = $this
            ->actingAs(User::factory()->create(['role' => 'Administrator']))
            ->get('/_probe');

        $response->assertOk();
    }
}
