<?php

namespace Tests\Feature;

use App\Models\CommentDraft;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function lead(): Lead
    {
        $post = DiscoveredPost::create([
            'platform' => 'threads',
            'external_post_id' => 'sample-'.uniqid(),
            'username' => 'pemilikusaha',
            'body' => 'Saya butuh website katalog produk yang terhubung ke WhatsApp',
        ]);

        return Lead::create(['discovered_post_id' => $post->id, 'status' => 'new']);
    }

    public function test_guest_cannot_generate_or_save_drafts(): void
    {
        $this->get(route('comments.index'))->assertRedirect(route('login'));
        $this->post(route('comments.store'), [])->assertRedirect(route('login'));
        $this->post(route('comments.generate'), [])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_save_a_draft_with_hash_and_revision(): void
    {
        $this->actingAs(User::factory()->create());
        $lead = $this->lead();
        $body = 'Bisa menggunakan website katalog sederhana yang terhubung langsung ke WhatsApp.';
        $payload = ['lead_id' => $lead->id, 'variant_key' => 'friendly', 'body' => $body];

        $this->post(route('comments.store'), $payload)->assertRedirect(route('comments.index', ['lead' => $lead->id]));
        $this->post(route('comments.store'), $payload)->assertRedirect();

        $this->assertDatabaseHas('comment_drafts', [
            'lead_id' => $lead->id,
            'revision' => 1,
            'status' => 'draft',
            'content_sha256' => hash('sha256', $body),
        ]);
        $this->assertSame(2, CommentDraft::count());
        $this->assertSame(2, CommentDraft::max('revision'));
        $this->get(route('comments.index', ['lead' => $lead->id]))->assertOk()->assertSee('Generator Komentar');
    }

    public function test_invalid_drafts_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $lead = $this->lead();
        $this->post(route('comments.store'), ['lead_id' => $lead->id, 'variant_key' => 'invalid', 'body' => 'short'])->assertSessionHasErrors(['variant_key', 'body']);
    }

    public function test_ai_generation_requires_configuration(): void
    {
        $this->actingAs(User::factory()->create());
        config()->set('services.groq.key', null);
        $lead = $this->lead();
        $this->post(route('comments.generate'), ['lead_id' => $lead->id, 'variant_key' => 'friendly'])->assertSessionHasErrors('ai');
        $this->assertDatabaseCount('comment_drafts', 0);
    }

    public function test_ai_response_creates_draft_only(): void
    {
        $this->actingAs(User::factory()->create());
        config()->set('services.groq.key', 'test-key');
        $lead = $this->lead();
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Bisa dibuat katalog produk dengan tautan langsung ke WhatsApp agar pelanggan lebih mudah memesan.']]]], 200)]);
        $this->post(route('comments.generate'), ['lead_id' => $lead->id, 'variant_key' => 'friendly'])->assertRedirect(route('comments.index', ['lead' => $lead->id]));
        $this->assertDatabaseHas('comment_drafts', ['lead_id' => $lead->id, 'status' => 'draft']);
        $this->assertDatabaseCount('comment_approvals', 0);
        $this->assertDatabaseCount('engagement_attempts', 0);
    }
}
