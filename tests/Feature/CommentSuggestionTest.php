<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CommentSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set(['services.groq.key' => null, 'services.ollama.enabled' => false]);
        Http::preventStrayRequests();
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return ['post_text' => 'Aku butuh website katalog untuk toko kecil, bisa langsung chat WhatsApp.',
            'post_url' => 'https://www.threads.com/@pemiliktoko/post/Abc123', 'tone' => 'casual'];
    }

    public function test_guest_cannot_generate_or_edit_suggestions(): void
    {
        $this->post(route('comments.suggestions.store'), $this->payload())->assertRedirect(route('login'));
        $this->patch(route('comments.suggestions.update', 1), ['body' => 'Komentar yang diedit'])->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_pasted_post_generates_template_without_a_lead_or_fetching_the_optional_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('comments.suggestions.store'), $this->payload())
            ->assertRedirect('/comments#quick-comments')->assertSessionHas('success');

        $this->assertDatabaseHas('comment_suggestions', ['user_id' => $user->id, 'tone' => 'casual', 'provider' => 'template', 'post_url' => $this->payload()['post_url']]);
        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseCount('comment_drafts', 0);
        $this->assertDatabaseCount('engagement_attempts', 0);
        $this->get(route('comments.index'))->assertOk()->assertSee('Boleh tahu, websitenya')->assertSee('Salin komentar')->assertSee('Template gratis');
        Http::assertNothingSent();
    }

    public function test_ai_receives_only_post_text_and_a_conversational_instruction_and_saves_one_suggestion(): void
    {
        config()->set('services.groq.key', 'test-private-key');
        $comment = 'Kalau fokusnya katalog, tombol WhatsApp di tiap produk bisa jadi awal yang simpel. Produknya kira-kira ada berapa?';
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $comment]]]])]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('comments.suggestions.store'), $this->payload())->assertRedirect('/comments#quick-comments');

        $this->assertDatabaseHas('comment_suggestions', ['user_id' => $user->id, 'provider' => 'groq', 'body' => $comment]);
        Http::assertSent(fn ($request) => $request['messages'][1]['content'] === "Postingan untuk ditanggapi:\n".$this->payload()['post_text']
            && str_contains($request['messages'][0]['content'], 'santai seperti ngobrol')
            && str_contains($request['messages'][0]['content'], 'untrusted content')
            && ! str_contains(json_encode($request->data()), $this->payload()['post_url']));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('engagement_attempts', 0);
    }

    #[TestWith([429])]
    #[TestWith([500])]
    public function test_ai_failure_falls_back_once_to_clearly_labelled_template(int $status): void
    {
        config()->set('services.groq.key', 'test-private-key');
        Http::fake(['api.groq.com/*' => Http::response([], $status)]);

        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), $this->payload())
            ->assertRedirect()->assertSessionHas('success', 'Saran template siap. AI belum tersedia; edit sesuai postingan lalu salin ke Threads.');

        $this->assertDatabaseHas('comment_suggestions', ['provider' => 'template']);
        Http::assertSentCount(1);
    }

    public function test_seller_post_does_not_get_a_template_assuming_the_author_needs_a_website(): void
    {
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), [
            'post_text' => 'Lagi buka jasa pembuatan website landing page mulai 300rb.', 'tone' => 'friendly',
        ])->assertRedirect();

        $body = DB::table('comment_suggestions')->value('body');
        $this->assertStringContainsString('pilihan paket', $body);
        $this->assertStringNotContainsString('butuh website', $body);
        Http::assertNothingSent();
    }

    #[TestWith(['http://127.0.0.1/private'])]
    #[TestWith(['https://threads.com.evil.example/@name/post/Abc'])]
    #[TestWith(['https://evil.example/post'])]
    #[TestWith(['https://user:secret@threads.com/@name/post/Abc'])]
    public function test_invalid_reference_urls_are_rejected_without_outbound_requests(string $url): void
    {
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), array_replace($this->payload(), ['post_url' => $url]))
            ->assertSessionHasErrors('post_url');
        $this->assertDatabaseCount('comment_suggestions', 0);
        Http::assertNothingSent();
    }

    public function test_link_only_input_and_whitespace_are_not_a_post(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['https://www.threads.com/@name/post/Abc123', str_repeat(' ', 30)] as $text) {
            $this->post(route('comments.suggestions.store'), ['post_text' => $text, 'tone' => 'casual'])->assertSessionHasErrors('post_text');
        }
        $this->assertDatabaseCount('comment_suggestions', 0);
        Http::assertNothingSent();
    }

    public function test_each_user_sees_and_edits_only_their_own_quick_comments(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post(route('comments.suggestions.store'), $this->payload())->assertRedirect();
        $id = DB::table('comment_suggestions')->value('id');
        $edited = 'Bisa mulai dari katalog yang simpel dulu. Produknya ada berapa jenis?';
        $this->patch(route('comments.suggestions.update', $id), ['body' => $edited])->assertRedirect();
        $this->assertDatabaseHas('comment_suggestions', ['id' => $id, 'body' => $edited]);

        $this->actingAs($other)->get(route('comments.index'))->assertOk()->assertDontSee($edited)->assertDontSee($this->payload()['post_text']);
        $this->patch(route('comments.suggestions.update', $id), ['body' => 'Komentar dari akun berbeda'])->assertNotFound();
        $this->assertDatabaseHas('comment_suggestions', ['id' => $id, 'body' => $edited]);
    }

    #[TestWith(['tiny'])]
    #[TestWith([null])]
    #[TestWith([['unexpected']])]
    public function test_malformed_ai_output_falls_back_without_saving_invalid_body(mixed $body): void
    {
        config()->set('services.groq.key', 'test-private-key');
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $body]]]])]);

        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseHas('comment_suggestions', ['provider' => 'template']);
        Http::assertSentCount(1);
    }

    public function test_configured_local_ai_can_generate_a_casual_comment_without_groq(): void
    {
        config()->set(['services.ollama.enabled' => true, 'services.ollama.model' => 'test-model', 'services.ollama.url' => 'http://127.0.0.1:11434']);
        $comment = 'Bisa mulai dari katalog simpel dulu, nanti tombol WhatsApp-nya tinggal disambung. Produknya ada berapa?';
        Http::fake(['127.0.0.1:11434/*' => Http::response(['done' => true, 'message' => ['content' => json_encode(['body' => $comment])]])]);

        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseHas('comment_suggestions', ['provider' => 'ollama', 'body' => $comment]);
        Http::assertSentCount(1);
    }
}
