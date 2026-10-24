<?php

namespace Tests\Feature;

use App\Models\SearchKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WibDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_shows_wib_clock_with_server_reference_and_preserves_utc_storage_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24T17:00:00Z'));

        $response = $this->actingAs(User::factory()->create())->get(route('comments.index'));

        $response->assertOk()->assertSee('00:00:00 WIB')->assertSee('25 Okt 2026')->assertSee('data-wib-clock', false)
            ->assertSee('data-server-time="2026-10-25T00:00:00+07:00"', false)->assertSee('/wib-clock.js', false);
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame(2, substr_count($response->getContent(), 'data-wib-clock'));
    }

    public function test_discovery_history_and_comment_timestamps_are_displayed_in_wib_without_rewriting_stored_values(): void
    {
        $user = User::factory()->admin()->create();
        $keyword = SearchKeyword::create(['user_id' => $user->id, 'platform' => 'threads', 'phrase' => 'website', 'normalized_phrase' => 'website', 'enabled' => true]);
        DB::table('discovery_runs')->insert(['search_keyword_id' => $keyword->id, 'source_type' => 'python', 'run_uuid' => (string) Str::uuid(),
            'status' => 'completed', 'fetched_count' => 1, 'created_at' => '2026-10-24 18:15:00', 'updated_at' => '2026-10-24 18:15:00']);
        DB::table('comment_suggestions')->insert(['user_id' => $user->id, 'post_text' => 'Butuh website katalog untuk toko saya.', 'tone' => 'casual', 'provider' => 'template',
            'body' => 'Boleh tahu produknya ada berapa jenis?', 'created_at' => '2026-10-24 18:15:00', 'updated_at' => '2026-10-24 18:15:00']);

        $this->actingAs($user)->get(route('discovery.index'))->assertOk()->assertSee('25 Oct 2026, 01:15 WIB');
        $this->getJson(route('discovery.index'))->assertOk()->assertSee('25 Oct 2026, 01:15 WIB');
        $this->get(route('comments.index'))->assertOk()->assertSee('25 Oct, 01:15 WIB');
        $this->assertDatabaseHas('discovery_runs', ['created_at' => '2026-10-24 18:15:00']);
        $this->assertDatabaseHas('comment_suggestions', ['created_at' => '2026-10-24 18:15:00']);
    }
}
