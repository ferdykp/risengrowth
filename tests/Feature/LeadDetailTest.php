<?php

namespace Tests\Feature;

use App\Models\User;

use App\Models\DiscoveredPost;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_detail_shows_original_post_and_unanalyzed_state(): void
    {
        $post = DiscoveredPost::create([
            'platform' => 'threads', 'external_post_id' => 'example-lead-1',
            'username' => 'calonklien', 'body' => 'Butuh website katalog produk',
        ]);
        $lead = Lead::create(['discovered_post_id' => $post->id]);

        $this->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('Butuh website katalog produk')
            ->assertSee('Belum dianalisis')
            ->assertSee('Belum ada draft komentar')
            ->assertSee('Belum ada aktivitas');
    }

    public function test_unknown_lead_returns_404(): void
    {
        $this->get(route('leads.show', 987654321))->assertNotFound();
    }

    public function test_inbox_links_to_lead_detail(): void
    {
        $post = DiscoveredPost::create([
            'platform' => 'threads', 'external_post_id' => 'example-lead-2',
            'username' => 'usahaku', 'body' => 'Perlu sistem inventory',
        ]);
        $lead = Lead::create(['discovered_post_id' => $post->id]);

        $this->get(route('leads.index'))->assertOk()->assertSee(route('leads.show', $lead));
    }
}
