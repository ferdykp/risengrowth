<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CommentApproval;
use App\Models\EngagementAttempt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'tab' => ['sometimes', Rule::in(['all', 'approvals', 'engagements', 'events'])],
            'status' => ['sometimes', Rule::in(['all', 'pending', 'publishing', 'published', 'failed', 'unknown', 'approved', 'rejected'])],
        ]);
        $tab = $validated['tab'] ?? 'all';
        $status = $validated['status'] ?? 'all';
        $userId = $request->user()->id;

        // Activity visibility is deliberately scoped to the signed-in user's actions.
        $approvalsQuery = CommentApproval::query()->where('reviewer_id', $userId);
        $attemptsQuery = EngagementAttempt::query()->where('triggered_by', $userId);
        $eventsQuery = ActivityLog::query()->where('actor_id', $userId);

        $approvalCount = (clone $approvalsQuery)->count();
        $attemptCount = (clone $attemptsQuery)->count();
        $publishedCount = (clone $attemptsQuery)->where('status', 'published')->whereNotNull('confirmed_at')->count();
        $failedCount = (clone $attemptsQuery)->whereIn('status', ['failed', 'unknown'])->count();

        // Separate paginated streams avoid an expensive mixed-table union, and keep types explicit.
        $approvals = $tab === 'all' || $tab === 'approvals'
            ? (clone $approvalsQuery)
                ->when(in_array($status, ['approved', 'rejected'], true), fn ($q) => $q->where('decision', $status))
                ->with('draft.lead.post')->latest('decided_at')->paginate(8, ['*'], 'approvals_page')->withQueryString()
            : null;

        $attempts = $tab === 'all' || $tab === 'engagements'
            ? (clone $attemptsQuery)
                ->when(in_array($status, ['pending', 'publishing', 'published', 'failed', 'unknown'], true), fn ($q) => $q->where('status', $status))
                ->with('draft.lead.post')->latest()->paginate(8, ['*'], 'attempts_page')->withQueryString()
            : null;

        $events = $tab === 'all' || $tab === 'events'
            ? (clone $eventsQuery)->latest()->paginate(8, ['*'], 'events_page')->withQueryString()
            : null;

        return view('activity.index', compact(
            'tab', 'status', 'approvals', 'attempts', 'events', 'approvalCount', 'attemptCount', 'publishedCount', 'failedCount'
        ));
    }
}
