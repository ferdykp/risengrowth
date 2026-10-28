<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Discovery\GenerateCommentSuggestion;
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
        $this->get(route('comments.index'))->assertOk()->assertSee('Komentar (boleh diedit)')->assertSee('Salin komentar')->assertSee('Template gratis');
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
        $this->assertNotEmpty($body);
        $this->assertStringNotContainsString('@risendev', $body);
        $this->assertStringNotContainsString('risencode.dev', $body);
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

    #[TestWith(['Kalau fokusnya katalog, alur ke WhatsApp bisa dibuat singkat. Kami di @risendev bisa diskusi kebutuhannya dulu.'])]
    #[TestWith(['Katalog yang ringan di HP bisa jadi langkah awal. Info tim kami ada di https://risencode.dev kalau mau ngobrol soal kebutuhannya.'])]
    public function test_one_approved_brand_reference_is_allowed_for_relevant_buyer_posts(string $body): void
    {
        config()->set('services.groq.key', 'test-private-key');
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $body]]]])]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseHas('comment_suggestions', ['provider' => 'groq', 'body' => $body]);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'https://risencode.dev')
            && str_contains($request['messages'][0]['content'], 'do not pretend to be an independent customer'));
    }

    public function test_turning_off_branding_rejects_branded_ai_output_and_returns_an_unbranded_suggestion(): void
    {
        config()->set('services.groq.key', 'test-private-key');
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Kalau perlu website, kami di @risendev bisa bantu diskusi dulu.']]]])]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), $this->payload() + ['allow_brand' => 0])->assertRedirect();

        $record = DB::table('comment_suggestions')->first();
        $this->assertSame('template', $record->provider);
        $this->assertStringNotContainsString('@risendev', $record->body);
        $this->assertStringNotContainsString('risencode.dev', $record->body);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'unbranded response'));
    }

    #[TestWith(['Lagi buka jasa pembuatan website landing page mulai 300rb.'])]
    #[TestWith(['Cari web developer untuk lowongan full time di kantor kami.'])]
    #[TestWith(['Kenapa website sekarang banyak animasinya ya?'])]
    #[TestWith(['Butuh website katalog untuk usaha, tolong tanpa promosi.'])]
    public function test_brand_is_not_included_without_a_suitable_offer_context(string $post): void
    {
        config()->set('services.groq.key', 'test-private-key');
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Kami di @risendev juga menyediakan website untuk usaha.']]]])]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), [
            'post_text' => $post, 'tone' => 'casual', 'allow_brand' => 1,
        ])->assertRedirect();

        $body = DB::table('comment_suggestions')->value('body');
        $this->assertStringNotContainsString('@risendev', $body);
        $this->assertStringNotContainsString('risencode.dev', $body);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'unbranded response'));
    }

    #[TestWith(['Kami di @risendev bisa diskusi. Info kami di https://risencode.dev juga ya.'])]
    #[TestWith(['Coba diskusi kebutuhan websitenya lewat https://evil.example dulu.'])]
    #[TestWith(['Untuk kebutuhan website itu bisa ngobrol dengan @akunlain.'])]
    #[TestWith(['Info kami di https://risencode.dev/portfolio-rahasia untuk lihat contohnya.'])]
    #[TestWith(['Untuk info kami kunjungi https://risencode.dev.evil.example ya.'])]
    public function test_unapproved_or_excessive_references_are_not_saved_as_ai_suggestions(string $body): void
    {
        config()->set('services.groq.key', 'test-private-key');
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $body]]]])]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseHas('comment_suggestions', ['provider' => 'template']);
        $saved = DB::table('comment_suggestions')->value('body');
        $this->assertNotSame($body, $saved);
        $this->assertStringNotContainsString('evil.example', $saved);
        $this->assertStringNotContainsString('@akunlain', $saved);
    }

    public function test_templates_are_contextual_without_generic_openers_and_do_not_call_ai_when_selected(): void
    {
        config()->set('services.groq.key', 'test-private-key');
        $result = app(GenerateCommentSuggestion::class)->generate($this->payload()['post_text'], 'casual', false, 'template');

        $this->assertSame('template', $result['provider']);
        $this->assertStringContainsString('WhatsApp', $result['body']);
        $this->assertStringNotContainsString('Menarik nih', $result['body']);
        $this->assertStringNotContainsString('Boleh tahu', $result['body']);
        $this->assertStringNotContainsString('@risendev', $result['body']);
        Http::assertNothingSent();
    }

    public function test_dm_is_saved_privately_with_a_different_prompt_and_profile_link_without_sending(): void
    {
        config()->set('services.groq.key', 'test-key');
        $body = 'Halo, aku dari tim RisenDev. Aku lihat kamu mencari katalog toko yang terhubung ke WhatsApp. Boleh ngobrol soal kebutuhan tokonya?';
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $body]]]])]);
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('comments.suggestions.store'), $this->payload() + ['purpose' => 'dm'])
            ->assertRedirect('/comments#quick-comments');
        $this->assertDatabaseHas('comment_suggestions', ['user_id' => $owner->id, 'purpose' => 'dm', 'body' => $body]);
        $this->get(route('comments.index'))->assertOk()->assertSee('Salin DM')->assertSee('href="https://www.threads.com/@pemiliktoko"', false);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'private introductory DM')
            && str_contains($request['messages'][0]['content'], 'No URLs or account mentions'));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('engagement_attempts', 0);
        $this->actingAs(User::factory()->create())->get(route('comments.index'))->assertDontSee($body);
    }

    public function test_first_contact_dm_does_not_keep_links_even_when_branding_is_enabled(): void
    {
        config()->set('services.groq.key', 'test-key');
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Halo, tim kami bisa bantu katalog. Lihat https://risencode.dev ya.']]]])]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), $this->payload() + ['purpose' => 'dm'])->assertRedirect();
        $record = DB::table('comment_suggestions')->first();
        $this->assertSame('template', $record->provider);
        $this->assertSame('dm', $record->purpose);
        $this->assertStringNotContainsString('https://', $record->body);
        Http::assertSentCount(1);
    }

    #[TestWith(['insightful', 'insight praktis'])]
    #[TestWith(['direct', 'tanpa basa-basi'])]
    #[TestWith(['playful', 'sedikit jenaka'])]
    public function test_new_styles_include_context_guardrails(string $tone, string $instruction): void
    {
        config()->set('services.groq.key', 'test-key');
        $body = 'Untuk katalog toko, alur ke WhatsApp bisa dibuat sesuai cara pelangganmu memesan.';
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $body]]]])]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), array_replace($this->payload(), ['tone' => $tone]))->assertRedirect();
        $this->assertDatabaseHas('comment_suggestions', ['tone' => $tone, 'body' => $body, 'purpose' => 'comment']);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], $instruction)
            && str_contains($request['messages'][0]['content'], 'Preserve negation')
            && str_contains($request['messages'][0]['content'], 'Never repeat a supplied budget or deadline'));
    }

    public function test_unsupported_destination_and_style_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), array_replace($this->payload(), ['purpose' => 'send-now', 'tone' => 'spam']))
            ->assertSessionHasErrors(['purpose', 'tone']);
        $this->assertDatabaseCount('comment_suggestions', 0);
        Http::assertNothingSent();
    }

    public function test_negated_request_is_not_used_as_a_sales_opportunity(): void
    {
        $result = app(GenerateCommentSuggestion::class)->generate('Aku tidak butuh website baru, cuma ingin cerita soal pengalaman kerja.', 'casual', true, 'template');
        $this->assertStringNotContainsString('@risendev', $result['body']);
        $this->assertStringNotContainsString('partner', $result['body']);
        Http::assertNothingSent();
    }

    #[TestWith(['Bisa dimulai dari katalog WhatsApp supaya lebih gampang pesan.'])]
    #[TestWith(['Untuk landing page yang kamu cari, kami bisa diskusi dulu.'])]
    #[TestWith(['Budget untuk websitenya kira-kira berapa?'])]
    public function test_off_context_features_and_repeated_budget_questions_are_not_saved_as_ai(string $body): void
    {
        config()->set('services.groq.key', 'test-key');
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $body]]]])]);
        $this->actingAs(User::factory()->create())->post(route('comments.suggestions.store'), [
            'post_text' => 'Cari website untuk profil perusahaan, budget 5 juta. Tidak perlu fitur jualan.', 'tone' => 'direct',
        ])->assertRedirect();
        $this->assertDatabaseHas('comment_suggestions', ['provider' => 'template']);
        $this->assertDatabaseMissing('comment_suggestions', ['body' => $body]);
        Http::assertSentCount(1);
    }
}
