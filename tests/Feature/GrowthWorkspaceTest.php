<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\CommentDraft;
use App\Models\ContentDraft;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\Relationship;
use App\Models\User;
use App\Services\Discovery\QualifyDiscoveredPost;
use App\Services\Discovery\RecordOpportunityAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GrowthWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function lead(string $identity = 'workspace'): Lead
    {
        $post = DiscoveredPost::create(['platform' => 'threads', 'external_post_id' => $identity,
            'username' => $identity, 'body' => 'Butuh website untuk usaha kecil.', 'first_discovered_at' => now()]);

        return Lead::create(['discovered_post_id' => $post->id, 'status' => 'new']);
    }

    /** @return array<string, mixed> */
    private function content(): array
    {
        return ['topic' => 'Proses inventory', 'pillar' => 'behind_the_build', 'platform' => 'threads', 'body' => 'Bagaimana memetakan alur stok sebelum membuat sistem.', 'status' => 'draft'];
    }

    public function test_guests_cannot_use_workspace_or_mutations(): void
    {
        $this->get(route('relationships.index'))->assertRedirect(route('login'));
        $this->get(route('studio.index'))->assertRedirect(route('login'));
        $this->post(route('studio.store'), $this->content())->assertRedirect(route('login'));
        $this->post(route('discovery.capture'), [])->assertRedirect(route('login'));
        $this->put(route('relationships.update', 1), [])->assertRedirect(route('login'));
    }

    public function test_new_modules_render_empty_states_and_navigation(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertOk()->assertSee('Today’s actions')->assertSee('Tidak ada follow-up jatuh tempo.');
        $this->get(route('relationships.index'))->assertOk()->assertSee('Belum ada relasi yang cocok.');
        $this->get(route('studio.index'))->assertOk()->assertSee('Belum ada konten yang cocok.');
        $this->get(route('discovery.index'))->assertOk()->assertSee('Capture postingan manual');
    }

    public function test_private_drafts_do_not_leak_from_any_workspace_view(): void
    {
        $owner = User::factory()->create();
        $lead = $this->lead();
        CommentDraft::create(['lead_id' => $lead->id, 'author_id' => $owner->id, 'variant_key' => 'friendly',
            'body' => 'PRIVATE_DRAFT_CONTENT', 'content_sha256' => hash('sha256', 'PRIVATE_DRAFT_CONTENT'), 'status' => 'pending_review']);
        $this->actingAs(User::factory()->create());

        foreach (['/', route('leads.show', $lead), route('comments.index'), route('comments.index', ['lead' => $lead->id])] as $url) {
            $this->get($url)->assertOk()->assertDontSee('PRIVATE_DRAFT_CONTENT');
        }
        $this->actingAs($owner)->get('/')->assertOk()->assertSee('PRIVATE_DRAFT_CONTENT');
    }

    public function test_relationship_is_idempotent_private_and_has_interaction_history(): void
    {
        $owner = User::factory()->create();
        $lead = $this->lead();
        $data = ['category' => 'developer', 'stage' => 'engaged', 'confirm_interaction' => '1',
            'follow_up_on' => '2026-11-02', 'notes' => 'PRIVATE_RELATIONSHIP_NOTE', 'interaction' => 'Sudah membalas diskusi inventory.'];
        $this->actingAs($owner)->put(route('relationships.update', $lead), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('relationships.update', $lead), $data)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('relationships', 1);
        $this->assertDatabaseHas('relationships', ['user_id' => $owner->id, 'lead_id' => $lead->id, 'stage' => 'engaged']);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $owner->id, 'lead_id' => $lead->id, 'event' => 'relationship_updated']);
        $this->get(route('leads.show', $lead))->assertOk()->assertSee('Sudah membalas diskusi inventory.');
        $this->actingAs(User::factory()->create())->get(route('leads.show', $lead))->assertOk()->assertDontSee('PRIVATE_RELATIONSHIP_NOTE')->assertDontSee('Sudah membalas diskusi inventory.');
        $this->get(route('relationships.index'))->assertOk()->assertDontSee('PRIVATE_RELATIONSHIP_NOTE');
        $this->get('/')->assertOk()->assertDontSee('relationship updated');
    }

    #[TestWith(['engaged'])]
    #[TestWith(['connected'])]
    #[TestWith(['opportunity'])]
    #[TestWith(['won'])]
    #[TestWith(['lost'])]
    public function test_relationship_decisions_need_human_confirmation(string $stage): void
    {
        $lead = $this->lead();
        $this->actingAs(User::factory()->create())->put(route('relationships.update', $lead), ['category' => 'community', 'stage' => $stage])
            ->assertSessionHasErrors('confirm_interaction');

        $this->assertDatabaseCount('relationships', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('engagement_attempts', 0);
    }

    public function test_invalid_relationship_does_not_change_existing_data(): void
    {
        $owner = User::factory()->create();
        $lead = $this->lead();
        Relationship::create(['user_id' => $owner->id, 'lead_id' => $lead->id, 'category' => 'community', 'stage' => 'reviewed']);

        $this->actingAs($owner)->put(route('relationships.update', $lead), ['category' => 'enterprise', 'stage' => 'unknown', 'follow_up_on' => 'not-a-date'])
            ->assertSessionHasErrors(['category', 'stage', 'follow_up_on']);

        $this->assertDatabaseHas('relationships', ['user_id' => $owner->id, 'stage' => 'reviewed']);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_today_uses_jakarta_date_and_excludes_closed_or_other_users_followups(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31T18:00:00Z'));
        $owner = User::factory()->create();
        foreach ([['due_today', '2026-11-01', 'engaged'], ['overdue', '2026-10-30', 'reviewed'], ['future', '2026-11-02', 'reviewed'], ['closed', '2026-11-01', 'won']] as [$name, $date, $stage]) {
            Relationship::create(['user_id' => $owner->id, 'lead_id' => $this->lead($name)->id, 'follow_up_on' => $date, 'stage' => $stage]);
        }
        Relationship::create(['user_id' => User::factory()->create()->id, 'lead_id' => $this->lead('other_owner')->id, 'follow_up_on' => '2026-11-01']);

        $this->actingAs($owner)->get(route('relationships.index', ['due' => 1]))->assertOk()->assertSee('due_today')->assertSee('overdue')->assertDontSee('future')->assertDontSee('closed')->assertDontSee('other_owner');
        $response = $this->get('/')->assertOk();
        $this->assertCount(2, $response->viewData('followUps'));
    }

    public function test_content_lifecycle_and_manual_performance_are_saved_privately(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('studio.store'), $this->content())->assertRedirect()->assertSessionHasNoErrors();
        $draft = ContentDraft::sole();
        $this->put(route('studio.update', $draft), array_replace($this->content(), ['status' => 'ready']))->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('studio.update', $draft), array_replace($this->content(), ['status' => 'published', 'confirm_published' => 1, 'published_on' => now('Asia/Jakarta')->toDateString(), 'impressions' => 120, 'engagements' => 7]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('content_drafts', ['id' => $draft->id, 'status' => 'published', 'impressions' => 120, 'engagements' => 7]);
        $this->get(route('studio.index', ['status' => 'published']))->assertOk()->assertSee('Proses inventory');
        $this->actingAs(User::factory()->create())->get(route('studio.index'))->assertOk()->assertDontSee('Proses inventory');
        $this->put(route('studio.update', $draft), $this->content())->assertNotFound();
        $this->assertDatabaseHas('content_drafts', ['id' => $draft->id, 'status' => 'published']);
    }

    public function test_published_status_requires_confirmation_and_real_date(): void
    {
        $this->actingAs(User::factory()->create())->post(route('studio.store'), array_replace($this->content(), ['status' => 'published']))
            ->assertSessionHasErrors(['confirm_published', 'published_on']);
        $this->post(route('studio.store'), array_replace($this->content(), ['status' => 'published', 'confirm_published' => 1, 'published_on' => now('Asia/Jakarta')->addDay()->toDateString(), 'impressions' => -1]))
            ->assertSessionHasErrors(['published_on', 'impressions']);

        $this->assertDatabaseCount('content_drafts', 0);
    }

    public function test_capture_deduplicates_without_fetching_url_or_replacing_review(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $payload = ['platform' => 'threads', 'url' => 'https://www.threads.com/@dev/post/abc', 'username' => 'dev', 'body' => 'Butuh website untuk bisnis.', 'keyword' => 'website'];
        $this->post(route('discovery.capture'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $this->patch(route('discovery.posts.review', $lead->post), ['qualification' => 'qualified', 'category' => 'community', 'reason' => 'Networking saja.'])->assertRedirect();
        $this->post(route('discovery.capture'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('discovered_posts', 1);
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame('community', $lead->post->analyses()->first()->output['category']);
        Http::assertNothingSent();
    }

    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['https://user:password@example.com/post'])]
    #[TestWith(['https://example.com:444/post'])]
    public function test_capture_rejects_unsafe_reference_urls(string $url): void
    {
        $this->actingAs(User::factory()->create())->post(route('discovery.capture'), ['platform' => 'web', 'url' => $url, 'body' => 'Website discussion here.', 'keyword' => 'website'])
            ->assertSessionHasErrors('url');

        $this->assertDatabaseCount('discovered_posts', 0);
    }

    public function test_rule_metadata_and_manual_category_survive_provider_reprocessing(): void
    {
        $lead = $this->lead();
        $keyword = DB::table('search_keywords')->insertGetId(['user_id' => User::factory()->create()->id, 'platform' => 'threads', 'phrase' => 'website', 'normalized_phrase' => 'website', 'category' => 'developer_community', 'search_type' => 'RECENT', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        AiAnalysis::create(['discovered_post_id' => $lead->post->id, 'provider' => 'manual', 'model' => 'human', 'prompt_version' => 'manual-v1', 'status' => 'completed', 'output' => ['status' => 'qualified', 'category' => 'community', 'rationale' => 'Confirmed networking.', 'reviewer_id' => 1, 'review_status' => 'reviewed']]);

        $result = app(RecordOpportunityAssessment::class)->record($lead->post, 'website', 'python-source', $keyword, 42);

        $this->assertSame('community', $result['category']);
        $this->assertSame('Confirmed networking.', $result['rationale']);
        $this->assertSame('reviewed', $result['review_status']);
        $this->assertSame('python-source', $result['source']);
        $this->assertSame(QualifyDiscoveredPost::VERSION, $result['rule_version']);
        $this->assertArrayHasKey('evidence', $result['rule_assessment']);
    }

    #[TestWith(['Saya tidak butuh website baru.'])]
    #[TestWith(['I do not need website development.'])]
    public function test_negated_buying_intent_is_not_qualified(string $body): void
    {
        $result = app(QualifyDiscoveredPost::class)->classify($body, 'website');

        $this->assertNotSame('qualified', $result['status']);
        $this->assertNotSame('business', $result['category']);
    }

    public function test_content_and_relationship_notes_are_escaped(): void
    {
        $owner = User::factory()->create();
        $lead = $this->lead();
        Relationship::create(['user_id' => $owner->id, 'lead_id' => $lead->id, 'notes' => '<script>alert(1)</script>']);
        ContentDraft::create($this->content() + ['user_id' => $owner->id, 'notes' => '<script>alert(2)</script>']);

        $this->actingAs($owner)->get(route('relationships.index'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('studio.index'))->assertOk()->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)->assertDontSee('<script>alert(2)</script>', false);
    }

    public function test_replied_status_requires_confirmation_and_ownership_and_edit_resets_it(): void
    {
        $owner = User::factory()->create();
        $id = DB::table('comment_suggestions')->insertGetId([
            'user_id' => $owner->id, 'post_text' => 'Website conversation', 'tone' => 'professional', 'provider' => 'template',
            'body' => 'Balasan yang relevan untuk percakapan ini.', 'purpose' => 'comment', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($owner)->post(route('comments.suggestions.replied', $id))->assertSessionHasErrors('confirm_replied');
        $this->assertDatabaseHas('comment_suggestions', ['id' => $id, 'replied_at' => null]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.replied', $id), ['confirm_replied' => 1])->assertNotFound();
        $this->actingAs($owner)->post(route('comments.suggestions.replied', $id), ['confirm_replied' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('comment_suggestions')->find($id)->replied_at);
        $this->patch(route('comments.suggestions.update', $id), ['body' => 'Balasan ini telah diubah untuk review ulang.'])->assertRedirect();
        $this->assertDatabaseHas('comment_suggestions', ['id' => $id, 'replied_at' => null]);
        $this->assertDatabaseCount('engagement_attempts', 0);
    }

    public function test_capture_preserves_query_identity_and_removes_tracking_only(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['100', '200'] as $id) {
            $this->post(route('discovery.capture'), ['platform' => 'web', 'url' => 'https://news.ycombinator.com/item?id='.$id.'&utm_source=test',
                'body' => 'Developer website conversation.', 'keyword' => 'website'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('discovered_posts', 2);
        $this->assertDatabaseHas('discovered_posts', ['permalink' => 'https://news.ycombinator.com/item?id=100']);
    }

    public function test_community_filter_uses_latest_review_instead_of_old_assessment(): void
    {
        $owner = User::factory()->create();
        $lead = $this->lead();
        $this->actingAs($owner)->patch(route('discovery.posts.review', $lead->post), ['qualification' => 'qualified', 'category' => 'community', 'reason' => 'Cocok untuk networking.'])->assertRedirect();
        $this->get(route('discovery.index', ['category' => 'community']))->assertOk()->assertSee('Butuh website untuk usaha kecil.');
        $this->get(route('discovery.index', ['category' => 'business']))->assertOk()->assertDontSee('Butuh website untuk usaha kecil.');
    }

    public function test_reviewed_networking_can_be_saved_without_claiming_an_interaction(): void
    {
        $owner = User::factory()->create();
        $lead = $this->lead();
        $this->actingAs($owner)->put(route('relationships.update', $lead), ['category' => 'developer', 'stage' => 'reviewed'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('relationships', ['lead_id' => $lead->id, 'user_id' => $owner->id, 'stage' => 'reviewed']);
    }

    public function test_capture_validates_payload_types_and_platform_attribution(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([[], 'https://example.org/spoofed-threads-post'] as $url) {
            $this->post(route('discovery.capture'), ['platform' => 'threads', 'url' => $url, 'body' => 'Need a website for my business.', 'keyword' => 'website'])
                ->assertSessionHasErrors('url');
        }
        $this->assertDatabaseCount('discovered_posts', 0);
    }

    public function test_capture_fills_missing_text_without_overwriting_existing_history(): void
    {
        $post = DiscoveredPost::create(['platform' => 'threads', 'external_post_id' => 'original-api-id', 'permalink' => 'https://www.threads.com/@dev/post/abc']);
        $this->actingAs(User::factory()->create())->post(route('discovery.capture'), ['platform' => 'threads', 'url' => $post->permalink, 'body' => 'Butuh website untuk usaha kecil.', 'keyword' => 'website'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('discovered_posts', 1);
        $this->assertDatabaseHas('discovered_posts', ['id' => $post->id, 'external_post_id' => 'original-api-id', 'body' => 'Butuh website untuk usaha kecil.']);
    }
}
