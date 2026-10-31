<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveContentDraftRequest;
use App\Models\ContentDraft;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentStudioController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(ContentDraft::STATUSES)]]);
        $drafts = ContentDraft::where('user_id', $request->user()->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('updated_at')->paginate(10)->withQueryString();

        return view('comments.studio', compact('drafts', 'filters'));
    }

    public function store(SaveContentDraftRequest $request): RedirectResponse
    {
        ContentDraft::create($request->safe()->except('confirm_published') + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Konten tersimpan. Publikasi dilakukan sendiri di platform tujuan.');
    }

    public function update(SaveContentDraftRequest $request, ContentDraft $contentDraft): RedirectResponse
    {
        $contentDraft->update($request->safe()->except('confirm_published'));

        return back()->with('success', 'Konten dan catatan performa diperbarui.');
    }
}
