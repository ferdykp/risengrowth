<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryReviewTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_forms_keep_browser_origin_even_when_backend_sees_http(): void
    {
        $user = User::factory()->create();
        $post = DiscoveredPost::create(['platform' => 'threads', 'external_post_id' => 'review-transport', 'body' => 'Butuh website untuk toko saya']);
        AiAnalysis::create(['discovered_post_id' => $post->id, 'provider' => 'rules', 'model' => 'rules-v1', 'prompt_version' => 'rules-v1', 'status' => 'completed',
            'output' => ['source' => 'apify', 'status' => 'uncertain'], 'analyzed_at' => now()]);
        config()->set('services.ollama.enabled', true);
        $action = route('discovery.posts.review', $post, false);
        $localAction = route('discovery.posts.local', $post, false);

        $this->actingAs($user)->get('http://localhost/discovery')->assertOk()
            ->assertSee('action="'.$action.'"', false)->assertSee('action="'.$localAction.'"', false)
            ->assertDontSee('action="http://localhost'.$action.'"', false);
        $response = $this->getJson('http://localhost/discovery')->assertOk();
        $this->assertStringContainsString('action="'.$action.'"', $response->json('reviews_html'));
        $this->assertStringNotContainsString('action="http://', $response->json('reviews_html'));

        $this->from('https://localhost/discovery')->patch('https://localhost'.$action, [
            'qualification' => 'qualified', 'reason' => 'Buyer request verified',
        ])->assertRedirect('https://localhost/discovery')->assertSessionHas('success');
        $this->assertDatabaseHas('leads', ['discovered_post_id' => $post->id, 'status' => 'qualified', 'manually_overridden' => true]);
    }
}
