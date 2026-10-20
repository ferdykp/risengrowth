<?php

namespace Tests\Feature;

use App\Jobs\RunPythonDiscoveryJob;
use App\Models\SearchKeyword;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscoveryLiveStatusTest extends TestCase
{
    use RefreshDatabase;

    private function keyword(User $user): SearchKeyword
    {
        return SearchKeyword::create(['user_id' => $user->id, 'platform' => 'threads', 'phrase' => 'butuh website',
            'normalized_phrase' => 'butuh website', 'enabled' => true, 'search_type' => 'RECENT']);
    }

    private function account(User $user): void
    {
        $account = SocialAccount::create(['user_id' => $user->id, 'platform' => 'threads', 'external_account_id' => 'live-test',
            'status' => 'connected', 'token_expires_at' => now()->addDay()]);
        $account->setAccessToken('private-test-token');
        $account->save();
    }

    public function test_python_enqueue_returns_tracking_cursor_without_claiming_completion(): void
    {
        $user = User::factory()->admin()->create();
        $keyword = $this->keyword($user);
        Bus::fake([RunPythonDiscoveryJob::class]);

        $this->actingAs($user)->postJson(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'python'])
            ->assertAccepted()->assertJson(['status' => 'queued', 'source' => 'python', 'keyword_id' => $keyword->id, 'after_id' => 0]);

        Bus::assertDispatched(RunPythonDiscoveryJob::class, fn ($job) => $job->keywordId === $keyword->id);
        $this->assertDatabaseCount('discovery_runs', 0);
    }

    public function test_threads_empty_response_reports_completed_and_zero_without_enqueuing_python(): void
    {
        $user = User::factory()->admin()->create();
        $keyword = $this->keyword($user);
        $this->account($user);
        Bus::fake([RunPythonDiscoveryJob::class]);
        Http::preventStrayRequests();
        Http::fake(['graph.threads.net/keyword_search*' => Http::response(['data' => []])]);

        $this->actingAs($user)->postJson(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'threads'])
            ->assertOk()->assertJson(['status' => 'completed', 'source' => 'threads', 'fetched' => 0, 'added' => 0]);

        Bus::assertNotDispatched(RunPythonDiscoveryJob::class);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('discovery_runs', ['source_type' => 'threads', 'status' => 'completed', 'fetched_count' => 0]);
    }

    public function test_permission_failure_is_distinct_from_empty_results_and_excludes_provider_secrets(): void
    {
        $user = User::factory()->admin()->create();
        $keyword = $this->keyword($user);
        $this->account($user);
        Http::preventStrayRequests();
        Http::fake(['graph.threads.net/keyword_search*' => Http::response(['error' => ['code' => 10, 'message' => 'provider-secret']], 403)]);

        $this->actingAs($user)->postJson(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'threads'])
            ->assertStatus(502)->assertJsonFragment(['message' => 'Threads menolak akses pencarian. Periksa izin threads_keyword_search dan persetujuan aplikasi di Meta.'])
            ->assertDontSee('provider-secret')->assertDontSee('private-test-token');

        $this->assertDatabaseHas('discovery_runs', ['status' => 'failed', 'error_code' => 'THREADS_PERMISSION_DENIED']);
        $this->assertDatabaseCount('leads', 0);
        Http::assertSentCount(1);
    }

    public function test_poll_tracks_requested_source_even_when_history_filters_hide_it(): void
    {
        $user = User::factory()->create();
        $keyword = $this->keyword($user);
        $runId = DB::table('discovery_runs')->insertGetId(['search_keyword_id' => $keyword->id, 'source_type' => 'python',
            'run_uuid' => (string) Str::uuid(), 'status' => 'running', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->getJson(route('discovery.index', ['status' => 'completed', 'track_keyword' => $keyword->id, 'track_source' => 'python', 'after_id' => 0]))
            ->assertOk()->assertJsonPath('tracked.id', $runId)->assertJsonPath('tracked.status', 'running')
            ->assertJsonPath('metrics.totalRuns', 1);
        $this->getJson(route('discovery.index', ['track_keyword' => $keyword->id, 'track_source' => 'threads', 'after_id' => 0]))
            ->assertOk()->assertJsonPath('tracked', null);
    }

    public function test_poll_requires_login_and_valid_tracking_parameters(): void
    {
        $this->getJson(route('discovery.index'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('discovery.index', ['track_source' => 'unknown', 'after_id' => -1]))
            ->assertUnprocessable()->assertJsonValidationErrors(['track_source', 'after_id']);
    }
}
