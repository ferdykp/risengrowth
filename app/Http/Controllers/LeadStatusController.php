<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadStatusController extends Controller
{
    public function __invoke(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'reviewing', 'qualified', 'contacted', 'converted', 'dismissed', 'saved', 'completed'])],
            'operator_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($lead, $data, $request): void {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $previous = $lead->status;
            $lead->update($data + ['manually_overridden' => true]);
            $lead->activities()->create([
                'actor_id' => $request->user()->id, 'event' => 'lead_status_updated',
                'metadata' => json_encode(['from' => $previous, 'to' => $lead->status]),
            ]);
        });

        return back()->with('success', 'Status prospek tersimpan.');
    }
}
