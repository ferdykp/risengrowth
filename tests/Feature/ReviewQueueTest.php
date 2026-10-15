<?php

namespace Tests\Feature;

use App\Models\CommentDraft;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function draftFor(User $user): CommentDraft
    {
        $post = DiscoveredPost::query()->create([
            'platform' => 'threads', 'external_post_id' => 'post-'.uniqid(),
            'username' => 'owner', 'body' => 'Sedang mencari web developer untuk bisnis saya.',
        ]);
        $lead = Lead::query()->create(['discovered_post_id' => $post->id]);
        $body = 'Boleh cerita sedikit kebutuhan website usahanya?';
        return CommentDraft::query()->create([
            'lead_id' => $lead->id, 'author_id' => $user->id,
            'variant_key' => 'friendly', 'revision' => 1, 'body' => $body,
            'content_sha256' => hash('sha256', $body), 'status' => 'draft',
        ]);
    }

    public function test_login_required(): void
    {
        $this->get(route('reviews.index'))->assertRedirect(route('login'));
    }

    public function test_review_status_and_snapshot_are_saved_without_publishing(): void
    {
        $user = User::factory()->create();
        $draft = $this->draftFor($user);
        $this->actingAs($user)->get(route('reviews.index'))->assertOk()->assertSee('Antrean Review');
        $this->actingAs($user)->post(route('reviews.submit', $draft))->assertRedirect();
        $this->assertDatabaseHas('comment_drafts', ['id' => $draft->id, 'status' => 'pending_review']);
        $this->actingAs($user)->post(route('reviews.decide', $draft), ['decision' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('comment_approvals', [
            'comment_draft_id' => $draft->id, 'decision' => 'approved',
            'approved_sha256' => hash('sha256', $draft->body),
            'approved_target_post_id' => $draft->lead->post->external_post_id,
        ]);
        $this->assertDatabaseCount('engagement_attempts', 0);
        $this->actingAs($user)->post(route('reviews.decide', $draft), ['decision' => 'approved'])->assertStatus(409);
        $this->assertDatabaseCount('comment_approvals', 1);
        $this->actingAs($user)->patch(route('reviews.update', $draft), ['body' => 'This is a changed message.'])->assertStatus(409);
    }

    public function test_edit_resets_pending_review_and_blocks_other_users(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $draft = $this->draftFor($author);
        $this->actingAs($other)->post(route('reviews.submit', $draft))->assertNotFound();
        $this->actingAs($author)->post(route('reviews.submit', $draft))->assertRedirect();
        $body = 'Boleh dijelaskan fitur yang ingin digunakan?';
        $this->actingAs($author)->patch(route('reviews.update', $draft), ['body' => $body])->assertRedirect();
        $this->assertDatabaseHas('comment_drafts', [
            'id' => $draft->id, 'status' => 'draft', 'content_sha256' => hash('sha256', $body),
        ]);
        $this->actingAs($other)->post(route('reviews.decide', $draft), ['decision' => 'rejected'])->assertNotFound();
    }
}
