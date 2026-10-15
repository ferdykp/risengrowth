<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Contracts\View\View;

class LeadDetailController extends Controller
{
    public function __invoke(Lead $lead): View
    {
        $lead->load([
            'post.analyses' => fn ($query) => $query->limit(5),
        ]);

        abort_unless($lead->post, 404);

        return view('leads.show', [
            'lead' => $lead,
            'drafts' => $lead->commentDrafts()->limit(5)->get(),
            'activities' => $lead->activities()->limit(10)->get(),
            'newLeads' => Lead::query()->where('status', 'new')->count(),
        ]);
    }
}
