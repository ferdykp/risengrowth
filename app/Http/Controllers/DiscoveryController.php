<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Services\Discovery\ApifyDiscoverySettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscoveryController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'running', 'completed', 'failed', 'partial'])],
            'keyword' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'track_keyword' => ['nullable', 'integer', 'min:1'],
            'track_source' => ['nullable', Rule::in(['threads', 'python', 'apify'])],
            'track_run' => ['nullable', 'integer', 'min:1'],
            'qualification' => ['nullable', Rule::in(['qualified', 'uncertain', 'rejected'])],
        ]);

        $accounts = DB::table('social_accounts')
            ->select([
                'id',
                'platform',
                'username',
                'status',
                'token_expires_at',
                'last_verified_at',
            ])
            ->orderBy('platform')
            ->get();

        $keywords = DB::table('search_keywords')
            ->whereNull('deleted_at')->orderBy('phrase')->get();

        $runsQuery = DB::table('discovery_runs as r')
            ->join('search_keywords as k', 'r.search_keyword_id', '=', 'k.id')
            ->leftJoin('social_accounts as a', 'r.social_account_id', '=', 'a.id')
            ->select('r.*', 'k.phrase as keyword_phrase', 'r.source_type', 'a.platform', 'a.username as account_username');

        if (filled($filters['status'] ?? null)) {
            $runsQuery->where('r.status', $filters['status']);
        }
        if (filled($filters['keyword'] ?? null)) {
            $runsQuery->where('r.search_keyword_id', (int) $filters['keyword']);
        }

        $analyses = AiAnalysis::query()->with('post')
            ->whereIn('id', AiAnalysis::query()->selectRaw('MAX(id)')->groupBy('discovered_post_id'));
        if (filled($filters['qualification'] ?? null)) {
            $analyses->where('output->status', $filters['qualification']);
        }
        $postReviews = $analyses->latest('id')->paginate(15, ['*'], 'posts_page')->withQueryString();

        if ($request->expectsJson()) {
            $runs = (clone $runsQuery)->orderByDesc('r.id')->paginate(12)->withQueryString();
            $tracked = null;
            if (filled($filters['track_keyword'] ?? null) && filled($filters['track_source'] ?? null)) {
                $tracked = DB::table('discovery_runs')
                    ->when(filled($filters['track_run'] ?? null), fn ($query) => $query->where('id', $filters['track_run']),
                        fn ($query) => $query->where('id', '>', $filters['after_id'] ?? 0))
                    ->where('search_keyword_id', $filters['track_keyword'])->where('source_type', $filters['track_source'])
                    ->orderBy('id')->first(['id', 'source_type', 'status', 'fetched_count', 'added_count', 'relevant_count', 'excluded_own_count', 'uncertain_count', 'rejected_count', 'error_summary', 'started_at', 'finished_at']);
            }

            return response()->json([
                'reviews_html' => view('discovery.partials.post-reviews', ['postReviews' => $postReviews])->render(),
                'history_html' => view('discovery.partials.run-rows', ['runs' => $runs])->render(),
                'metrics' => [
                    'totalRuns' => DB::table('discovery_runs')->count(),
                    'completedRuns' => DB::table('discovery_runs')->where('status', 'completed')->count(),
                    'totalDiscovered' => DB::table('discovered_posts')->count(),
                    'activeKeywords' => $keywords->where('enabled', 1)->count(),
                ],
                'tracked' => $tracked,
            ])->header('Cache-Control', 'no-store, private');
        }

        $sourcePath = config('services.risengrowth_python.sources');
        $sourceConfig = is_readable($sourcePath) ? json_decode(file_get_contents($sourcePath), true) : [];
        $sourceConfig = is_array($sourceConfig) ? $sourceConfig : [];

        $apifySettings = app(ApifyDiscoverySettings::class)->get();
        $nextScans = [];
        $lastScans = DB::table('discovery_runs')->where('source_type', 'apify')->select('search_keyword_id')->selectRaw('MAX(created_at) as last_attempt')->groupBy('search_keyword_id')->pluck('last_attempt', 'search_keyword_id');
        foreach ($keywords as $keyword) {
            $last = $lastScans[$keyword->id] ?? null;
            $nextScans[$keyword->id] = $last ? Carbon::parse($last)->addMinutes(max($apifySettings->min_interval_minutes, $keyword->interval_minutes ?? 360)) : now();
        }

        return view('discovery.index', [
            'apifySettings' => $apifySettings,
            'apifyConfigured' => filled(config('services.apify.token')),
            'apifyUsage' => Cache::get('apify-last-account-usage'),
            'apifyPaused' => Cache::has('apify-probe-paused'),
            'apifyStartsToday' => (int) Cache::get('apify-probe-starts:'.now()->utc()->format('Y-m-d'), 0),
            'nextScans' => $nextScans,
            'accounts' => $accounts,
            'postReviews' => $postReviews,
            'activeSources' => collect($sourceConfig['sources'] ?? [])->where('enabled', true)->pluck('id'),
            'lastSuccessfulScan' => DB::table('discovery_runs')->whereIn('status', ['completed', 'partial'])->max('finished_at'),
            'keywords' => $keywords,
            'runs' => $runsQuery->orderByDesc('r.created_at')->orderByDesc('r.id')->paginate(12)->withQueryString(),
            'totalRuns' => DB::table('discovery_runs')->count(),
            'completedRuns' => DB::table('discovery_runs')->where('status', 'completed')->count(),
            'totalDiscovered' => DB::table('discovered_posts')->count(),
            'activeKeywords' => $keywords->where('enabled', 1)->count(),
            'filters' => $filters,
        ]);
    }
}
