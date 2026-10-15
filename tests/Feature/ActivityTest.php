<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CommentApproval;
use App\Models\CommentDraft;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
    }

    public function test_empty_activity_page_renders_and_filters_are_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('activity.index'))
            ->assertOk()->assertSee('Aktivitas & Engagement', false)
            ->assertSee('Belum ada keputusan review.')
            ->assertSee('Belum ada percobaan engagement tercatat.');
        $this->actingAs($user)->get(route('activity.index', ['tab' => 'unknown-tab']))->assertSessionHasErrors('tab');
    }

    public function test_approval_history_is_scoped_to_reviewer(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = DiscoveredPost::create(['platform'=>'threads', 'external_post_id'=>'activity-01', 'username'=>'owner_test', 'body'=>'Perlu website.']);
        $lead = Lead::create(['discovered_post_id'=>$post->id]);
        $draft = CommentDraft::create(['lead_id'=>$lead->id, 'author_id'=>$owner->id, 'variant_key'=>'friendly', 'revision'=>1, 'body'=>'Boleh cerita kebutuhan website?', 'content_sha256'=>hash('sha256', 'Boleh cerita kebutuhan website?'), 'status'=>'approved']);
        CommentApproval::create(['comment_draft_id'=>$draft->id, 'reviewer_id'=>$owner->id, 'decision'=>'approved', 'approved_sha256'=>$draft->content_sha256, 'approved_target_post_id'=>$post->external_post_id, 'approved_platform'=>'threads', 'approved_body_snapshot'=>$draft->body, 'decided_at'=>now()]);
        ActivityLog::create(['actor_id'=>$owner->id, 'lead_id'=>$lead->id, 'event'=>'comment_reviewed']);

        $this->actingAs($owner)->get(route('activity.index', ['tab'=>'approvals']))->assertOk()->assertSee('owner_test');
        $this->actingAs($other)->get(route('activity.index', ['tab'=>'approvals']))->assertOk()->assertDontSee('owner_test');
        $this->actingAs($other)->get(route('activity.index', ['tab'=>'events']))->assertDontSee('comment reviewed');
        $this->assertDatabaseCount('engagement_attempts', 0);
    }
}
