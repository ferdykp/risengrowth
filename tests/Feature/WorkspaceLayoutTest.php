<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_and_review_use_the_same_workspace_navigation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['/', route('reviews.index'), route('settings.index')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="rg-sidebar"', false)
                ->assertSee('id="rg-menu-toggle"', false)
                ->assertSee('href="' . route('reviews.index') . '"', false)
                ->assertSee('href="' . route('settings.index') . '"', false)
                ->assertSee('action="' . route('logout') . '"', false)
                ->assertSee('method="POST"', false)
                ->assertDontSee('>7</span>', false);
        }
    }

    public function test_admin_menu_is_hidden_from_members_and_visible_to_admin(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get('/')
            ->assertOk()
            ->assertDontSee('User Management');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertSee('User Management');
    }
}
