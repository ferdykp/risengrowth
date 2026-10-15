<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThreadsConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.threads', [
            'app_id' => 'test-app-id',
            'app_secret' => 'test-app-secret',
            'redirect_uri' =>
            'https://growth.risencode.dev/auth/threads/callback',
            'api_base_url' => 'https://graph.threads.net',
            'scopes' => [
                'threads_basic',
                'threads_keyword_search',
            ],
        ]);
    }

    public function test_guest_cannot_connect_threads(): void
    {
        $this->post(route('threads.connect'))
            ->assertRedirect(route('login'));
    }

    public function test_member_cannot_connect_threads(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post(route('threads.connect'))
            ->assertForbidden();
    }

    public function test_admin_is_redirected_to_meta(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('threads.connect'));

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertStringStartsWith(
            'https://threads.net/oauth/authorize',
            $location
        );

        $this->assertStringContainsString(
            'client_id=test-app-id',
            $location
        );

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_invalid_oauth_state_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('threads.connect'));

        $this->get(route('threads.callback', [
            'state' => 'invalid',
            'code' => 'test-code',
        ]))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('threads');

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_oauth_callback_stores_encrypted_token(): void
    {
        $admin = User::factory()->admin()->create();

        Http::preventStrayRequests();

        Http::fake([
            'graph.threads.net/oauth/access_token' =>
            Http::response([
                'access_token' => 'short-test-token',
                'user_id' => '123456789',
            ]),

            'graph.threads.net/access_token*' =>
            Http::response([
                'access_token' => 'long-test-token',
                'expires_in' => 5184000,
            ]),

            'graph.threads.net/me*' =>
            Http::response([
                'id' => '123456789',
                'username' => 'risendev',
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('threads.connect'));

        $state = session('threads_oauth.state');

        $this->get(route('threads.callback', [
            'state' => $state,
            'code' => 'test-code',
        ]))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success');

        $account = SocialAccount::firstOrFail();

        $this->assertSame('threads', $account->platform);
        $this->assertSame('connected', $account->status);
        $this->assertSame('risendev', $account->username);
        $this->assertSame(
            'long-test-token',
            $account->getAccessToken()
        );
        $this->assertNotSame(
            'long-test-token',
            $account->access_token_encrypted
        );
    }

    public function test_admin_can_disconnect_threads(): void
    {
        $admin = User::factory()->admin()->create();

        $account = SocialAccount::create([
            'user_id' => $admin->id,
            'platform' => 'threads',
            'external_account_id' => '123456789',
            'username' => 'risendev',
            'status' => 'connected',
        ]);

        $account->setAccessToken('test-secret-token');
        $account->save();

        $this->actingAs($admin)
            ->delete(route('threads.disconnect', $account))
            ->assertRedirect(route('settings.index'));

        $account->refresh();

        $this->assertSame('disconnected', $account->status);
        $this->assertNull($account->access_token_encrypted);
    }
}
