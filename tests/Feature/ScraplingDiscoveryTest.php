<?php

namespace Tests\Feature;

use App\Jobs\RunApifyDiscoveryJob;
use App\Jobs\RunPythonDiscoveryJob;
use App\Models\DiscoveredPost;
use App\Models\SearchKeyword;
use App\Models\User;
use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ScraplingDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function keyword(): SearchKeyword
    {
        return SearchKeyword::create([
            'user_id' => User::factory()->admin()->create()->id, 'platform' => 'threads',
            'phrase' => 'website', 'normalized_phrase' => 'website', 'category' => 'direct_demand',
            'search_type' => 'RECENT', 'enabled' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function threadsPost(string $id, string $username, string $body, ?string $postedAt = '2026-10-26T07:33:20+00:00'): array
    {
        return ['platform' => 'threads', 'external_post_id' => $id, 'username' => $username, 'body' => $body,
            'permalink' => 'https://www.threads.com/@'.$username.'/post/C'.$id, 'source' => 'scrapling',
            'posted_at' => $postedAt, 'keyword' => 'website', 'metrics' => ['likes' => 4]];
    }

    public function test_admin_queues_scrapling_discovery_without_apify_token(): void
    {
        Bus::fake([RunPythonDiscoveryJob::class, RunApifyDiscoveryJob::class]);
        config(['services.apify.token' => null]);
        $keyword = $this->keyword();

        $this->actingAs(User::findOrFail($keyword->user_id))
            ->postJson(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'scrapling',
                'posted_from' => '2026-10-28', 'posted_to' => '2026-11-04'])
            ->assertStatus(202)->assertJson(['status' => 'queued', 'source' => 'scrapling']);

        Bus::assertDispatched(RunPythonDiscoveryJob::class, fn ($job) => $job->keywordId === $keyword->id && $job->engine === 'scrapling'
            && $job->maxExceptions === 1 && $job->postedFrom === '2026-10-28' && $job->postedTo === '2026-11-04');
        Bus::assertNotDispatched(RunApifyDiscoveryJob::class);
    }

    public function test_rejects_reversed_or_future_scrapling_date_range_with_422(): void
    {
        Bus::fake([RunPythonDiscoveryJob::class]);
        $this->travelTo(Carbon::parse('2026-11-04 10:00', 'Asia/Jakarta'));
        $keyword = $this->keyword();
        $admin = User::findOrFail($keyword->user_id);

        $this->actingAs($admin)->postJson(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'scrapling',
            'posted_from' => '2026-11-03', 'posted_to' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('posted_to');
        $this->actingAs($admin)->postJson(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'scrapling',
            'posted_from' => '2026-11-05'])->assertUnprocessable()->assertJsonValidationErrors('posted_from');

        Bus::assertNotDispatched(RunPythonDiscoveryJob::class);
    }

    public function test_default_range_keeps_only_posts_from_last_seven_days(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00', 'Asia/Jakarta'));
        $keyword = $this->keyword();
        Process::fake(['*threads_scrapling.py*' => Process::result(output: json_encode(['posts' => [
            $this->threadsPost('201', 'fresh', 'Need a website today', '2026-11-04T01:00:00+00:00'),
            $this->threadsPost('202', 'edge', 'Need a website', '2026-10-27T17:00:00+00:00'),
            $this->threadsPost('203', 'old', 'Need a website', '2026-10-27T16:59:59+00:00'),
            $this->threadsPost('204', 'undated', 'Need a website', null),
        ], 'errors' => [], 'sources_configured' => 1]))]);
        Process::preventStrayProcesses();

        app(RunPythonDiscovery::class)->execute($keyword, 'scrapling');

        $this->assertSame(['201', '202'], DiscoveredPost::orderBy('external_post_id')->pluck('external_post_id')->all());
        $this->assertDatabaseHas('discovery_runs', ['source_type' => 'scrapling', 'status' => 'completed', 'fetched_count' => 4,
            'out_of_range_count' => 2, 'posted_from' => '2026-10-28', 'posted_to' => '2026-11-04']);
    }

    public function test_explicit_range_overrides_default(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00', 'Asia/Jakarta'));
        $keyword = $this->keyword();
        Process::fake(['*threads_scrapling.py*' => Process::result(output: json_encode(['posts' => [
            $this->threadsPost('301', 'today', 'Need a website', '2026-11-04T01:00:00+00:00'),
            $this->threadsPost('302', 'older', 'Need a website', '2026-10-01T01:00:00+00:00'),
        ], 'errors' => [], 'sources_configured' => 1]))]);
        Process::preventStrayProcesses();

        app(RunPythonDiscovery::class)->execute($keyword, 'scrapling', '2026-09-30', '2026-10-02');

        $this->assertSame(['302'], DiscoveredPost::pluck('external_post_id')->all());
        $this->assertDatabaseHas('discovery_runs', ['out_of_range_count' => 1, 'posted_from' => '2026-09-30', 'posted_to' => '2026-10-02']);
    }

    public function test_discovery_page_preselects_configured_engine(): void
    {
        $this->withoutVite();
        $admin = User::findOrFail($this->keyword()->user_id);

        $this->actingAs($admin)->get(route('discovery.index'))
            ->assertSee('<option value="apify" selected', false);

        config(['services.scraper.engine' => 'scrapling']);
        $this->actingAs($admin)->get(route('discovery.index'))
            ->assertSee('<option value="scrapling" selected', false);
    }

    public function test_scrapling_job_stores_threads_posts_excludes_own_account_and_deduplicates(): void
    {
        $this->travelTo(Carbon::parse('2026-10-27 10:00', 'Asia/Jakarta'));
        $keyword = $this->keyword();
        config(['services.apify.excluded_usernames' => ['risendev']]);
        Process::fake(['*threads_scrapling.py*' => Process::result(output: json_encode(['posts' => [
            $this->threadsPost('101', 'buyer.one', 'Looking for someone to build a website.'),
            $this->threadsPost('102', 'RisenDev', 'Need a website? We build them.'),
        ], 'errors' => [], 'sources_configured' => 1]))]);
        Process::preventStrayProcesses();

        (new RunPythonDiscoveryJob($keyword->id, 'scrapling'))->handle(app(RunPythonDiscovery::class));
        (new RunPythonDiscoveryJob($keyword->id, 'scrapling'))->handle(app(RunPythonDiscovery::class));

        $this->assertDatabaseCount('discovered_posts', 1);
        $this->assertDatabaseHas('discovered_posts', ['platform' => 'threads', 'external_post_id' => '101',
            'username' => 'buyer.one', 'permalink' => 'https://www.threads.com/@buyer.one/post/C101']);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('discovery_runs', ['source_type' => 'scrapling', 'status' => 'completed',
            'fetched_count' => 2, 'excluded_own_count' => 1]);
        $this->assertDatabaseHas('ai_analyses', ['output->source' => 'scrapling']);
        Process::assertRan(fn ($process) => $process->command === [
            config('services.risengrowth_python.bin'), base_path('scraper/threads_scrapling.py'),
            '--keyword', 'website', '--mode', 'http', '--timeout', '20', '--max-posts', '20',
        ]);
    }

    public function test_job_is_released_until_scrapling_cooldown_ends(): void
    {
        $this->freezeTime();
        $keyword = $this->keyword();
        Cache::put('scrapling-next-request-at', now()->timestamp + 40, 40);
        Process::fake();
        Process::preventStrayProcesses();

        $job = (new RunPythonDiscoveryJob($keyword->id, 'scrapling'))->withFakeQueueInteractions();
        $job->handle(app(RunPythonDiscovery::class));

        $job->assertReleased(40);
        Process::assertNothingRan();
        $this->assertDatabaseCount('discovery_runs', 0);
    }

    public function test_job_claims_next_scrapling_slot_with_jittered_gap(): void
    {
        $this->freezeTime();
        $keyword = $this->keyword();
        Process::fake(['*threads_scrapling.py*' => Process::result(output: json_encode(['posts' => [], 'errors' => [], 'sources_configured' => 1]))]);
        Process::preventStrayProcesses();

        $job = (new RunPythonDiscoveryJob($keyword->id, 'scrapling'))->withFakeQueueInteractions();
        $job->handle(app(RunPythonDiscovery::class));

        $job->assertNotReleased();
        $this->assertGreaterThanOrEqual(now()->timestamp + 60, Cache::get('scrapling-next-request-at'));
        $this->assertLessThanOrEqual(now()->timestamp + 90, Cache::get('scrapling-next-request-at'));
    }

    public function test_login_wall_fails_run_with_visible_reason(): void
    {
        $this->freezeTime();
        $keyword = $this->keyword();
        Process::fake(['*threads_scrapling.py*' => Process::result(output: json_encode(['posts' => [],
            'errors' => [['source' => 'scrapling', 'error' => 'website: LOGIN_WALL']], 'sources_configured' => 1]), exitCode: 2)]);
        Process::preventStrayProcesses();

        $this->expectException(RuntimeException::class);
        try {
            app(RunPythonDiscovery::class)->execute($keyword, 'scrapling');
        } finally {
            $this->assertDatabaseHas('discovery_runs', ['source_type' => 'scrapling', 'status' => 'failed',
                'error_summary' => 'Semua sumber gagal: website: LOGIN_WALL']);
            $this->assertSame(now()->timestamp + 900, Cache::get('scrapling-next-request-at'));
            $this->assertDatabaseCount('discovered_posts', 0);
        }
    }

    public function test_non_threads_permalink_from_worker_is_rejected(): void
    {
        $keyword = $this->keyword();
        $post = array_merge($this->threadsPost('101', 'buyer.one', 'Need a website'), ['permalink' => 'https://evil.example/@buyer.one/post/C101']);
        Process::fake(['*threads_scrapling.py*' => Process::result(output: json_encode(['posts' => [$post], 'errors' => [], 'sources_configured' => 1]))]);
        Process::preventStrayProcesses();

        try {
            app(RunPythonDiscovery::class)->execute($keyword, 'scrapling');
            $this->fail('A non-Threads permalink must fail validation.');
        } catch (ValidationException) {
        }

        $this->assertDatabaseHas('discovery_runs', ['source_type' => 'scrapling', 'status' => 'failed']);
        $this->assertDatabaseCount('discovered_posts', 0);
    }
}
