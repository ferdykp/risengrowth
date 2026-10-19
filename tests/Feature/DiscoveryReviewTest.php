<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncertain_post_can_be_reviewed_and_promoted_without_losing_history(): void
    {
        $user = User::factory()->create();
        $post = DiscoveredPost::create(['platform' => 'web', 'external_post_id' => 'review', 'body' => 'Website project details']);
        AiAnalysis::create(['discovered_post_id' => $post->id, 'provider' => 'rules', 'model' => 'rules-v1', 'prompt_version' => 'rules-v1', 'status' => 'completed', 'output' => ['status' => 'uncertain', 'rationale' => 'Review needed']]);

        $this->actingAs($user)->get(route('discovery.index', ['qualification' => 'uncertain']))
            ->assertOk()->assertSee('Website project details');
        $this->patch(route('discovery.posts.review', $post), ['qualification' => 'qualified', 'reason' => 'Confirmed buyer request'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ai_analyses', 2);
        $this->assertDatabaseHas('leads', ['discovered_post_id' => $post->id, 'status' => 'qualified', 'manually_overridden' => true]);
        $this->assertModelExists($post);
    }

    public function test_guest_cannot_review_or_change_status(): void
    {
        $this->patch(route('discovery.posts.review', 123), [])->assertRedirect(route('login'));
        $this->patch(route('leads.status', 123), [])->assertRedirect(route('login'));
    }

    public function test_status_update_saves_notes_and_audit_and_filters_web_leads(): void
    {
        $user = User::factory()->create();
        $post = DiscoveredPost::create(['platform' => 'web', 'external_post_id' => 'status', 'body' => 'Need a website']);
        $lead = Lead::create(['discovered_post_id' => $post->id, 'service_category' => 'website', 'score' => 100]);

        $this->actingAs($user)->patch(route('leads.status', $lead), ['status' => 'saved', 'operator_notes' => 'Follow up tomorrow'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'saved', 'operator_notes' => 'Follow up tomorrow']);
        $this->assertDatabaseHas('activity_logs', ['lead_id' => $lead->id, 'actor_id' => $user->id, 'event' => 'lead_status_updated']);
        $this->get(route('leads.index', ['platform' => 'web', 'service_category' => 'website', 'min_score' => 90, 'status' => 'saved']))
            ->assertOk()->assertSee('Need a website');
        $this->patch(route('leads.status', $lead), ['status' => 'invalid'])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'saved']);
    }
}
