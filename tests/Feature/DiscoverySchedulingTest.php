<?php

namespace Tests\Feature;

use App\Jobs\RunPythonDiscoveryJob;
use App\Models\SearchKeyword;
use App\Models\User;
use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DiscoverySchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_due_enabled_keywords_are_dispatched(): void
    {
        $this->freezeTime();
        $user = User::factory()->admin()->create();
        $due = SearchKeyword::create(['user_id' => $user->id, 'phrase' => 'website', 'normalized_phrase' => 'website', 'enabled' => true, 'interval_minutes' => 30]);
        $due->forceFill(['last_ran_at' => now()->subMinutes(31)])->save();
        $recent = SearchKeyword::create(['user_id' => $user->id, 'phrase' => 'inventory', 'normalized_phrase' => 'inventory', 'enabled' => true, 'interval_minutes' => 60]);
        $recent->forceFill(['last_ran_at' => now()->subMinutes(10)])->save();
        SearchKeyword::create(['user_id' => $user->id, 'phrase' => 'pos', 'normalized_phrase' => 'pos', 'enabled' => false]);
        Bus::fake([RunPythonDiscoveryJob::class]);

        $this->artisan('growth:scan')->assertSuccessful();

        Bus::assertDispatched(RunPythonDiscoveryJob::class, fn ($job) => $job->keywordId === $due->id);
        Bus::assertDispatchedTimes(RunPythonDiscoveryJob::class, 1);
    }

    public function test_active_keyword_lock_prevents_duplicate_worker_execution(): void
    {
        $keyword = SearchKeyword::create(['user_id' => User::factory()->admin()->create()->id, 'phrase' => 'website', 'normalized_phrase' => 'website', 'enabled' => true]);
        $lock = Cache::lock('python-discovery:'.$keyword->id, 120);
        $lock->get();
        Process::fake();
        Process::preventStrayProcesses();

        try {
            $result = app(RunPythonDiscovery::class)->execute($keyword);
        } finally {
            $lock->release();
        }

        $this->assertSame(['fetched' => 0, 'added' => 0, 'linked' => 0], $result);
        $this->assertDatabaseCount('discovery_runs', 0);
        Process::assertNothingRan();
    }
}
