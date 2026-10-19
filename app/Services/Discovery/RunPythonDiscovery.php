<?php

namespace App\Services\Discovery;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunPythonDiscovery
{
    public function __construct(private readonly QualifyDiscoveredPost $qualifier) {}

    /** @return array{fetched: int, added: int, linked: int} */
    public function execute(object $keyword): array
    {
        $lock = Cache::lock('python-discovery:'.$keyword->id, 120);
        if (! $lock->get()) {
            return ['fetched' => 0, 'added' => 0, 'linked' => 0];
        }

        try {
            return $this->run($keyword);
        } finally {
            $lock->release();
        }
    }

    /** @return array{fetched: int, added: int, linked: int} */
    private function run(object $keyword): array
    {
        $runId = DB::table('discovery_runs')->insertGetId([
            'social_account_id' => null, 'source_type' => 'python',
            'search_keyword_id' => $keyword->id, 'run_uuid' => (string) Str::uuid(),
            'status' => 'running', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $result = Process::path(base_path())->timeout(90)->run([
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
                'posts.*.permalink' => ['required', 'url:https', 'max:2048'],
                'posts.*.source' => ['required', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
                'posts.*.username' => ['nullable', 'string', 'max:191'],
                'posts.*.posted_at' => ['nullable', 'date'],
                'errors' => ['present', 'array'],
                'errors.*.source' => ['required', 'string', 'max:100'],
                'errors.*.error' => ['required', 'string', 'max:200'],
                'sources_configured' => ['required', 'integer', 'min:1'],
            ])->validate();
            if (! $result->successful() && ($result->exitCode() !== 2 || ! $decoded['errors'])) {
                throw new RuntimeException('Python worker exited unexpectedly');
            }
            if ($decoded['errors'] && ! $decoded['posts'] && count($decoded['errors']) >= $decoded['sources_configured']) {
                throw new RuntimeException('All configured discovery sources failed');
            }

            return DB::transaction(function () use ($decoded, $runId, $keyword): array {
                $added = 0;
                $relevant = 0;
                $linked = [];
                foreach ($decoded['posts'] as $entry) {
                    $post = DiscoveredPost::firstOrCreate([
                        'platform' => 'web', 'external_post_id' => $entry['external_post_id'],
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
                    $qualification = $this->qualifier->classify($post->body, $keyword->phrase);
                    $output = $qualification + ['source' => $entry['source'], 'keyword_id' => $keyword->id, 'run_id' => $runId];
                    $previous = $post->analyses()->first()?->output;
                    if (isset($previous['reviewer_id'])) {
                        $output['rule_assessment'] = $qualification;
                        $output['status'] = $previous['status'];
                        $output['rationale'] = $previous['rationale'];
                        $output['reviewer_id'] = $previous['reviewer_id'];
                    }
                    AiAnalysis::create([
                        'discovered_post_id' => $post->id, 'provider' => 'rules',
                        'model' => QualifyDiscoveredPost::VERSION, 'prompt_version' => QualifyDiscoveredPost::VERSION,
                        'status' => 'completed', 'score' => $qualification['score'],
                        'output' => $output,
                        'analyzed_at' => now(),
                    ]);
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
                    'fetched_count' => count($decoded['posts']), 'added_count' => $added, 'relevant_count' => $relevant,
                    'error_code' => $decoded['errors'] ? 'SOURCE_PARTIAL' : null,
                    'error_summary' => $decoded['errors'] ? json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE) : null,
                    'finished_at' => now(), 'updated_at' => now(),
                ]);

                return ['fetched' => count($decoded['posts']), 'added' => $added, 'linked' => count($linked)];
            });
        } catch (Throwable $exception) {
            DB::table('discovery_runs')->where('id', $runId)->update([
                'status' => 'failed', 'error_code' => 'PYTHON_WORKER_FAILED',
                'error_summary' => 'Discovery failed ('.$exception::class.'). Check worker configuration, source access and application logs.',
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            throw $exception;
        }
    }
}
