<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled(): void
    {
        // Self-registration is disabled for this internal IMS; accounts are
        // created by administrators. Both register routes must be absent.
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
