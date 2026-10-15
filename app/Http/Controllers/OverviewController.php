<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CommentDraft;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use Illuminate\Contracts\View\View;

class OverviewController extends Controller
{
    public function __invoke(): View
    {
        $leads = Lead::query();
        $metrics = [
            ['label' => 'Total prospek', 'value' => (clone $leads)->count(), 'detail' => 'Prospek ditemukan dan tersimpan'],
            ['label' => 'Prospek prioritas tinggi', 'value' => (clone $leads)->where('priority', 'high')->count(), 'detail' => 'Hasil scoring dengan prioritas tinggi'],
            ['label' => 'Menunggu review', 'value' => CommentDraft::query()->where('status', 'pending_review')->count(), 'detail' => 'Komentar yang memerlukan persetujuan'],
            ['label' => 'Postingan ditemukan', 'value' => DiscoveredPost::query()->count(), 'detail' => 'Postingan unik dalam database'],
        ];

        return view('overview', [
            'metrics' => $metrics,
            'newLeadsCount' => Lead::query()->where('status', 'new')->count(),
            'priorityLeads' => Lead::query()->with('post')->where('priority', 'high')->orderByDesc('score')->limit(5)->get(),
            'pendingDrafts' => CommentDraft::query()->with('lead.post')->where('status', 'pending_review')->latest()->limit(4)->get(),
            'recentActivities' => ActivityLog::query()->latest()->limit(6)->get(),
        ]);
    }
}
