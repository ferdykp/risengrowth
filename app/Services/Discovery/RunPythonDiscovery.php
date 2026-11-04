<?php

namespace App\Services\Discovery;

use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\SocialAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunPythonDiscovery
{
    public function __construct(private readonly RecordOpportunityAssessment $assessment) {}

    /**
     * Run a Python worker for one keyword: `python` (configured public web sources) or `scrapling` (Threads search).
     * Scrapling keeps only posts dated within $postedFrom..$postedTo (Y-m-d, WIB); both default to the last
     * `services.scrapling.max_post_age_days` days up to today.
     *
     * @return array{fetched: int, added: int, linked: int}
     */
    public function execute(object $keyword, string $engine = 'python', ?string $postedFrom = null, ?string $postedTo = null): array
    {
        $lock = Cache::lock($engine.'-discovery:'.$keyword->id, 120);
        if (! $lock->get()) {
            return ['fetched' => 0, 'added' => 0, 'linked' => 0];
        }

        try {
            return $this->run($keyword, $engine, $postedFrom, $postedTo);
        } finally {
            $lock->release();
        }
    }

    /** @return array{fetched: int, added: int, linked: int} */
    private function run(object $keyword, string $engine, ?string $postedFrom, ?string $postedTo): array
    {
        $isScrapling = $engine === 'scrapling';
        $today = now('Asia/Jakarta');
        $from = $isScrapling ? Carbon::parse($postedFrom ?? $today->copy()->subDays((int) config('services.scrapling.max_post_age_days'))->toDateString(), 'Asia/Jakarta')->startOfDay() : null;
        $to = $isScrapling ? Carbon::parse($postedTo ?? $today->toDateString(), 'Asia/Jakarta')->endOfDay() : null;
        $runId = DB::table('discovery_runs')->insertGetId([
            'social_account_id' => null, 'source_type' => $engine,
            'search_keyword_id' => $keyword->id, 'run_uuid' => (string) Str::uuid(),
            'posted_from' => $from?->toDateString(), 'posted_to' => $to?->toDateString(),
            'status' => 'running', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $failureSummary = null;

        try {
            $result = Process::path(base_path())->timeout(90)->run($isScrapling ? [
                config('services.risengrowth_python.bin'), config('services.scrapling.script'),
                '--keyword', $keyword->phrase, '--mode', (string) config('services.scrapling.mode'),
                '--timeout', (string) config('services.scrapling.timeout'), '--max-posts', (string) config('services.scrapling.max_posts'),
            ] : [
                config('services.risengrowth_python.bin'), config('services.risengrowth_python.script'),
                '--keyword', $keyword->phrase, '--config', config('services.risengrowth_python.sources'),
            ]);
            $decoded = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new RuntimeException('Invalid Python worker response');
            }
            Validator::make($decoded, [
                'posts' => ['present', 'array', 'max:200'],
                'posts.*' => ['required', 'array'],
                'posts.*.body' => ['required', 'string', 'max:20000'],
                'posts.*.external_post_id' => ['required', 'string', 'max:191'],
                'posts.*.permalink' => ['required', 'url:https', 'max:2048',
                    ...($isScrapling ? ['regex:~\Ahttps://www\.threads\.com/@[a-zA-Z0-9._]+/post/[a-zA-Z0-9_-]+\z~'] : [])],
                'posts.*.source' => ['required', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
                'posts.*.username' => ['nullable', 'string', 'max:191'],
                'posts.*.posted_at' => ['nullable', 'date'],
                'errors' => ['present', 'array'],
                'errors.*.source' => ['required', 'string', 'max:100'],
                'errors.*.error' => ['required', 'string', 'max:200'],
                'sources_configured' => ['required', 'integer', 'min:1'],
            ])->validate();
            $blocked = collect($decoded['errors'])->contains(fn (array $error): bool => (bool) preg_match('/LOGIN_WALL|HTTP_(403|429)/', $error['error']));
            if ($isScrapling && $blocked) {
                $backoff = (int) config('services.scrapling.block_backoff_seconds');
                Cache::put('scrapling-next-request-at', now()->timestamp + $backoff, $backoff);
            }
            if (! $result->successful() && ($result->exitCode() !== 2 || ! $decoded['errors'])) {
                throw new RuntimeException('Python worker exited unexpectedly');
            }
            if ($decoded['errors'] && ! $decoded['posts'] && count($decoded['errors']) >= $decoded['sources_configured']) {
                $failureSummary = 'Semua sumber gagal: '.implode('; ', array_column($decoded['errors'], 'error'));
                throw new RuntimeException('All configured discovery sources failed');
            }
            $received = count($decoded['posts']);
            $excludedOwn = $outOfRange = 0;
            if ($isScrapling) {
                $ownUsernames = array_map(fn (string $name): string => strtolower(ltrim(trim($name), '@')), array_merge(
                    config('services.apify.excluded_usernames', []),
                    SocialAccount::where('platform', 'threads')->whereNotNull('username')->pluck('username')->all(),
                ));
                $kept = array_filter($decoded['posts'], fn (array $entry): bool => ! in_array(strtolower((string) ($entry['username'] ?? '')), $ownUsernames, true));
                $excludedOwn = count($decoded['posts']) - count($kept);
                $inRange = array_filter($kept, fn (array $entry): bool => filled($entry['posted_at'] ?? null)
                    && Carbon::parse($entry['posted_at'])->between($from, $to));
                $outOfRange = count($kept) - count($inRange);
                $decoded['posts'] = array_values($inRange);
            }

            return DB::transaction(function () use ($decoded, $runId, $keyword, $isScrapling, $excludedOwn, $outOfRange, $received): array {
                $added = 0;
                $relevant = 0;
                $linked = [];
                foreach ($decoded['posts'] as $entry) {
                    $post = DiscoveredPost::firstOrCreate([
                        'platform' => $isScrapling ? 'threads' : 'web', 'external_post_id' => $entry['external_post_id'],
                    ], [
                        'username' => $entry['username'] ?? null,
                        'body' => $entry['body'], 'permalink' => $entry['permalink'],
                        'posted_at' => $entry['posted_at'] ?? null,
                        'first_discovered_at' => now(), 'last_seen_at' => now(),
                    ]);
                    $post->update(['last_seen_at' => now()]);
                    if (isset($linked[$post->id])) {
                        continue;
                    }
                    DB::table('discovery_run_posts')->insert([
                        'discovery_run_id' => $runId, 'discovered_post_id' => $post->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $linked[$post->id] = true;
                    $qualification = $this->assessment->record($post, $keyword->phrase, $entry['source'], $keyword->id, $runId);
                    $output = $qualification;
                    if ($output['status'] !== 'qualified') {
                        continue;
                    }
                    $relevant++;
                    $lead = Lead::firstOrCreate(['discovered_post_id' => $post->id], [
                        'intent' => $qualification['intent'], 'service_category' => $qualification['service_category'],
                        'score' => $qualification['score'], 'priority' => $qualification['score'] >= 80 ? 'high' : 'medium',
                        'status' => 'new', 'rationale' => $qualification['rationale'].' '.json_encode($qualification['evidence'], JSON_UNESCAPED_UNICODE),
                    ]);
                    $added += (int) $lead->wasRecentlyCreated;
                }
                DB::table('search_keywords')->where('id', $keyword->id)->update([
                    'last_ran_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('discovery_runs')->where('id', $runId)->update([
                    'status' => $decoded['errors'] ? 'partial' : 'completed',
                    'fetched_count' => $received, 'added_count' => $added, 'relevant_count' => $relevant,
                    'excluded_own_count' => $excludedOwn, 'out_of_range_count' => $outOfRange,
                    'error_code' => $decoded['errors'] ? 'SOURCE_PARTIAL' : null,
                    'error_summary' => $decoded['errors'] ? json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE) : null,
                    'finished_at' => now(), 'updated_at' => now(),
                ]);

                return ['fetched' => $received, 'added' => $added, 'linked' => count($linked)];
            });
        } catch (Throwable $exception) {
            DB::table('discovery_runs')->where('id', $runId)->update([
                'status' => 'failed', 'error_code' => 'PYTHON_WORKER_FAILED',
                'error_summary' => $failureSummary ?? 'Discovery failed ('.$exception::class.'). Check worker configuration, source access and application logs.',
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            throw $exception;
        }
    }
}
