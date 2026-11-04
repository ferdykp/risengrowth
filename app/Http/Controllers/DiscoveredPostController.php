<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscoveredPostController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(['business', 'community', 'not_relevant'])],
            'qualification' => ['nullable', Rule::in(['qualified', 'uncertain', 'rejected'])],
        ]);

        $latestAnalysisIds = AiAnalysis::query()->selectRaw('MAX(id)')->groupBy('discovered_post_id');
        $posts = DiscoveredPost::query()->with(['analyses' => fn ($query) => $query->whereIn('id', $latestAnalysisIds)]);

        if (filled($filters['category'] ?? null)) {
            $posts->whereHas('analyses', fn ($query) => $query->whereIn('id', $latestAnalysisIds)
                ->where('output->category', $filters['category']));
        }

        if (filled($filters['qualification'] ?? null)) {
            $posts->whereHas('analyses', fn ($query) => $query->whereIn('id', $latestAnalysisIds)
                ->where('output->status', $filters['qualification']));
        }

        return view('discovery.posts', [
            'posts' => $posts->orderByDesc('first_discovered_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'totalDiscovered' => DB::table('discovered_posts')->count(),
            'totalAnalyzed' => AiAnalysis::query()->whereIn('id', $latestAnalysisIds)->count(),
        ]);
    }
}
