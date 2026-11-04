<?php

namespace Tests\Feature;

use App\Jobs\RunApifyDiscoveryJob;
use App\Jobs\RunPythonDiscoveryJob;
use App\Models\SearchKeyword;
use App\Models\User;
use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
    private function threadsPost(string $id, string $username, string $body): array
    {
        return ['platform' => 'threads', 'external_post_id' => $id, 'username' => $username, 'body' => $body,
            'permalink' => 'https://www.threads.com/@'.$username.'/post/C'.$id, 'source' => 'scrapling',
            'posted_at' => '2026-10-26T07:33:20+00:00', 'keyword' => 'website', 'metrics' => ['likes' => 4]];
    }

    public function test_admin_queues_scrapling_discovery_without_apify_token(): void
    {
        Bus::fake([RunPythonDiscoveryJob::class, RunApifyDiscoveryJob::class]);
        config(['services.apify.token' => null]);
        $keyword = $this->keyword();

        $this->actingAs(User::findOrFail($keyword->user_id))
            ->postJson(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'scrapling'])
            ->assertStatus(202)->assertJson(['status' => 'queued', 'source' => 'scrapling']);

        Bus::assertDispatched(RunPythonDiscoveryJob::class, fn ($job) => $job->keywordId === $keyword->id && $job->engine === 'scrapling' && $job->tries === 1);
        Bus::assertNotDispatched(RunApifyDiscoveryJob::class);
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
            'fetched_count' => 1, 'excluded_own_count' => 1]);
        $this->assertDatabaseHas('ai_analyses', ['output->source' => 'scrapling']);
        Process::assertRan(fn ($process) => $process->command === [
            config('services.risengrowth_python.bin'), base_path('scraper/threads_scrapling.py'),
            '--keyword', 'website', '--mode', 'http', '--timeout', '20', '--max-posts', '20',
        ]);
    }

    public function test_login_wall_fails_run_with_visible_reason(): void
    {
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
