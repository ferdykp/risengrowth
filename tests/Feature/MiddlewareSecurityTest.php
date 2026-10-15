<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiddlewareSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_response_is_not_cached_and_has_security_headers(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
    }

    public function test_private_dashboard_is_not_cached(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
    }

    public function test_guest_cannot_access_private_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_requests_are_throttled(): void
    {
        User::factory()->create(['email' => 'rate-test@example.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'rate-test@example.test', 'password' => 'invalid']);
        }
        $this->post('/login', ['email' => 'rate-test@example.test', 'password' => 'invalid'])
            ->assertStatus(429);
    }
}
