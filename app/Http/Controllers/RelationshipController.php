<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRelationshipRequest;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Relationship;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RelationshipController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'stage' => ['nullable', Rule::in(Relationship::STAGES)], 'due' => ['nullable', 'boolean']]);
        $relationships = Relationship::query()->where('user_id', $request->user()->id)->with('lead.post')
            ->when($filters['stage'] ?? null, fn ($query, $stage) => $query->where('stage', $stage))
            ->when($filters['due'] ?? false, fn ($query) => $query->whereDate('follow_up_on', '<=', now('Asia/Jakarta')->toDateString())->whereNotIn('stage', ['won', 'lost', 'dismissed']))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->whereHas('lead.post', fn ($posts) => $posts->where(fn ($post) => $post->where('username', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('body', 'like', '%'.addcslashes($term, '%_\\').'%'))))
            ->orderByRaw('follow_up_on IS NULL')->orderBy('follow_up_on')->latest('id')->paginate(12)->withQueryString();

        return view('leads.relationships', compact('relationships', 'filters'));
    }

    public function update(UpdateRelationshipRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $lead, $data): void {
            Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $relationship = Relationship::firstOrNew(['user_id' => $request->user()->id, 'lead_id' => $lead->id]);
            $previous = $relationship->stage;
            $relationship->fill(collect($data)->only(['category', 'stage', 'follow_up_on', 'notes'])->all())->save();
            ActivityLog::create(['actor_id' => $request->user()->id, 'lead_id' => $lead->id, 'event' => 'relationship_updated',
                'metadata' => json_encode(['from' => $previous, 'to' => $relationship->stage, 'interaction' => $data['interaction'] ?? null, 'follow_up_on' => $relationship->follow_up_on?->toDateString()])]);
        });

        return back()->with('success', 'Relasi dan follow-up tersimpan.');
    }
}
