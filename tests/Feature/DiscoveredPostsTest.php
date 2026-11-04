<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveredPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_renders_posts_without_optional_analysis_fields(): void
    {
        $user = User::factory()->create();
        $unanalyzedPost = DiscoveredPost::query()->create([
            'platform' => 'threads',
            'external_post_id' => 'without-analysis',
            'body' => 'Saya sedang mencari bantuan untuk toko kecil.',
        ]);
        $postWithoutLocalAi = DiscoveredPost::query()->create([
            'platform' => 'threads',
            'external_post_id' => 'without-local-ai',
            'body' => 'Butuh website katalog produk.',
        ]);

        AiAnalysis::query()->create([
            'discovered_post_id' => $postWithoutLocalAi->id,
            'provider' => 'rules',
            'model' => 'rules-v1',
            'prompt_version' => 'rules-v1',
            'status' => 'completed',
            'output' => ['source' => 'apify', 'status' => 'uncertain'],
        ]);

        $this->actingAs($user)->get(route('discovery.posts.index'))
            ->assertOk()
            ->assertSee($unanalyzedPost->body)
            ->assertSee($postWithoutLocalAi->body)
            ->assertSee('Belum dianalisis')
            ->assertDontSee('Saran AI (perlu ditinjau)');
    }
}
