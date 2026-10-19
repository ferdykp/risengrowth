<?php

namespace Tests\Feature;

use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\User;
use App\Services\Discovery\LocalAiAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalAiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_structured_local_qualification_is_returned(): void
    {
        config()->set(['services.ollama.enabled' => true, 'services.ollama.model' => 'test-model']);
        Http::preventStrayRequests();
        $answer = ['buyer_intent' => true, 'service_category' => 'website', 'confidence' => 85, 'reason' => 'Author requests a website', 'follow_up' => 'Ask about the scope'];
        Http::fake(['127.0.0.1:11434/api/chat' => Http::response(['done' => true, 'message' => ['content' => json_encode($answer)]])]);

        $result = app(LocalAiAssistant::class)->qualify('Need a website');

        $this->assertSame($answer, $result);
        Http::assertSent(fn ($request) => $request['stream'] === false && $request['format']['type'] === 'object');
    }

    public function test_local_timeout_or_invalid_response_returns_no_override(): void
    {
        config()->set(['services.ollama.enabled' => true, 'services.ollama.model' => 'test-model']);
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:11434/api/chat' => Http::sequence()
            ->push(['done' => true, 'message' => ['content' => '{"buyer_intent":"yes","confidence":999}']])
            ->pushResponse(Http::failedConnection())]);

        $this->assertNull(app(LocalAiAssistant::class)->qualify('Need a website'));
        $this->assertNull(app(LocalAiAssistant::class)->qualify('Need a website'));
        Http::assertSentCount(2);
    }

    public function test_local_draft_failure_falls_back_to_free_english_template(): void
    {
        config()->set(['services.ollama.enabled' => true, 'services.ollama.model' => 'test-model', 'services.groq.key' => null]);
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:11434/api/chat' => Http::response([], 503)]);
        $post = DiscoveredPost::create(['platform' => 'web', 'external_post_id' => 'draft-local', 'body' => 'Need a website']);
        $lead = Lead::create(['discovered_post_id' => $post->id]);

        $this->actingAs(User::factory()->create())->post(route('comments.generate'), [
            'lead_id' => $lead->id, 'variant_key' => 'friendly', 'provider' => 'ollama', 'language' => 'en',
        ])->assertRedirect(route('comments.index', ['lead' => $lead->id]));

        $this->assertDatabaseHas('comment_drafts', ['lead_id' => $lead->id, 'status' => 'draft',
            'body' => 'Hi! If you are still looking for help with your website, RisenDev can discuss the requirements. What is the main workflow you need to support?']);
        $this->assertDatabaseCount('engagement_attempts', 0);
        Http::assertSentCount(1);
    }

    public function test_disabled_ai_makes_no_request(): void
    {
        config()->set('services.ollama.enabled', false);
        Http::preventStrayRequests();
        Http::fake();

        $this->assertNull(app(LocalAiAssistant::class)->qualify('Need a website'));
        Http::assertNothingSent();
    }
}
