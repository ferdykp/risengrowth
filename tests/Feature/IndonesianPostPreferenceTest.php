<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndonesianPostPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_indonesian_text_is_prioritized_without_hiding_other_posts_and_can_be_disabled(): void
    {
        $user = User::factory()->create();
        $indonesian = 'Butuh website untuk toko saya, ada rekomendasi?';
        $english = 'Need a website for my small business.';
        foreach ([$indonesian, $english] as $index => $body) {
            $post = DiscoveredPost::create(['platform' => 'threads', 'external_post_id' => 'language-'.$index, 'body' => $body]);
            Lead::create(['discovered_post_id' => $post->id, 'status' => 'new']);
            AiAnalysis::create(['discovered_post_id' => $post->id, 'provider' => 'rules', 'model' => 'rules-v1', 'prompt_version' => 'rules-v1',
                'status' => 'completed', 'output' => ['source' => 'apify', 'status' => 'uncertain']]);
        }

        $this->actingAs($user)->get(route('leads.index'))->assertOk()->assertSeeInOrder([$indonesian, $english]);
        $this->get(route('leads.index', ['prefer_indonesian' => 0]))->assertOk()->assertSeeInOrder([$english, $indonesian]);
        $this->get(route('discovery.index'))->assertOk()->assertSee('bukan lokasi akun')->assertSeeInOrder([$indonesian, $english]);
        $this->get(route('discovery.index', ['prefer_indonesian' => 0]))->assertOk()->assertSeeInOrder([$english, $indonesian]);
        $json = $this->getJson(route('discovery.index'))->assertOk();
        $this->assertLessThan(strpos($json->json('reviews_html'), $english), strpos($json->json('reviews_html'), $indonesian));
        $this->assertDatabaseCount('discovered_posts', 2);
    }
}
