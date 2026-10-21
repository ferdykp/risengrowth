<?php

namespace Tests\Feature;

use App\Jobs\RunApifyDiscoveryJob;
use App\Models\SearchKeyword;
use App\Models\User;
use App\Services\Discovery\RunApifyDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApifyWebDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config()->set('services.apify.token', 'private-test-token');
    }

    private function keyword(User $user, string $phrase = 'website'): SearchKeyword
    {
        return SearchKeyword::create(['user_id' => $user->id, 'platform' => 'threads', 'phrase' => $phrase,
            'normalized_phrase' => $phrase, 'enabled' => true, 'search_type' => 'RECENT', 'interval_minutes' => 360]);
    }

    private function enable(bool $automatic = false): void
    {
        DB::table('discovery_provider_settings')->where('id', 1)->update(['enabled' => true, 'automation_enabled' => $automatic]);
    }

    /** @return array<string, mixed> */
    private function remote(string $status = 'SUCCEEDED'): array
    {
        return ['data' => ['id' => 'remote123', 'actId' => 'actor123', 'status' => $status,
            'defaultDatasetId' => 'dataset123', 'usageTotalUsd' => 0.015]];
    }

    /** @return array<string, mixed> */
    private function postEntry(string $id, string $body, string $username = 'customer'): array
    {
        return ['platform' => 'threads', 'item_type' => 'post', 'item_id' => $id,
            'description' => $body, 'author_username' => $username, 'url' => 'https://www.threads.net/@'.$username.'/post/Abc'.$id,
            'created_at' => '2026-10-20T12:00:00+00:00', 'mode' => 'search_latest', 'source_input' => 'website'];
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function fakeResults(array $entries, string $status = 'SUCCEEDED'): void
    {
        Http::fake([
            'api.apify.com/v2/actor-runs/remote123' => Http::response($this->remote($status)),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response($entries),
        ]);
    }

    public function test_admin_can_enqueue_from_web_with_queued_history_without_starting_actor_in_request(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        $this->enable();
        Queue::fake([RunApifyDiscoveryJob::class]);
        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $keyword->id]);

        $response->assertAccepted()->assertJsonPath('status', 'queued')->assertJsonPath('source', 'apify');
        $this->assertDatabaseHas('discovery_runs', ['id' => $response->json('run_id'), 'source_type' => 'apify', 'status' => 'queued', 'keyword_snapshot' => 'website']);
        Queue::assertPushed(RunApifyDiscoveryJob::class, fn ($job) => $job->connection === 'database');
        Http::assertNothingSent();
    }

    public function test_duplicate_clicks_reuse_the_same_queued_run(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        $this->enable();
        Queue::fake([RunApifyDiscoveryJob::class]);

        $first = app(RunApifyDiscovery::class)->enqueue($keyword->id);
        $second = app(RunApifyDiscovery::class)->enqueue($keyword->id);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('discovery_runs', 1);
        Queue::assertPushed(RunApifyDiscoveryJob::class, 2);
    }

    public function test_import_saves_only_other_accounts_and_qualified_leads_and_does_not_repeat_on_redelivery(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        Queue::fake([RunApifyDiscoveryJob::class]);
        $this->fakeResults([
            $this->postEntry('1', 'Need a website for my business'),
            $this->postEntry('2', 'My portfolio: website development'),
            $this->postEntry('3', 'Website project details'),
            $this->postEntry('4', 'Need a website', 'risencode.dev'),
        ]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id, 'remote123');

        (new RunApifyDiscoveryJob($run->id))->handle(app(RunApifyDiscovery::class));
        (new RunApifyDiscoveryJob($run->id))->handle(app(RunApifyDiscovery::class));

        $this->assertDatabaseCount('discovered_posts', 3);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('ai_analyses', 3);
        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'completed', 'fetched_count' => 4,
            'relevant_count' => 1, 'added_count' => 1, 'excluded_own_count' => 1, 'uncertain_count' => 1, 'rejected_count' => 1]);
        $this->actingAs($admin)->get(route('leads.index', ['source' => 'apify']))->assertOk()->assertSee('Need a website for my business')->assertDontSee('My portfolio');
        $this->get(route('discovery.index'))->assertOk()->assertSee('Website project details');
        $this->getJson(route('discovery.index'))->assertOk()->assertJsonStructure(['reviews_html'])->assertSee('Website project details');
        Http::assertSentCount(5);
        Queue::assertPushed(RunApifyDiscoveryJob::class, 1);
    }

    public function test_pending_actor_is_polled_with_delay_without_starting_a_second_actor(): void
    {
        $keyword = $this->keyword(User::factory()->admin()->create());
        Queue::fake([RunApifyDiscoveryJob::class]);
        $this->fakeResults([], 'RUNNING');
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id, 'remote123');

        (new RunApifyDiscoveryJob($run->id))->handle(app(RunApifyDiscovery::class));

        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'running', 'provider_poll_count' => 1]);
        Queue::assertPushed(RunApifyDiscoveryJob::class, fn ($job) => $job->delay !== null);
        Http::assertSentCount(2);
    }

    public function test_ambiguous_start_is_never_repeated_by_redelivered_job(): void
    {
        $keyword = $this->keyword(User::factory()->admin()->create());
        $this->enable();
        Queue::fake([RunApifyDiscoveryJob::class]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id);
        DB::table('discovery_runs')->where('id', $run->id)->update(['provider_attempted_at' => now()]);
        Http::fake();

        (new RunApifyDiscoveryJob($run->id))->handle(app(RunApifyDiscovery::class));

        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'failed', 'error_code' => 'START_UNCERTAIN']);
        Http::assertNothingSent();
        Queue::assertPushed(RunApifyDiscoveryJob::class, 1);
    }

    public function test_member_cannot_change_provider_or_start_discovery(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('discovery.apify.settings'), ['enabled' => 1])->assertForbidden();
        $this->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => 1])->assertForbidden();

        $this->assertDatabaseHas('discovery_provider_settings', ['id' => 1, 'enabled' => false]);
    }

    public function test_admin_can_enable_and_disable_automation_with_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('discovery.apify.settings'), ['enabled' => 1, 'automation_enabled' => 1, 'daily_runs' => 2, 'min_interval_minutes' => 360])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('discovery_provider_settings', ['id' => 1, 'enabled' => true, 'automation_enabled' => true, 'daily_runs' => 2]);
        $this->patch(route('discovery.apify.settings'), ['enabled' => 1, 'automation_enabled' => 1, 'daily_runs' => 99, 'min_interval_minutes' => 1])
            ->assertSessionHasErrors(['daily_runs', 'min_interval_minutes']);
        $this->assertDatabaseHas('discovery_provider_settings', ['id' => 1, 'daily_runs' => 2]);
    }

    public function test_automation_dispatches_due_keywords_and_respects_interval(): void
    {
        $this->freezeTime();
        $keyword = $this->keyword(User::factory()->admin()->create());
        $this->enable(true);
        Queue::fake([RunApifyDiscoveryJob::class]);

        $this->artisan('growth:apify-dispatch')->assertSuccessful();
        $this->artisan('growth:apify-dispatch')->assertSuccessful();

        $this->assertDatabaseCount('discovery_runs', 1);
        $this->assertDatabaseHas('discovery_runs', ['search_keyword_id' => $keyword->id, 'status' => 'queued']);
        Queue::assertPushed(RunApifyDiscoveryJob::class, 2);
    }

    public function test_json_status_tracks_exact_run_and_preserves_api_key_privacy(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        Queue::fake([RunApifyDiscoveryJob::class]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id, 'remote123');

        $this->actingAs($admin)->getJson(route('discovery.index', ['track_source' => 'apify', 'track_keyword' => $keyword->id, 'track_run' => $run->id, 'after_id' => $run->id]))
            ->assertOk()->assertJsonPath('tracked.id', $run->id)->assertJsonPath('tracked.status', 'queued')->assertDontSee('private-test-token');

        Queue::assertPushed(RunApifyDiscoveryJob::class, 1);
    }

    public function test_new_actor_starts_once_then_polls_and_persists_the_original_keyword(): void
    {
        $this->freezeTime();
        $keyword = $this->keyword(User::factory()->admin()->create());
        $this->enable();
        Queue::fake([RunApifyDiscoveryJob::class]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id);
        $keyword->update(['phrase' => 'edited keyword']);
        Http::fake([
            'api.apify.com/v2/users/me/limits' => Http::response(['data' => ['current' => ['monthlyUsageUsd' => 0], 'limits' => ['maxMonthlyUsageUsd' => 5]]]),
            'api.apify.com/v2/actors/openclawai~threads-scraper/runs*' => Http::response($this->remote('READY'), 201),
            'api.apify.com/v2/actor-runs/remote123' => Http::sequence()->push($this->remote('RUNNING'))->push($this->remote())->push($this->remote()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([$this->postEntry('1', 'Need a website for my business')]),
        ]);

        app(RunApifyDiscovery::class)->advance($run->id);
        app(RunApifyDiscovery::class)->advance($run->id);
        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'running', 'provider_run_id' => 'remote123']);
        $this->travel(16)->seconds();
        app(RunApifyDiscovery::class)->advance($run->id);

        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'completed', 'added_count' => 1]);
        $posts = Http::recorded(fn ($request) => $request->method() === 'POST');
        $this->assertCount(1, $posts);
        $this->assertSame(['website'], $posts->first()[0]['queries']);
    }

    public function test_quota_failure_pauses_new_runs_and_redelivery_does_not_retry(): void
    {
        $keyword = $this->keyword(User::factory()->admin()->create());
        $this->enable();
        Queue::fake([RunApifyDiscoveryJob::class]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id);
        Http::fake(['api.apify.com/v2/users/me/limits' => Http::response([], 402)]);

        app(RunApifyDiscovery::class)->advance($run->id);
        app(RunApifyDiscovery::class)->advance($run->id);

        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'failed', 'error_code' => 'QUOTA_OR_RATE_LIMIT']);
        $this->assertTrue(Cache::has('apify-probe-paused'));
        Http::assertSentCount(1);
    }

    public function test_storage_failure_rolls_back_posts_and_can_recover_using_the_same_remote_run(): void
    {
        $keyword = $this->keyword(User::factory()->admin()->create());
        Queue::fake([RunApifyDiscoveryJob::class]);
        $this->fakeResults([$this->postEntry('1', 'Need a website for my business')]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id, 'remote123');
        DB::unprepared("CREATE TRIGGER fail_completion BEFORE UPDATE ON discovery_runs WHEN NEW.status = 'completed' BEGIN SELECT RAISE(ABORT, 'storage unavailable'); END");

        app(RunApifyDiscovery::class)->advance($run->id);

        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'failed', 'provider_run_id' => 'remote123']);
        DB::unprepared('DROP TRIGGER fail_completion');
        $recovered = app(RunApifyDiscovery::class)->enqueue($keyword->id, 'remote123');
        app(RunApifyDiscovery::class)->advance($recovered->id);
        $this->assertSame($run->id, $recovered->id);
        $this->assertDatabaseCount('leads', 1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_disabled_provider_and_daily_limit_reject_new_starts_but_allow_import(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        Queue::fake([RunApifyDiscoveryJob::class]);
        $this->actingAs($admin)->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $keyword->id])
            ->assertUnprocessable()->assertJsonPath('code', 'PROVIDER_DISABLED');
        $this->enable();
        Cache::put('apify-probe-starts:'.now()->utc()->format('Y-m-d'), 1, 3600);
        $this->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $keyword->id])
            ->assertUnprocessable()->assertJsonPath('code', 'DAILY_LIMIT');
        $this->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $keyword->id, 'existing_run_id' => 'remote123'])
            ->assertAccepted();
        Http::assertNothingSent();
    }

    public function test_daily_start_limit_survives_cache_clear(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        $other = $this->keyword($admin, 'web developer');
        $this->enable();
        Queue::fake([RunApifyDiscoveryJob::class]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id);
        DB::table('discovery_runs')->where('id', $run->id)->update(['provider_attempted_at' => now(), 'provider_run_id' => 'remote123', 'status' => 'completed']);
        Cache::flush();

        $this->actingAs($admin)->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $other->id])
            ->assertUnprocessable()->assertJsonPath('code', 'DAILY_LIMIT');
        Http::assertNothingSent();
    }

    public function test_failed_wrong_keyword_import_can_be_corrected_from_web_without_starting_actor(): void
    {
        $admin = User::factory()->admin()->create();
        $wrongKeyword = $this->keyword($admin, 'butuh website');
        $correctKeyword = $this->keyword($admin);
        Queue::fake([RunApifyDiscoveryJob::class]);
        $this->fakeResults([$this->postEntry('1', 'Need a website for my business')]);
        $run = app(RunApifyDiscovery::class)->enqueue($wrongKeyword->id, 'remote123');
        app(RunApifyDiscovery::class)->advance($run->id);
        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'failed', 'error_code' => 'WRONG_QUERY']);

        $this->actingAs($admin)->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $correctKeyword->id, 'existing_run_id' => 'remote123'])
            ->assertAccepted()->assertJsonPath('run_id', $run->id);
        app(RunApifyDiscovery::class)->advance($run->id);

        $this->assertDatabaseCount('discovery_runs', 1);
        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'completed', 'search_keyword_id' => $correctKeyword->id, 'keyword_snapshot' => 'website', 'provider_run_id' => 'remote123']);
        $this->assertDatabaseCount('leads', 1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_successful_import_cannot_be_reassigned_to_a_different_keyword(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin);
        $other = $this->keyword($admin, 'butuh website');
        Queue::fake([RunApifyDiscoveryJob::class]);
        $this->fakeResults([$this->postEntry('1', 'Need a website for my business')]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id, 'remote123');
        app(RunApifyDiscovery::class)->advance($run->id);

        $this->actingAs($admin)->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $other->id, 'existing_run_id' => 'remote123'])
            ->assertUnprocessable()->assertJsonPath('code', 'RUN_ALREADY_LINKED');
        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'search_keyword_id' => $keyword->id, 'status' => 'completed']);
    }

    public function test_verified_no_results_hint_completes_web_import_with_zero_posts(): void
    {
        $admin = User::factory()->admin()->create();
        $keyword = $this->keyword($admin, 'butuh website');
        Queue::fake([RunApifyDiscoveryJob::class]);
        $this->fakeResults([['platform' => 'threads', 'item_type' => 'hint', 'item_id' => null, 'url' => null,
            'mode' => 'search_latest', 'source_input' => 'butuh website',
            'error_message' => "No results for 'butuh website' on Threads.", 'scraped_at' => '2026-10-21T03:06:22.897962+00:00']]);
        $run = app(RunApifyDiscovery::class)->enqueue($keyword->id, 'remote123');
        DB::table('discovery_runs')->where('id', $run->id)->update(['status' => 'failed', 'error_code' => 'ACTOR_OUTPUT_ERROR']);
        $this->actingAs($admin)->postJson(route('discovery.run'), ['source' => 'apify', 'keyword_id' => $keyword->id, 'existing_run_id' => 'remote123'])
            ->assertAccepted()->assertJsonPath('run_id', $run->id);
        app(RunApifyDiscovery::class)->advance($run->id);

        $this->assertDatabaseHas('discovery_runs', ['id' => $run->id, 'status' => 'completed', 'fetched_count' => 0, 'added_count' => 0, 'error_code' => null]);
        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseCount('leads', 0);
        $this->actingAs($admin)->getJson(route('discovery.index', ['track_source' => 'apify', 'track_keyword' => $keyword->id, 'track_run' => $run->id]))
            ->assertOk()->assertJsonPath('tracked.status', 'completed')->assertJsonPath('tracked.fetched_count', 0);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }
}
