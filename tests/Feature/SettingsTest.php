<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_settings(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_active' => true,
        ]);

        $this->actingAs($member)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Pengaturan')
            ->assertSee('Integrasi dikelola oleh Administrator.');
    }

    public function test_user_can_save_preferences_without_enabling_discovery(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/settings/preferences', [
            'discovery_interval_minutes' => 180,
            'notify_on_new_leads' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('user_growth_settings', [
            'user_id' => $user->id,
            'discovery_interval_minutes' => 180,
            'notify_on_new_leads' => false,
            'discovery_enabled' => false,
        ]);
    }

    public function test_invalid_interval_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch('/settings/preferences', ['discovery_interval_minutes' => 1, 'notify_on_new_leads' => 1])
            ->assertSessionHasErrors('discovery_interval_minutes');
    }

    public function test_user_cannot_claim_another_users_email(): void
    {
        $current = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($current)->patch('/settings/profile', ['name' => 'Baru', 'email' => $other->email])
            ->assertSessionHasErrors('email');
    }
}
