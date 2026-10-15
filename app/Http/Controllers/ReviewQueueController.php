<?php

namespace App\Http\Controllers;

use App\Models\CommentApproval;
use App\Models\CommentDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewQueueController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate(['status' => ['sometimes', Rule::in(['all', 'draft', 'pending_review', 'approved', 'rejected'])]])['status'] ?? 'all';

        $drafts = CommentDraft::query()
            ->where('author_id', $request->user()->id)
            ->with('lead.post')
            ->when($filter !== 'all', fn ($query) => $query->where('status', $filter))
            ->latest()->paginate(10)->withQueryString();

        return view('reviews.index', [
            'drafts' => $drafts,
            'filter' => $filter,
            'counts' => CommentDraft::query()->where('author_id', $request->user()->id)
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'recentApprovals' => CommentApproval::query()->where('reviewer_id', $request->user()->id)
                ->with('draft.lead.post')->latest('decided_at')->limit(8)->get(),
        ]);
    }

    public function update(Request $request, CommentDraft $draft): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:10', 'max:2000']]);
        $body = trim($validated['body']);
        DB::transaction(function () use ($request, $draft, $body) {
            $locked = CommentDraft::query()->lockForUpdate()->findOrFail($draft->id);
            abort_unless($locked->author_id === $request->user()->id, 404);
            abort_unless(in_array($locked->status, ['draft', 'pending_review'], true), 409, 'Komentar yang sudah diputuskan tidak dapat diedit. Buat draft baru.');
            $locked->update(['body' => $body, 'content_sha256' => hash('sha256', $body), 'status' => 'draft']);
        });
        return back()->with('success', 'Draft diperbarui. Ajukan review kembali untuk meminta persetujuan.');
    }

    public function submit(Request $request, CommentDraft $draft): RedirectResponse
    {
        DB::transaction(function () use ($request, $draft) {
            $locked = CommentDraft::query()->lockForUpdate()->findOrFail($draft->id);
            abort_unless($locked->author_id === $request->user()->id, 404);
            abort_unless($locked->status === 'draft', 409, 'Hanya draft yang bisa diajukan.');
            $locked->update(['status' => 'pending_review']);
        });
        return back()->with('success', 'Komentar masuk antrean review.');
    }

    public function decide(Request $request, CommentDraft $draft): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $draft, $validated) {
            $locked = CommentDraft::query()->with('lead.post')->lockForUpdate()->findOrFail($draft->id);
            abort_unless($locked->author_id === $request->user()->id, 404);
            abort_unless($locked->status === 'pending_review', 409, 'Hanya draft yang menunggu review dapat diputuskan.');
            $post = $locked->lead?->post;
            if ($validated['decision'] === 'approved') {
                abort_unless($post && filled($post->external_post_id) && filled($post->platform), 422, 'Postingan tujuan belum valid.');
            }
            $hash = hash('sha256', $locked->body);
            CommentApproval::query()->create([
                'comment_draft_id' => $locked->id,
                'reviewer_id' => $request->user()->id,
                'decision' => $validated['decision'],
                'approved_sha256' => $validated['decision'] === 'approved' ? $hash : null,
                'approved_target_post_id' => $validated['decision'] === 'approved' ? $post->external_post_id : null,
                'approved_platform' => $validated['decision'] === 'approved' ? $post->platform : null,
                'approved_body_snapshot' => $validated['decision'] === 'approved' ? $locked->body : null,
                'reason' => $validated['reason'] ?? null,
                'decided_at' => now(),
            ]);
            $locked->update(['status' => $validated['decision']]);
        });
        return back()->with('success', $validated['decision'] === 'approved'
            ? 'Komentar disetujui dan tercatat. Belum dipublikasikan.'
            : 'Komentar ditolak dan tercatat.');
    }
}
