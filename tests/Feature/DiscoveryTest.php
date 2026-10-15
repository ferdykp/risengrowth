<?php

namespace Tests\Feature;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }


    public function test_empty_discovery_page_is_rendered(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertSee('Eksplorasi Prospek')
            ->assertSee('Belum ada riwayat pencarian')
            ->assertSee('Pencarian dikelola Administrator')
            ->assertDontSee('Jalankan Pencarian');
    }

    public function test_admin_can_see_discovery_search_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertSee('Eksplorasi Prospek')
            ->assertSee('Jalankan Pencarian')
            ->assertSee(
                'action="' . route('discovery.run') . '"',
                false
            );
    }



    public function test_invalid_status_filter_is_rejected(): void
    {
        $this->get(route('discovery.index', ['status' => 'unexpected']))
            ->assertSessionHasErrors('status');
    }

    public function test_existing_keyword_is_displayed_without_running_discovery(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('search_keywords')->insert([
            'user_id' => $userId,
            'platform' => 'threads',
            'phrase' => 'butuh website',
            'normalized_phrase' => 'butuh website',
            'category' => 'direct_demand',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->get(route('discovery.index'))
            ->assertOk()->assertSee('butuh website');
        $this->assertDatabaseCount('discovery_runs', 0);
    }
}
