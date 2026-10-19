<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Discovery\QualifyDiscoveredPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadInboxController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', Rule::in(['high', 'medium', 'low', 'unscored'])],
            'status' => ['nullable', Rule::in(['new', 'reviewing', 'qualified', 'contacted', 'converted', 'dismissed', 'saved', 'completed'])],
            'platform' => ['nullable', Rule::in(['threads', 'instagram', 'web'])],
            'service_category' => ['nullable', Rule::in(array_keys(QualifyDiscoveredPost::SERVICES))],
            'source' => ['nullable', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'min_score' => ['nullable', 'integer', 'between:0,100'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'score_high', 'score_low'])],
        ]);

        $query = Lead::query()->with('post')->whereHas('post');
        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $escaped = addcslashes($term, '%_\\');
            $query->whereHas('post', fn ($posts) => $posts
                ->where(fn ($p) => $p->where('body', 'like', "%{$escaped}%")
                    ->orWhere('username', 'like', "%{$escaped}%")));
        }
        if (filled($filters['priority'] ?? null)) {
            $query->where('priority', $filters['priority']);
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['platform'] ?? null)) {
            $query->whereHas('post', fn ($posts) => $posts->where('platform', $filters['platform']));
        }

        if (filled($filters['source'] ?? null)) {
            $query->whereHas('post.analyses', fn ($analyses) => $analyses->where('output->source', $filters['source']));
        }
        if (filled($filters['service_category'] ?? null)) {
            $query->where('service_category', $filters['service_category']);
        }
        if (filled($filters['min_score'] ?? null)) {
            $query->where('score', '>=', $filters['min_score']);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'score_high' => $query->orderByRaw('score IS NULL')->orderByDesc('score')->orderByDesc('id'),
            'score_low' => $query->orderByRaw('score IS NULL')->orderBy('score')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return view('leads.index', [
            'leads' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'totalLeads' => Lead::count(),
            'newLeads' => Lead::where('status', 'new')->count(),
            'highLeads' => Lead::where('priority', 'high')->count(),
        ]);
    }
}
