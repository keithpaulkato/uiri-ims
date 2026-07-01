<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_can_log_in(): void
    {
        $this->seed();

        $response = $this->post('/login', [
            'email' => 'admin@uiri.go.ug',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'admin@uiri.go.ug',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
