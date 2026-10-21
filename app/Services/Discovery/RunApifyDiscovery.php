<?php

namespace App\Services\Discovery;

use App\Exceptions\ApifyDiscoveryException;
use App\Jobs\RunApifyDiscoveryJob;
use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\SocialAccount;
use App\Services\Discovery\Providers\ApifyThreadsProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunApifyDiscovery
{
    public function __construct(
        private readonly ApifyThreadsProvider $provider,
        private readonly QualifyDiscoveredPost $qualifier,
        private readonly ApifyDiscoverySettings $settings,
    ) {}

    public function enqueue(int $keywordId, ?string $existingRunId = null): object
    {
        $lock = Cache::lock('apify-discovery-enqueue', 15);
        if (! $lock->get()) {
            throw new ApifyDiscoveryException('ENQUEUE_BUSY', 'Permintaan discovery lain sedang masuk. Coba lagi sebentar.');
        }
        try {
            return $this->enqueueLocked($keywordId, $existingRunId);
        } finally {
            $lock->release();
        }
    }

    private function enqueueLocked(int $keywordId, ?string $existingRunId): object
    {
        if (blank(config('services.apify.token'))) {
            throw new ApifyDiscoveryException('TOKEN_MISSING', 'APIFY_TOKEN belum tersedia di server.');
        }
        if ($existingRunId !== null && ! preg_match('/\A[a-zA-Z0-9]{1,64}\z/', $existingRunId)) {
            throw new ApifyDiscoveryException('INVALID_ID', 'ID run Apify tidak valid.');
        }
        $run = DB::transaction(function () use ($keywordId, $existingRunId): object {
            $keyword = DB::table('search_keywords')->where('id', $keywordId)->where('enabled', true)
                ->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $keyword) {
                throw new ApifyDiscoveryException('INVALID_KEYWORD', 'Keyword tidak ditemukan atau tidak aktif.');
            }
            if ($existingRunId !== null) {
                $existing = DB::table('discovery_runs')->where('provider_run_id', $existingRunId)->lockForUpdate()->first();
                if ($existing) {
                    if ((int) $existing->search_keyword_id !== $keywordId) {
                        $canCorrectKeyword = $existing->source_type === 'apify'
                            && $existing->status === 'failed' && $existing->error_code === 'WRONG_QUERY'
                            && (int) $existing->added_count === 0
                            && ! DB::table('discovery_run_posts')->where('discovery_run_id', $existing->id)->exists()
                            && ! AiAnalysis::where('output->run_id', $existing->id)->exists();
                        if (! $canCorrectKeyword) {
                            throw new ApifyDiscoveryException('RUN_ALREADY_LINKED', 'Run ini sudah terkait keyword lain. Hanya impor gagal karena keyword tidak cocok dan tanpa hasil tersimpan yang dapat dikoreksi.');
                        }
                        DB::table('discovery_runs')->where('id', $existing->id)->update([
                            'search_keyword_id' => $keywordId, 'keyword_snapshot' => $keyword->phrase, 'updated_at' => now(),
                        ]);
                    }
                    if ($existing->status === 'failed') {
                        DB::table('discovery_runs')->where('id', $existing->id)->update(['status' => 'queued',
                            'provider_poll_count' => 0, 'provider_poll_after' => null, 'started_at' => null,
                            'finished_at' => null, 'error_code' => null, 'error_summary' => null, 'updated_at' => now()]);
                    }

                    return DB::table('discovery_runs')->find($existing->id);
                }
            }
            $active = DB::table('discovery_runs')->where('search_keyword_id', $keywordId)->where('source_type', 'apify')
                ->whereIn('status', ['queued', 'running'])->first();
            if ($active) {
                return $active;
            }
            $settings = $this->settings->get();
            if ($existingRunId === null) {
                if (! $settings->enabled) {
                    throw new ApifyDiscoveryException('PROVIDER_DISABLED', 'Aktifkan Apify melalui pengaturan provider di halaman Eksplorasi.');
                }
                if (Cache::has('apify-probe-paused')) {
                    throw new ApifyDiscoveryException('PROVIDER_PAUSED', 'Apify dijeda karena kuota/rate limit. Periksa billing dan status provider.');
                }
                $attemptsToday = DB::table('discovery_runs')->where('source_type', 'apify')
                    ->where(function ($query) {
                        $query->where('provider_attempted_at', '>=', now()->utc()->startOfDay())
                            ->orWhere(function ($pending) {
                                $pending->whereNull('provider_run_id')->whereNull('provider_attempted_at')
                                    ->whereIn('status', ['queued', 'running'])->where('created_at', '>=', now()->utc()->startOfDay());
                            });
                    })->count();
                $startsToday = (int) Cache::get('apify-probe-starts:'.now()->utc()->format('Y-m-d'), 0);
                if (max($attemptsToday, $startsToday) >= $settings->daily_runs) {
                    throw new ApifyDiscoveryException('DAILY_LIMIT', 'Batas run harian UTC tercapai. Impor run lama tetap tersedia.');
                }
                $last = DB::table('discovery_runs')->where('source_type', 'apify')->where('search_keyword_id', $keywordId)->latest('id')->first();
                if ($last && Carbon::parse($last->created_at)->addMinutes(max($settings->min_interval_minutes, $keyword->interval_minutes ?? 360))->isFuture()) {
                    throw new ApifyDiscoveryException('INTERVAL_NOT_DUE', 'Keyword ini belum jatuh tempo. Tunggu interval pencarian atau impor run yang sudah ada.');
                }
            }
            $id = DB::table('discovery_runs')->insertGetId(['social_account_id' => null, 'source_type' => 'apify',
                'search_keyword_id' => $keywordId, 'keyword_snapshot' => $keyword->phrase, 'run_uuid' => (string) Str::uuid(),
                'provider_run_id' => $existingRunId, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('discovery_runs')->find($id);
        });
        if (in_array($run->status, ['queued', 'running'], true)) {
            RunApifyDiscoveryJob::dispatch($run->id)->afterCommit();
        }

        return $run;
    }

    public function advance(int $runId): void
    {
        $lock = Cache::lock('apify-discovery-run:'.$runId, 130);
        if (! $lock->get()) {
            return;
        }
        try {
            $run = DB::table('discovery_runs')->where('id', $runId)->where('source_type', 'apify')->first();
            if (! $run || ! in_array($run->status, ['queued', 'running'], true)) {
                return;
            }
            if ($run->provider_poll_after && Carbon::parse($run->provider_poll_after)->isFuture()) {
                return;
            }
            if ($run->provider_poll_count >= 20 || ($run->started_at && Carbon::parse($run->started_at)->addMinutes(20)->isPast())) {
                throw new ApifyDiscoveryException('POLL_LIMIT', 'Batas pemantauan tercapai. Periksa run di Apify lalu impor ID yang sama untuk melanjutkan tanpa start baru.');
            }
            if (! $run->started_at) {
                DB::table('discovery_runs')->where('id', $runId)->update(['status' => 'running', 'started_at' => now(), 'updated_at' => now()]);
            }
            if ($run->provider_run_id === null) {
                if ($run->provider_attempted_at !== null) {
                    throw new ApifyDiscoveryException('START_UNCERTAIN', 'Start pernah dicoba tetapi ID belum tersimpan. Cari run di Console lalu impor ID-nya; start tidak diulang.');
                }
                $settings = $this->settings->get();
                if (! $settings->enabled) {
                    throw new ApifyDiscoveryException('PROVIDER_DISABLED', 'Provider dinonaktifkan sebelum worker mulai.');
                }
                $keyword = DB::table('search_keywords')->where('id', $run->search_keyword_id)->where('enabled', true)->whereNull('deleted_at')->first();
                if (! $keyword) {
                    throw new ApifyDiscoveryException('KEYWORD_DISABLED', 'Keyword sudah dinonaktifkan atau diarsipkan.');
                }
                $attemptsToday = DB::table('discovery_runs')->where('source_type', 'apify')
                    ->where('provider_attempted_at', '>=', now()->utc()->startOfDay())->count();
                if ($attemptsToday >= $settings->daily_runs) {
                    throw new ApifyDiscoveryException('DAILY_LIMIT', 'Batas start harian UTC tercapai. Actor tidak dimulai.');
                }
                DB::table('discovery_runs')->where('id', $runId)->update(['provider_attempted_at' => now(), 'updated_at' => now()]);
                $remote = $this->provider->start($run->keyword_snapshot, $settings->daily_runs);
                DB::table('discovery_runs')->where('id', $runId)->update(['provider_run_id' => $remote['id'],
                    'provider_dataset_id' => $remote['dataset_id'], 'provider_usage_usd' => $remote['reported_usage_usd'], 'updated_at' => now()]);
                $run->provider_run_id = $remote['id'];
            }
            $remote = $this->provider->inspect($run->provider_run_id);
            DB::table('discovery_runs')->where('id', $runId)->update(['provider_dataset_id' => $remote['dataset_id'],
                'provider_usage_usd' => $remote['reported_usage_usd'], 'provider_poll_count' => $run->provider_poll_count + 1, 'updated_at' => now()]);
            if (in_array($remote['status'], ['READY', 'RUNNING', 'TIMING-OUT', 'ABORTING'], true)) {
                DB::table('discovery_runs')->where('id', $runId)->update(['provider_poll_after' => now()->addSeconds(15), 'updated_at' => now()]);
                RunApifyDiscoveryJob::dispatch($runId)->delay(now()->addSeconds(15));

                return;
            }
            if ($remote['status'] !== 'SUCCEEDED') {
                throw new ApifyDiscoveryException('ACTOR_FAILED', 'Run Apify berakhir dengan status '.$remote['status'].'. Periksa Console; Actor tidak diulang otomatis.');
            }
            $excluded = array_merge(config('services.apify.excluded_usernames', []), SocialAccount::where('platform', 'threads')
                ->whereNotNull('username')->pluck('username')->all());
            $result = $this->provider->posts($run->provider_run_id, $excluded, $run->keyword_snapshot);
            Cache::lock('apify-discovery-persist', 120)->block(5, fn () => $this->persist($run, $result));
        } catch (Throwable $exception) {
            DB::table('discovery_runs')->where('id', $runId)->whereIn('status', ['queued', 'running'])->update([
                'status' => 'failed', 'error_code' => $exception instanceof ApifyDiscoveryException ? $exception->reason : 'APIFY_STORAGE_OR_WORKER_FAILED',
                'error_summary' => $exception instanceof ApifyDiscoveryException ? $exception->getMessage() : 'Pemrosesan gagal. Data dibatalkan; periksa log server. Gunakan impor ID yang sama untuk pemulihan.',
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            if (! $exception instanceof ApifyDiscoveryException) {
                report($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /** @param array{posts: array<int, array<string, mixed>>, excluded_own: int, duplicates: int, received: int, plan_notices: int} $result */
    private function persist(object $run, array $result): void
    {
        DB::transaction(function () use ($run, $result): void {
            $current = DB::table('discovery_runs')->where('id', $run->id)->lockForUpdate()->first();
            if (! in_array($current->status, ['queued', 'running'], true)) {
                return;
            }
            $added = $qualified = $uncertain = $rejected = 0;
            foreach ($result['posts'] as $entry) {
                $post = DiscoveredPost::firstOrCreate(['platform' => 'threads', 'external_post_id' => $entry['external_post_id']], [
                    'username' => $entry['username'], 'body' => $entry['body'], 'permalink' => $entry['permalink'],
                    'posted_at' => $entry['posted_at'], 'first_discovered_at' => now(), 'last_seen_at' => now(),
                ]);
                $post->update(['last_seen_at' => now()]);
                DB::table('discovery_run_posts')->insertOrIgnore(['discovery_run_id' => $run->id, 'discovered_post_id' => $post->id,
                    'created_at' => now(), 'updated_at' => now()]);
                $classification = $this->qualifier->classify($post->body ?? '', $run->keyword_snapshot);
                $output = $classification + ['source' => 'apify', 'keyword_id' => $run->search_keyword_id, 'run_id' => $run->id];
                $previous = $post->analyses()->first()?->output;
                if (isset($previous['reviewer_id'])) {
                    $output = array_merge($output, ['status' => $previous['status'], 'rationale' => $previous['rationale'],
                        'reviewer_id' => $previous['reviewer_id'], 'rule_assessment' => $classification]);
                }
                AiAnalysis::create(['discovered_post_id' => $post->id, 'provider' => 'rules', 'model' => QualifyDiscoveredPost::VERSION,
                    'prompt_version' => QualifyDiscoveredPost::VERSION, 'status' => 'completed', 'score' => $classification['score'], 'output' => $output, 'analyzed_at' => now()]);
                if ($output['status'] === 'uncertain') {
                    $uncertain++;
                } elseif ($output['status'] === 'rejected') {
                    $rejected++;
                } else {
                    $qualified++;
                    $lead = Lead::firstOrCreate(['discovered_post_id' => $post->id], ['intent' => $classification['intent'],
                        'service_category' => $classification['service_category'], 'score' => $classification['score'],
                        'priority' => $classification['score'] >= 80 ? 'high' : 'medium', 'status' => 'new',
                        'rationale' => $classification['rationale'].' '.json_encode($classification['evidence'], JSON_UNESCAPED_UNICODE)]);
                    $added += (int) $lead->wasRecentlyCreated;
                }
            }
            DB::table('search_keywords')->where('id', $run->search_keyword_id)->update(['last_ran_at' => now(), 'updated_at' => now()]);
            DB::table('discovery_runs')->where('id', $run->id)->update(['status' => 'completed',
                'fetched_count' => $result['received'] - $result['plan_notices'], 'added_count' => $added,
                'relevant_count' => $qualified, 'excluded_own_count' => $result['excluded_own'], 'uncertain_count' => $uncertain,
                'rejected_count' => $rejected, 'error_code' => null, 'error_summary' => null, 'provider_poll_after' => null,
                'finished_at' => now(), 'updated_at' => now()]);
        });
    }
}
