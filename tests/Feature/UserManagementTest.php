<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_accounts_are_active_members_by_default(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->is_active);
        $this->assertSame('member', $user->role);
        $this->assertFalse($user->must_change_password);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_only_admin_can_manage_users(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/users')->assertOk()->assertSee('User Management');
    }

    public function test_admin_can_create_account_and_force_password_change(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Developer', 'email' => 'new@example.test', 'role' => 'member',
            'password' => 'TemporaryPass123!', 'password_confirmation' => 'TemporaryPass123!',
        ])->assertRedirect(route('admin.users.index'));
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('TemporaryPass123!', $user->password));
        $this->actingAs($user)->get('/')->assertRedirect(route('account.initial-password.edit'));
        $this->get(route('account.initial-password.edit'))->assertOk();
        $this->put(route('account.initial-password.update'), [
            'current_password' => 'TemporaryPass123!', 'password' => 'PersonalPassword123!',
            'password_confirmation' => 'PersonalPassword123!',
        ])->assertRedirect(route('login'));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_inactive_account_cannot_login_and_is_logged_out_on_request(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'member',
        ])->assertSessionHasErrors('role');
        $this->patch(route('admin.users.toggle', $admin))->assertSessionHasErrors('account');
        $this->assertEquals('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_can_deactivate_another_member(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.users.toggle', $member))->assertRedirect();
        $this->assertFalse($member->fresh()->is_active);
    }

    public function test_member_cannot_call_admin_actions(): void
    {
        $member = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($member)->patch(route('admin.users.toggle', $target))->assertForbidden();
        $this->actingAs($member)->put(route('admin.users.password', $target), [
            'password' => 'RandomSecure123!', 'password_confirmation' => 'RandomSecure123!',
        ])->assertForbidden();
    }
}
