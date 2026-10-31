<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Relationship;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LeadDetailController extends Controller
{
    public function __invoke(Request $request, Lead $lead): View
    {
        $lead->load([
            'post.analyses' => fn ($query) => $query->limit(5),
        ]);

        abort_unless($lead->post, 404);

        return view('leads.show', [
            'lead' => $lead,
            'relationship' => Relationship::where('user_id', $request->user()->id)->where('lead_id', $lead->id)->first(),
            'drafts' => $lead->commentDrafts()->where('author_id', $request->user()->id)->limit(5)->get(),
            'activities' => $lead->activities()->where(fn ($query) => $query->where('event', '!=', 'relationship_updated')->orWhere('actor_id', $request->user()->id))->limit(10)->get(),
            'newLeads' => Lead::query()->where('status', 'new')->count(),
        ]);
    }
}
