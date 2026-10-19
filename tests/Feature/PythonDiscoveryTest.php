<?php

namespace Tests\Feature;

use App\Jobs\RunPythonDiscoveryJob;
use App\Models\SearchKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PythonDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enqueue_python_discovery_without_threads_account(): void
    {
        Bus::fake();
        $admin = User::factory()->admin()->create();
        $keyword = SearchKeyword::create([
            'user_id' => $admin->id, 'platform' => 'threads',
            'phrase' => 'website', 'normalized_phrase' => 'website',
            'category' => 'direct_demand', 'search_type' => 'RECENT',
            'enabled' => true,
        ]);
        $this->actingAs($admin)->post(route('discovery.run'), ['keyword_id' => $keyword->id, 'source' => 'python'])
            ->assertRedirect(route('discovery.index'));
        Bus::assertDispatched(RunPythonDiscoveryJob::class, fn ($job) => $job->keywordId === $keyword->id);
    }

    public function test_member_cannot_enqueue_discovery(): void
    {
        Bus::fake();
        $this->actingAs(User::factory()->create())
            ->post(route('discovery.run'), ['keyword_id' => 123])->assertForbidden();
        Bus::assertNotDispatched(RunPythonDiscoveryJob::class);
    }
}
