<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_all_private_pages(): void
    {
        foreach (['/', '/leads', '/discovery', '/keywords', '/comments', '/reviews', '/activity', '/settings'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'Password12345']);
        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'Password12345'])
            ->assertRedirect(route('overview'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);
        $this->post(route('login.submit'), ['email' => 'admin@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_updates_credentials(): void
    {
        $user = User::factory()->create();
        $token = \Illuminate\Support\Facades\Password::createToken($user);
        $this->post(route('password.update'), [
            'email' => $user->email, 'token' => $token,
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewSecurePassword123', $user->fresh()->password));
    }

    public function test_password_change_requires_current_password_and_logs_out(): void
    {
        $user = User::factory()->create(['password' => 'OriginalPassword123']);
        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'wrong', 'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('account.password.update'), [
            'current_password' => 'OriginalPassword123',
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(Hash::check('NewSecurePassword123', $user->fresh()->password));
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
