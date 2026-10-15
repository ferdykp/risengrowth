<?php

namespace App\Services\Discovery;

use App\Models\SocialAccount;
use App\Services\Social\Threads\ThreadsSearchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunThreadsDiscovery
{
    public function __construct(
        private readonly ThreadsSearchService $threads,
    ) {}

    public function execute(
        SocialAccount $account,
        object $keyword,
    ): array {
        $runId = DB::table('discovery_runs')->insertGetId([
            'social_account_id' => $account->id,
            'search_keyword_id' => $keyword->id,
            'run_uuid' => (string) Str::uuid(),
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // Request HTTP dilakukan di luar transaksi database.
            $posts = $this->threads->search(
                $account,
                $keyword->phrase,
                $keyword->search_type,
                25,
            );

            $stats = DB::transaction(
                function () use ($posts, $runId, $keyword): array {
                    $added = 0;
                    $linked = 0;

                    foreach ($posts as $post) {
                        $externalId = (string) $post['id'];

                        $postedAt = null;

                        if (filled($post['timestamp'] ?? null)) {
                            try {
                                $postedAt = Carbon::parse(
                                    $post['timestamp']
                                );
                            } catch (Throwable) {
                                $postedAt = null;
                            }
                        }

                        $body = is_string($post['text'] ?? null)
                            ? $post['text']
                            : null;

                        $postId = DB::table('discovered_posts')
                            ->where('platform', 'threads')
                            ->where('external_post_id', $externalId)
                            ->value('id');

                        if ($postId === null) {
                            // Unique index mencegah duplikasi
                            // antar-run secara database-level.
                            DB::table('discovered_posts')
                                ->insertOrIgnore([
                                    'platform' => 'threads',
                                    'external_post_id' => $externalId,
                                    'username' => $post['username'] ?? null,
                                    'body' => $body,
                                    'permalink' => $post['permalink'] ?? null,
                                    'posted_at' => $postedAt,
                                    'first_discovered_at' => now(),
                                    'last_seen_at' => now(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                            $postId = DB::table('discovered_posts')
                                ->where('platform', 'threads')
                                ->where('external_post_id', $externalId)
                                ->value('id');
                        }

                        if ($postId === null) {
                            continue;
                        }

                        DB::table('discovered_posts')
                            ->where('id', $postId)
                            ->update([
                                'last_seen_at' => now(),
                                'updated_at' => now(),
                            ]);

                        DB::table('discovery_run_posts')
                            ->insertOrIgnore([
                                'discovery_run_id' => $runId,
                                'discovered_post_id' => $postId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                        $linked++;

                        // Hanya post dengan isi teks yang dapat
                        // dibuat menjadi lead untuk analisis AI.
                        if (filled(trim((string) $body))) {
                            $inserted = DB::table('leads')
                                ->insertOrIgnore([
                                    'discovered_post_id' => $postId,
                                    'intent' => 'unclassified',
                                    'priority' => 'unscored',
                                    'status' => 'new',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                            if ($inserted > 0) {
                                $added++;
                            }
                        }
                    }

                    DB::table('search_keywords')
                        ->where('id', $keyword->id)
                        ->update([
                            'last_ran_at' => now(),
                            'updated_at' => now(),
                        ]);

                    return [
                        'fetched' => count($posts),
                        'linked' => $linked,
                        'added' => $added,
                    ];
                }
            );

            DB::table('discovery_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'completed',
                    'fetched_count' => $stats['fetched'],
                    'added_count' => $stats['added'],
                    // AI belum dijalankan: jangan
                    // mengarang relevant_count.
                    'relevant_count' => 0,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            return $stats;
        } catch (Throwable $exception) {
            DB::table('discovery_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'failed',
                    'error_code' => 'DISCOVERY_FAILED',
                    'error_summary' =>
                    'Pencarian gagal. Periksa izin API, '
                        . 'token, dan log aplikasi.',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }
}
