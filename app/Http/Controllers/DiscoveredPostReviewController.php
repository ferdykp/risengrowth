<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscoveredPostReviewController extends Controller
{
    public function __invoke(Request $request, DiscoveredPost $post): RedirectResponse
    {
        $data = $request->validate([
            'qualification' => ['required', Rule::in(['qualified', 'uncertain', 'rejected'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($data, $post, $request): void {
            $post = DiscoveredPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
            AiAnalysis::create([
                'discovered_post_id' => $post->id, 'provider' => 'manual', 'model' => 'human',
                'prompt_version' => 'manual-v1', 'status' => 'completed',
                'output' => array_merge($post->analyses()->first()?->output ?? [], ['status' => $data['qualification'], 'rationale' => $data['reason'], 'reviewer_id' => $request->user()->id]),
                'analyzed_at' => now(),
            ]);
            if ($data['qualification'] === 'qualified') {
                $lead = Lead::firstOrCreate(['discovered_post_id' => $post->id]);
                $lead->update(['status' => 'qualified', 'manually_overridden' => true, 'rationale' => $data['reason']]);
            } else {
                Lead::where('discovered_post_id', $post->id)->update([
                    'status' => $data['qualification'] === 'rejected' ? 'dismissed' : 'reviewing',
                    'manually_overridden' => true, 'rationale' => $data['reason'],
                ]);
            }
        });

        return back()->with('success', 'Review tersimpan. Postingan dan riwayat tetap tersedia.');
    }
}
