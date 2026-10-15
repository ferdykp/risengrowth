<?php

namespace Tests\Feature;

use App\Models\User;

use App\Models\DiscoveredPost;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_empty_inbox_has_real_empty_state(): void
    {
        $this->get(route('leads.index'))->assertOk()->assertSee('Belum ada prospek ditemukan');
    }

    public function test_search_priority_and_sort_use_database_data(): void
    {
        $postA = DiscoveredPost::create(['platform' => 'threads', 'external_post_id' => 'p-a', 'username' => 'butuhweb', 'body' => 'Butuh website katalog']);
        $postB = DiscoveredPost::create(['platform' => 'instagram', 'external_post_id' => 'p-b', 'username' => 'belajarcode', 'body' => 'Belajar Laravel']);
        Lead::create(['discovered_post_id' => $postA->id, 'score' => 95, 'priority' => 'high']);
        Lead::create(['discovered_post_id' => $postB->id, 'score' => 18, 'priority' => 'low']);

        $this->get(route('leads.index', ['q' => 'katalog', 'priority' => 'high', 'sort' => 'score_high']))
            ->assertOk()->assertSee('butuhweb')->assertDontSee('belajarcode');
        $this->get(route('leads.index', ['platform' => 'instagram']))
            ->assertOk()->assertSee('belajarcode')->assertDontSee('butuhweb');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->get(route('leads.index', ['sort' => 'DROP TABLE leads']))->assertSessionHasErrors('sort');
    }
}
