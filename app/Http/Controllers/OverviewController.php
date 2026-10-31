<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CommentDraft;
use App\Models\Lead;
use App\Models\Relationship;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $active = Lead::query()->whereIn('status', ['new', 'reviewing', 'qualified', 'saved']);
        $due = Relationship::where('user_id', $request->user()->id)
            ->whereNotIn('stage', ['won', 'lost', 'dismissed'])
            ->whereDate('follow_up_on', '<=', now('Asia/Jakarta')->toDateString());
        $metrics = [
            ['label' => 'Peluang untuk ditinjau', 'value' => (clone $active)->count(), 'detail' => 'Pilih percakapan yang relevan'],
            ['label' => 'Business intent tinggi', 'value' => (clone $active)->where('priority', 'high')->where('intent', 'buyer_request')->count(), 'detail' => 'Masih memerlukan review manusia'],
            ['label' => 'Draft menunggu review', 'value' => CommentDraft::where('author_id', $request->user()->id)->where('status', 'pending_review')->count(), 'detail' => 'Review sebelum membalas sendiri'],
            ['label' => 'Follow-up jatuh tempo', 'value' => (clone $due)->count(), 'detail' => 'Hari ini dan yang terlewat · WIB'],
        ];

        return view('overview', [
            'metrics' => $metrics,
            'newLeadsCount' => Lead::query()->where('status', 'new')->count(),
            'priorityLeads' => (clone $active)->with(['post', 'post.analyses' => fn ($query) => $query->limit(1)])->orderByDesc('score')->orderByDesc('created_at')->limit(5)->get(),
            'pendingDrafts' => CommentDraft::query()->where('author_id', $request->user()->id)->with('lead.post')->where('status', 'pending_review')->latest()->limit(4)->get(),
            'recentActivities' => ActivityLog::query()->where('actor_id', $request->user()->id)->latest()->limit(6)->get(),
            'followUps' => (clone $due)->with('lead.post')->orderBy('follow_up_on')->limit(6)->get(),
            'networkingCount' => Relationship::where('user_id', $request->user()->id)->where('category', '!=', 'potential_client')->whereNotIn('stage', ['won', 'lost', 'dismissed'])->count(),
        ]);
    }
}
