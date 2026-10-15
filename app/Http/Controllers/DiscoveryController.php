<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscoveryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'running', 'completed', 'failed', 'partial'])],
            'keyword' => ['nullable', 'integer', 'min:1'],
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
            ->join('social_accounts as a', 'r.social_account_id', '=', 'a.id')
            ->select('r.*', 'k.phrase as keyword_phrase', 'a.platform', 'a.username as account_username');

        if (filled($filters['status'] ?? null)) {
            $runsQuery->where('r.status', $filters['status']);
        }
        if (filled($filters['keyword'] ?? null)) {
            $runsQuery->where('r.search_keyword_id', (int) $filters['keyword']);
        }

        return view('discovery.index', [
            'accounts' => $accounts,
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
