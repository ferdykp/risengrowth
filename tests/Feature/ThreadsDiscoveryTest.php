<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThreadsDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.threads.api_base_url',
            'https://graph.threads.net'
        );

        Cache::flush();
    }

    private function connectedAccount(User $admin): SocialAccount
    {
        $account = SocialAccount::create([
            'user_id' => $admin->id,
            'platform' => 'threads',
            'external_account_id' => '123456789',
            'username' => 'risendev',
            'status' => 'connected',
            'token_expires_at' => now()->addDays(30),
        ]);

        $account->setAccessToken('fake-token');
        $account->save();

        return $account;
    }

    private function keyword(User $admin): int
    {
        return DB::table('search_keywords')->insertGetId([
            'user_id' => $admin->id,
            'platform' => 'threads',
            'phrase' => 'butuh website',
            'normalized_phrase' => 'butuh website',
            'category' => 'direct_demand',
            'search_type' => 'RECENT',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_member_cannot_run_discovery(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post(route('discovery.run'), [
                'keyword_id' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_search_and_store_unique_leads(): void
    {
        $admin = User::factory()->admin()->create();

        $this->connectedAccount($admin);
        $keywordId = $this->keyword($admin);

        Http::preventStrayRequests();

        Http::fake([
            'graph.threads.net/keyword_search*' =>
            Http::response([
                'data' => [
                    [
                        'id' => 'post-001',
                        'text' => 'Saya butuh website untuk toko.',
                        'username' => 'calonpelanggan',
                        'permalink' =>
                        'https://www.threads.com/@calonpelanggan/post/abc',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->post(route('discovery.run', [
                'keyword_id' => $keywordId,
            ]))
            ->assertRedirect(route('discovery.index'));

        $this->assertDatabaseCount('discovered_posts', 1);
        $this->assertDatabaseCount('leads', 1);

        $this->assertDatabaseHas('discovery_runs', [
            'status' => 'completed',
            'fetched_count' => 1,
            'added_count' => 1,
        ]);

        // Pencarian ulang tidak membuat post/lead duplikat.
        $this->post(route('discovery.run'), [
            'keyword_id' => $keywordId,
        ]);

        $this->assertDatabaseCount('discovered_posts', 1);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('discovery_runs', 2);

        $this->assertDatabaseHas('discovery_runs', [
            'status' => 'completed',
            'fetched_count' => 1,
            'added_count' => 0,
        ]);
    }

    public function test_api_failure_does_not_create_leads(): void
    {
        $admin = User::factory()->admin()->create();

        $this->connectedAccount($admin);
        $keywordId = $this->keyword($admin);

        Http::preventStrayRequests();

        Http::fake([
            'graph.threads.net/keyword_search*' =>
            Http::response([
                'error' => [
                    'code' => 10,
                    'message' => 'Permission denied',
                ],
            ], 403),
        ]);

        $this->actingAs($admin)
            ->post(route('discovery.run'), [
                'keyword_id' => $keywordId,
            ])
            ->assertSessionHasErrors('discovery');

        $this->assertDatabaseCount('leads', 0);

        $this->assertDatabaseHas('discovery_runs', [
            'status' => 'failed',
        ]);
    }
}
