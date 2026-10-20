<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
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
        Http::preventStrayRequests();
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
            'graph.threads.net/keyword_search*' => Http::response([
                'data' => [
                    [
                        'id' => 'post-001',
                        'text' => 'Saya butuh website untuk toko.',
                        'username' => 'calonpelanggan',
                        'permalink' => 'https://www.threads.com/@calonpelanggan/post/abc',
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
            'graph.threads.net/keyword_search*' => Http::response([
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

    public function test_website_recent_request_persists_posts_and_shows_shared_inbox(): void
    {
        $owner = User::factory()->admin()->create();
        $admin = User::factory()->admin()->create();
        $this->connectedAccount($owner);
        $keyword = $this->keyword($owner);
        DB::table('search_keywords')->where('id', $keyword)->update(['phrase' => 'website']);
        Http::preventStrayRequests();
        Http::fake(['graph.threads.net/keyword_search*' => Http::response(['data' => [
            ['id' => 'one', 'text' => 'Website untuk toko', 'username' => 'prospek'],
            ['id' => 'one', 'text' => 'Website untuk toko'],
            ['id' => 'two', 'text' => '   '],
            ['id' => 'three', 'text' => 'Perlu katalog'],
            ['text' => 'Tanpa identitas'],
        ]])]);

        $this->actingAs($admin)->post(route('discovery.run'), ['keyword_id' => $keyword])
            ->assertRedirect(route('discovery.index'))->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['q'] === 'website'
            && $request['search_type'] === 'RECENT'
            && $request['fields'] === 'id,text,username,permalink,timestamp'
            && (int) $request['limit'] === 25);
        $this->assertDatabaseCount('discovered_posts', 3);
        $this->assertDatabaseCount('discovery_run_posts', 3);
        $this->assertDatabaseCount('leads', 2);
        $this->assertDatabaseHas('discovery_runs', [
            'status' => 'completed', 'fetched_count' => 4, 'added_count' => 2, 'relevant_count' => 0,
        ]);
        $this->get(route('leads.index'))->assertOk()->assertSee('Website untuk toko');
    }

    public function test_disabled_keyword_is_rejected_before_http(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        DB::table('search_keywords')->where('id', $keyword)->update(['enabled' => false]);
        Http::fake();

        $this->actingAs($admin)->post(route('discovery.run'), ['keyword_id' => $keyword])
            ->assertSessionHasErrors('keyword_id');

        Http::assertNothingSent();
        $this->assertDatabaseCount('discovery_runs', 0);
    }

    public function test_missing_account_is_rejected_before_http(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        Http::fake();

        $this->actingAs($admin)->post(route('discovery.run'), ['keyword_id' => $keyword])
            ->assertSessionHasErrors('keyword_id');

        Http::assertNothingSent();
        $this->assertDatabaseCount('discovery_runs', 0);
    }

    public function test_empty_search_completes_without_fabricating_leads(): void
    {
        $admin = User::factory()->admin()->create();
        $this->connectedAccount($admin);
        $keyword = $this->keyword($admin);
        Http::fake(['graph.threads.net/keyword_search*' => Http::response(['data' => []])]);

        $this->actingAs($admin)->post(route('discovery.run'), ['keyword_id' => $keyword])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        Http::assertSentCount(1);
        $this->assertDatabaseHas('discovery_runs', [
            'status' => 'completed', 'fetched_count' => 0, 'added_count' => 0,
        ]);
        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    #[TestWith(['leads', 'INSERT'])]
    #[TestWith(['discovery_runs', 'UPDATE'])]
    public function test_database_failure_rolls_back_data_and_reports_failure(string $table, string $operation): void
    {
        $admin = User::factory()->admin()->create();
        $account = $this->connectedAccount($admin);
        $keyword = $this->keyword($admin);
        Http::fake(['graph.threads.net/keyword_search*' => Http::response(['data' => [
            ['id' => 'post-rollback', 'text' => 'Perlu website'],
        ]])]);
        $condition = $table === 'discovery_runs' ? "WHEN NEW.status = 'completed'" : '';
        DB::unprepared("CREATE TRIGGER fail_discovery BEFORE {$operation} ON {$table} {$condition}
            BEGIN SELECT RAISE(ABORT, 'forced database failure'); END");

        try {
            $this->actingAs($admin)->post(route('discovery.run'), ['keyword_id' => $keyword])
                ->assertSessionHasErrors('discovery')->assertSessionMissing('success');

            Http::assertSentCount(1);
            $this->assertDatabaseCount('discovered_posts', 0);
            $this->assertDatabaseCount('discovery_run_posts', 0);
            $this->assertDatabaseCount('leads', 0);
            $this->assertDatabaseHas('search_keywords', ['id' => $keyword, 'last_ran_at' => null]);
            $this->assertDatabaseHas('discovery_runs', [
                'status' => 'failed', 'error_code' => 'DISCOVERY_STORAGE_FAILED',
                'fetched_count' => 1, 'added_count' => 0,
            ]);
            $lock = Cache::lock("threads-discovery:{$account->id}:{$keyword}", 90);
            $this->assertTrue($lock->get());
            $lock->release();
        } finally {
            DB::unprepared('DROP TRIGGER fail_discovery');
        }
    }

    #[TestWith([429, ['error' => ['code' => 4]], 'THREADS_RATE_LIMITED'])]
    #[TestWith([200, ['unexpected' => []], 'THREADS_SEARCH_FAILED'])]
    #[TestWith([200, ['data' => ['id' => 'wrong-shape']], 'THREADS_SEARCH_FAILED'])]
    public function test_rate_limit_or_invalid_response_is_not_success(int $status, array $body, string $errorCode): void
    {
        $admin = User::factory()->admin()->create();
        $this->connectedAccount($admin);
        $keyword = $this->keyword($admin);
        Http::fake(['graph.threads.net/keyword_search*' => Http::response($body, $status)]);

        $this->actingAs($admin)->post(route('discovery.run'), ['keyword_id' => $keyword])
            ->assertSessionHasErrors('discovery')->assertSessionMissing('success');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseHas('discovery_runs', [
            'status' => 'failed', 'error_code' => $errorCode,
        ]);
    }
}
