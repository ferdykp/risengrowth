<?php

namespace Tests\Feature;

use App\Exceptions\ApifyDiscoveryException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Discovery\PostNormalizer;
use App\Services\Discovery\Providers\ApifyThreadsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ApifyThreadsProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set(['services.apify.token' => 'test-private-token', 'services.apify.probe_daily_runs' => 1,
            'services.apify.probe_max_charge_usd' => 0.05, 'services.apify.monthly_budget_usd' => 4.50]);
        Http::preventStrayRequests();
    }

    /** @return array<string, mixed> */
    private function runPayload(string $status = 'SUCCEEDED'): array
    {
        return ['data' => ['id' => 'run123', 'actId' => 'actor123', 'status' => $status,
            'defaultDatasetId' => 'dataset123', 'usageTotalUsd' => 0.015]];
    }

    /** Fixture follows the Actor publisher's documented post output; not live data.
     * @return array<string, mixed>
     */
    private function entry(string $username = 'customer', string $id = '3996155940894885511'): array
    {
        return ['platform' => 'threads', 'item_type' => 'post', 'item_id' => $id,
            'url' => 'https://www.threads.net/@'.$username.'/post/Abc123',
            'description' => 'Ada rekomendasi website untuk bisnis?', 'author_username' => $username,
            'created_at' => '2026-09-28T12:35:32+00:00', 'mode' => 'search_latest', 'source_input' => 'website'];
    }

    public function test_missing_token_does_not_contact_provider(): void
    {
        config()->set('services.apify.token', null);
        Http::fake();

        $this->artisan('growth:apify-probe --start')->expectsOutputToContain('TOKEN_MISSING')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_start_uses_bounded_async_input_and_no_token_in_url(): void
    {
        Http::fake([
            'api.apify.com/v2/users/me/limits' => Http::response(['data' => ['current' => ['monthlyUsageUsd' => 0.2], 'limits' => ['maxMonthlyUsageUsd' => 5]]]),
            'api.apify.com/v2/actors/openclawai~threads-scraper/runs*' => Http::response($this->runPayload('RUNNING'), 201),
        ]);

        $run = app(ApifyThreadsProvider::class)->start('website');

        $this->assertSame('RUNNING', $run['status']);
        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->hasHeader('Authorization', 'Bearer test-private-token')
                && ! str_contains($request->url(), 'test-private-token')
                && $request['mode'] === 'search_latest' && $request['queries'] === ['website'] && $request['maxItems'] === 10
                && $query['maxTotalChargeUsd'] === '0.05' && $query['waitForFinish'] === '0' && $query['restartOnError'] === 'false';
        });
        Http::assertSentCount(2);
    }

    public function test_daily_limit_prevents_a_second_actor_start(): void
    {
        Http::fake([
            'api.apify.com/v2/users/me/limits' => Http::response(['data' => ['current' => ['monthlyUsageUsd' => 0], 'limits' => ['maxMonthlyUsageUsd' => 5]]]),
            'api.apify.com/v2/actors/openclawai~threads-scraper/runs*' => Http::response($this->runPayload('RUNNING'), 201),
        ]);
        app(ApifyThreadsProvider::class)->start('website');

        try {
            app(ApifyThreadsProvider::class)->start('website');
            $this->fail('A second run must be blocked.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('DAILY_LIMIT', $exception->reason);
        }

        Http::assertSentCount(2);
    }

    #[TestWith([4.49])]
    #[TestWith([5.0])]
    public function test_monthly_budget_blocks_start_before_post(float $spent): void
    {
        Http::fake(['api.apify.com/v2/users/me/limits' => Http::response(['data' => ['current' => ['monthlyUsageUsd' => $spent], 'limits' => ['maxMonthlyUsageUsd' => 5]]])]);

        try {
            app(ApifyThreadsProvider::class)->start('website');
            $this->fail('Budget must prevent starting.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('MONTHLY_BUDGET', $exception->reason);
        }

        Http::assertSentCount(1);
    }

    #[TestWith([402])]
    #[TestWith([429])]
    public function test_quota_error_pauses_probe_without_retry(int $status): void
    {
        Http::fake(['api.apify.com/v2/users/me/limits' => Http::response(['error' => ['message' => 'private-provider-error']], $status)]);

        try {
            app(ApifyThreadsProvider::class)->start('website');
            $this->fail('Quota failure must pause discovery.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('QUOTA_OR_RATE_LIMIT', $exception->reason);
            $this->assertStringNotContainsString('private-provider-error', $exception->getMessage());
        }

        $this->assertTrue(Cache::get('apify-probe-paused'));
        Http::assertSentCount(1);
    }

    public function test_malformed_json_does_not_start_actor(): void
    {
        Http::fake(['api.apify.com/v2/users/me/limits' => Http::response('not json', 200)]);

        try {
            app(ApifyThreadsProvider::class)->start('website');
            $this->fail('Malformed JSON must fail.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('INVALID_JSON', $exception->reason);
        }

        Http::assertSentCount(1);
    }

    public function test_uncertain_post_timeout_is_not_retried_or_started_again(): void
    {
        Http::fake([
            'api.apify.com/v2/users/me/limits' => Http::response(['data' => ['current' => ['monthlyUsageUsd' => 0], 'limits' => ['maxMonthlyUsageUsd' => 5]]]),
            'api.apify.com/v2/actors/openclawai~threads-scraper/runs*' => Http::failedConnection(),
        ]);

        try {
            app(ApifyThreadsProvider::class)->start('website');
            $this->fail('Connection failure must be reported.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('CONNECTION_FAILED', $exception->reason);
        }
        try {
            app(ApifyThreadsProvider::class)->start('website');
            $this->fail('Do not start again after ambiguous POST.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('DAILY_LIMIT', $exception->reason);
        }

        Http::assertSentCount(2);
    }

    public function test_dataset_normalizes_excludes_own_accounts_and_deduplicates(): void
    {
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([$this->entry(), $this->entry(), $this->entry('risencode.dev', '123')]),
        ]);

        $result = app(ApifyThreadsProvider::class)->posts('run123', ['@RISENCODE.DEV']);

        $this->assertSame(3, $result['received']);
        $this->assertSame(1, $result['excluded_own']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertCount(1, $result['posts']);
        $this->assertSame('https://www.threads.net/@customer/post/Abc123', $result['posts'][0]['permalink']);
        Http::assertSentCount(3);
    }

    #[TestWith(['FAILED'])]
    #[TestWith(['TIMED-OUT'])]
    #[TestWith(['RUNNING'])]
    public function test_unsuccessful_run_does_not_read_dataset(string $status): void
    {
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload($status)),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
        ]);

        try {
            app(ApifyThreadsProvider::class)->posts('run123', []);
            $this->fail('Run must succeed before reading results.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('RUN_NOT_SUCCEEDED', $exception->reason);
        }

        Http::assertSentCount(2);
    }

    #[TestWith(['hint'])]
    #[TestWith(['error'])]
    public function test_provider_hint_rows_are_not_treated_as_empty_success(string $type): void
    {
        $this->expectException(ApifyDiscoveryException::class);
        $this->expectExceptionMessage('hint/error');

        app(PostNormalizer::class)->fromApify(['item_type' => $type, 'error_message' => 'private-details'], []);
    }

    #[TestWith(['https://evil.example/@customer/post/Abc123'])]
    #[TestWith(['https://www.threads.com.evil.example/@customer/post/Abc123'])]
    #[TestWith(['http://www.threads.com/@customer/post/Abc123'])]
    #[TestWith(['https://www.threads.com/@different/post/Abc123'])]
    public function test_invalid_post_urls_are_rejected(string $url): void
    {
        $entry = $this->entry();
        $entry['url'] = $url;
        $this->expectException(ApifyDiscoveryException::class);

        app(PostNormalizer::class)->fromApify($entry, []);
    }

    public function test_probe_existing_run_prints_other_account_urls_without_persisting_leads(): void
    {
        $user = User::factory()->admin()->create();
        SocialAccount::create(['user_id' => $user->id, 'platform' => 'threads', 'external_account_id' => 'own', 'username' => 'customer']);
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([$this->entry(), $this->entry('otherbusiness', '123')]),
        ]);

        $this->artisan('growth:apify-probe --run=run123 --keyword=website')
            ->expectsOutputToContain('@otherbusiness')->assertSuccessful();

        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseCount('leads', 0);
        Http::assertSentCount(5);
    }

    public function test_empty_live_dataset_fails_feasibility_without_creating_data(): void
    {
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([]),
        ]);

        $this->artisan('growth:apify-probe --run=run123')->expectsOutputToContain('Uji belum memenuhi syarat')->assertFailed();

        $this->assertDatabaseCount('discovered_posts', 0);
        $this->assertDatabaseCount('leads', 0);
        Http::assertSentCount(5);
    }

    public function test_run_from_a_different_actor_is_rejected_without_dataset_read(): void
    {
        $payload = $this->runPayload();
        $payload['data']['actId'] = 'wrongActor';
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($payload),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
        ]);

        try {
            app(ApifyThreadsProvider::class)->posts('run123', []);
            $this->fail('Runs from other actors must not be imported.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('WRONG_ACTOR', $exception->reason);
        }

        Http::assertSentCount(2);
    }

    public function test_wrong_keyword_is_not_reported_as_a_successful_probe(): void
    {
        $entry = $this->entry();
        $entry['source_input'] = 'different query';
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([$entry]),
        ]);

        $this->artisan('growth:apify-probe --run=run123 --keyword=website')->expectsOutputToContain('WRONG_QUERY')->assertFailed();

        $this->assertDatabaseCount('leads', 0);
        Http::assertSentCount(5);
    }

    public function test_ten_posts_plus_actual_free_plan_notice_are_accepted(): void
    {
        $posts = array_map(fn (int $id): array => $this->entry('customer', (string) $id), range(1, 10));
        $notice = ['platform' => 'threads', 'item_type' => 'hint', 'item_id' => null, 'url' => null,
            'mode' => 'search_latest', 'source_input' => '',
            'error_message' => 'Free Apify plan: this actor returns up to 10 results per run, without comment replies. Upgrade to any paid Apify plan for unlimited results and all modes.'];
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([...$posts, $notice]),
        ]);

        $result = app(ApifyThreadsProvider::class)->posts('run123', []);

        $this->assertSame(11, $result['received']);
        $this->assertSame(1, $result['plan_notices']);
        $this->assertCount(10, $result['posts']);
        Http::assertSentCount(3);
    }

    public function test_eleven_actual_posts_still_exceed_the_probe_limit(): void
    {
        $posts = array_map(fn (int $id): array => $this->entry('customer', (string) $id), range(1, 11));
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response($posts),
        ]);

        try {
            app(ApifyThreadsProvider::class)->posts('run123', []);
            $this->fail('Eleven posts must still be refused.');
        } catch (ApifyDiscoveryException $exception) {
            $this->assertSame('INVALID_DATASET', $exception->reason);
        }

        Http::assertSentCount(3);
    }

    #[TestWith(['website', 'hint', "No results for 'website' on Threads.", true])]
    #[TestWith(['different', 'hint', "No results for 'website' on Threads.", false])]
    #[TestWith(['website', 'error', "No results for 'website' on Threads.", false])]
    #[TestWith(['website', 'hint', 'Search failed upstream', false])]
    public function test_only_exact_empty_search_hint_for_requested_keyword_is_accepted(string $sourceInput, string $type, string $message, bool $accepted): void
    {
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([['platform' => 'threads', 'item_type' => $type,
                'mode' => 'search_latest', 'source_input' => $sourceInput, 'error_message' => $message, 'item_id' => null, 'url' => null]]),
        ]);
        if (! $accepted) {
            $this->expectException(ApifyDiscoveryException::class);
        }

        $result = app(ApifyThreadsProvider::class)->posts('run123', []);

        $this->assertSame([], $result['posts']);
        $this->assertSame(1, $result['empty_notices']);
    }

    public function test_empty_search_notice_cannot_hide_contradictory_post_rows(): void
    {
        Http::fake([
            'api.apify.com/v2/actor-runs/run123' => Http::response($this->runPayload()),
            'api.apify.com/v2/actors/openclawai~threads-scraper' => Http::response(['data' => ['id' => 'actor123']]),
            'api.apify.com/v2/datasets/dataset123/items*' => Http::response([$this->entry(), ['platform' => 'threads', 'item_type' => 'hint',
                'mode' => 'search_latest', 'source_input' => 'website', 'error_message' => "No results for 'website' on Threads."]]),
        ]);
        $this->expectException(ApifyDiscoveryException::class);
        $this->expectExceptionMessage('sekaligus');

        app(ApifyThreadsProvider::class)->posts('run123', []);
    }
}
