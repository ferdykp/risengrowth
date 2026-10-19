<?php

namespace Tests\Feature;

use App\Jobs\RunPythonDiscoveryJob;
use App\Models\DiscoveredPost;
use App\Models\SearchKeyword;
use App\Models\User;
use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;
use Throwable;

class PythonDiscoveryPipelineTest extends TestCase
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
    private function workerPost(string $id, string $body): array
    {
        return ['external_post_id' => $id, 'body' => $body, 'permalink' => 'https://example.org/posts/'.$id,
            'source' => 'fixture', 'username' => 'customer', 'posted_at' => '2026-10-17T12:00:00+00:00'];
    }

    public function test_job_qualifies_persists_and_deduplicates_posts_for_the_inbox(): void
    {
        $keyword = $this->keyword();
        $posts = [
            $this->workerPost('buyer', 'Looking for someone to build a website.'),
            $this->workerPost('seller', 'My portfolio: website development.'),
            $this->workerPost('uncertain', 'Website project details.'),
        ];
        Process::fake(['*main.py*' => Process::result(output: json_encode(['posts' => $posts, 'errors' => [], 'sources_configured' => 1]))]);
        Process::preventStrayProcesses();

        (new RunPythonDiscoveryJob($keyword->id))->handle(app(RunPythonDiscovery::class));
        (new RunPythonDiscoveryJob($keyword->id))->handle(app(RunPythonDiscovery::class));

        $this->assertDatabaseCount('discovered_posts', 3);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('discovery_run_posts', 6);
        $this->assertDatabaseHas('leads', ['intent' => 'buyer_request', 'service_category' => 'website', 'score' => 100]);
        $this->assertDatabaseHas('discovery_runs', ['status' => 'completed', 'relevant_count' => 1, 'added_count' => 0]);
        $this->assertDatabaseHas('ai_analyses', ['provider' => 'rules', 'score' => 0]);
        $this->actingAs(User::findOrFail($keyword->user_id))->get(route('leads.index'))
            ->assertOk()->assertSee('Looking for someone')->assertDontSee('My portfolio');
        Process::assertRanTimes(fn ($process) => is_array($process->command) && $process->command[1] === base_path('scraper/main.py'), 2);
    }

    #[TestWith(['{"posts":"invalid","errors":[]}', 0])]
    #[TestWith(['{"posts":[],"errors":[],"sources_configured":0}', 0])]
    #[TestWith(['not json', 1])]
    #[TestWith(['{"posts":[],"errors":[{"source":"fixture","error":"timeout"}],"sources_configured":1}', 2])]
    public function test_invalid_output_or_all_source_failure_records_failure_without_posts(string $output, int $exitCode): void
    {
        $keyword = $this->keyword();
        Process::fake(['*main.py*' => Process::result(output: $output, exitCode: $exitCode)]);
        Process::preventStrayProcesses();

        try {
            app(RunPythonDiscovery::class)->execute($keyword);
            $this->fail('Invalid worker output must fail discovery.');
        } catch (Throwable $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertDatabaseHas('discovery_runs', ['status' => 'failed', 'error_code' => 'PYTHON_WORKER_FAILED']);
        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseHas('search_keywords', ['id' => $keyword->id, 'last_ran_at' => null]);
        Process::assertRanTimes(fn ($process) => is_array($process->command) && $process->command[1] === base_path('scraper/main.py'), 1);
    }

    public function test_source_failure_preserves_successful_source_results(): void
    {
        $keyword = $this->keyword();
        Process::fake(['*main.py*' => Process::result(output: json_encode([
            'posts' => [$this->workerPost('partial', 'Need a website for my business')],
            'errors' => [['source' => 'unavailable', 'error' => 'timeout']], 'sources_configured' => 2,
        ]), exitCode: 2)]);
        Process::preventStrayProcesses();

        app(RunPythonDiscovery::class)->execute($keyword);

        $this->assertDatabaseHas('discovery_runs', ['status' => 'partial', 'added_count' => 1]);
        $this->assertDatabaseCount('leads', 1);
        Process::assertRanTimes(fn ($process) => is_array($process->command) && $process->command[1] === base_path('scraper/main.py'), 1);
    }

    public function test_real_python_worker_fixture_reaches_the_lead_inbox(): void
    {
        $keyword = $this->keyword();
        $python = base_path('scraper/.venv/bin/python');
        if (! is_executable($python)) {
            $this->markTestSkipped('Install scraper/requirements.txt in scraper/.venv to run the real Python smoke test.');
        }
        config()->set('services.risengrowth_python.bin', $python);
        config()->set('services.risengrowth_python.script', base_path('tests/Fixtures/discovery_worker.py'));

        (new RunPythonDiscoveryJob($keyword->id))->handle(app(RunPythonDiscovery::class));

        $this->assertDatabaseCount('discovered_posts', 3);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('discovery_runs', ['status' => 'completed', 'fetched_count' => 3, 'relevant_count' => 1]);
        $this->actingAs(User::findOrFail($keyword->user_id))->get(route('leads.index'))
            ->assertOk()->assertSee('Looking for someone')->assertDontSee('My portfolio');
    }

    public function test_repeated_discovery_preserves_manual_rejection(): void
    {
        $keyword = $this->keyword();
        Process::fake(['*main.py*' => Process::result(output: json_encode([
            'posts' => [$this->workerPost('manual', 'Need a website for my business')],
            'errors' => [], 'sources_configured' => 1,
        ]))]);
        Process::preventStrayProcesses();
        app(RunPythonDiscovery::class)->execute($keyword);
        $post = DiscoveredPost::firstOrFail();
        $this->actingAs(User::findOrFail($keyword->user_id))->patch(route('discovery.posts.review', $post), [
            'qualification' => 'rejected', 'reason' => 'Already handled by our team',
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(RunPythonDiscovery::class)->execute($keyword);

        $this->assertDatabaseHas('leads', ['discovered_post_id' => $post->id, 'status' => 'dismissed', 'manually_overridden' => true]);
        $this->get(route('discovery.index', ['qualification' => 'rejected']))
            ->assertOk()->assertSee('Already handled by our team');
        Process::assertRanTimes(fn ($process) => is_array($process->command) && $process->command[1] === base_path('scraper/main.py'), 2);
    }
}
