<?php

namespace App\Services\Discovery;

use App\Exceptions\ThreadsSearchException;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Models\SocialAccount;
use App\Services\Social\Threads\ThreadsSearchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RunThreadsDiscovery
{
    public function __construct(private readonly ThreadsSearchService $threads, private readonly RecordOpportunityAssessment $assessment) {}

    /** @return array{fetched: int, linked: int, added: int} */
    public function execute(SocialAccount $account, object $keyword): array
    {
        $runId = DB::table('discovery_runs')->insertGetId([
            'source_type' => 'threads',
            'social_account_id' => $account->id,
            'search_keyword_id' => $keyword->id,
            'run_uuid' => (string) Str::uuid(),
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stage = 'search';
        $fetched = 0;

        try {
            $posts = $this->threads->search($account, $keyword->phrase, $keyword->search_type, 25);
            $fetched = count($posts);
            $stage = 'persistence';

            return DB::transaction(function () use ($posts, $runId, $keyword, $fetched): array {
                $added = 0;
                $qualified = 0;
                $linked = [];

                foreach ($posts as $post) {
                    $postedAt = null;
                    if (filled($post['timestamp'] ?? null)) {
                        try {
                            $postedAt = Carbon::parse($post['timestamp']);
                        } catch (Throwable) {
                            $postedAt = null;
                        }
                    }

                    $body = is_string($post['text'] ?? null) ? $post['text'] : null;
                    $storedPost = DiscoveredPost::query()->firstOrCreate([
                        'platform' => 'threads',
                        'external_post_id' => (string) $post['id'],
                    ], [
                        'username' => $post['username'] ?? null,
                        'body' => $body,
                        'permalink' => $post['permalink'] ?? null,
                        'posted_at' => $postedAt,
                        'first_discovered_at' => now(),
                        'last_seen_at' => now(),
                    ]);
                    $storedPost->update(['last_seen_at' => now()]);

                    if (! isset($linked[$storedPost->id])) {
                        DB::table('discovery_run_posts')->insert([
                            'discovery_run_id' => $runId,
                            'discovered_post_id' => $storedPost->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $linked[$storedPost->id] = true;
                    } else {
                        continue;
                    }

                    if (filled(trim((string) $storedPost->body))) {
                        $classification = $this->assessment->record($storedPost, $keyword->phrase, 'threads', $keyword->id, $runId);
                        $output = $classification;
                        $qualified += (int) ($output['status'] === 'qualified');
                        $lead = Lead::query()->firstOrCreate([
                            'discovered_post_id' => $storedPost->id,
                        ], [
                            'intent' => $classification['intent'],
                            'score' => $classification['score'],
                            'service_category' => $classification['service_category'],
                            'rationale' => $classification['rationale'],
                            'priority' => $classification['score'] >= 80 ? 'high' : 'low',
                            'status' => 'new',
                        ]);
                        $added += (int) $lead->wasRecentlyCreated;
                    }
                }

                DB::table('search_keywords')->where('id', $keyword->id)->update([
                    'last_ran_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('discovery_runs')->where('id', $runId)->update([
                    'status' => 'completed',
                    'fetched_count' => $fetched,
                    'added_count' => $added,
                    'relevant_count' => $qualified,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

                return ['fetched' => $fetched, 'linked' => count($linked), 'added' => $added];
            });
        } catch (Throwable $exception) {
            Log::error('Threads discovery run failed', [
                'run_id' => $runId,
                'stage' => $stage,
                'search_type' => $keyword->search_type,
                'fetched_count' => $fetched,
                'exception_type' => $exception::class,
            ]);

            try {
                DB::table('discovery_runs')->where('id', $runId)->update([
                    'status' => 'failed',
                    'fetched_count' => $fetched,
                    'error_code' => $exception instanceof ThreadsSearchException ? $exception->errorCode : ($stage === 'search' ? 'THREADS_SEARCH_FAILED' : 'DISCOVERY_STORAGE_FAILED'),
                    'error_summary' => $exception instanceof ThreadsSearchException ? $exception->getMessage() : ($stage === 'search'
                        ? 'Pencarian Threads gagal. Periksa koneksi, token, izin API, atau batas request.'
                        : 'Penyimpanan gagal; perubahan postingan dan lead dibatalkan.'),
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (Throwable $statusException) {
                Log::error('Threads discovery failure status unavailable', [
                    'run_id' => $runId,
                    'exception_type' => $statusException::class,
                ]);
            }

            throw $exception;
        }
    }
}
