<?php

namespace App\Http\Controllers;

use App\Http\Requests\CapturePostRequest;
use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Models\Lead;
use App\Services\Discovery\QualifyDiscoveredPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ManualCaptureController extends Controller
{
    public function store(CapturePostRequest $request, QualifyDiscoveredPost $qualifier): RedirectResponse
    {
        $data = $request->validated();
        $lead = DB::transaction(function () use ($request, $data, $qualifier): Lead {
            $parts = parse_url(trim($data['url']));
            parse_str($parts['query'] ?? '', $parameters);
            $parameters = array_filter($parameters, fn (string $key): bool => ! str_starts_with(strtolower($key), 'utm_'), ARRAY_FILTER_USE_KEY);
            ksort($parameters);
            $url = 'https://'.strtolower($parts['host']).($parts['path'] ?? '/').($parameters ? '?'.http_build_query($parameters) : '');
            $post = DiscoveredPost::where('platform', $data['platform'])->where('permalink', $url)->first();
            $post ??= DiscoveredPost::firstOrCreate(['platform' => $data['platform'], 'external_post_id' => 'manual-'.hash('sha256', $url)], [
                'username' => $data['username'] ?? null, 'body' => $data['body'], 'permalink' => $url,
                'first_discovered_at' => now(), 'last_seen_at' => now(),
            ]);
            $post->update(['last_seen_at' => now()] + (blank($post->body) ? ['body' => $data['body']] : []));
            $classification = $qualifier->classify($post->body, $data['keyword']);
            if (! $post->analyses()->exists()) {
                AiAnalysis::create(['discovered_post_id' => $post->id, 'provider' => 'manual_capture', 'model' => QualifyDiscoveredPost::VERSION,
                    'prompt_version' => QualifyDiscoveredPost::VERSION, 'status' => 'completed', 'score' => $classification['score'],
                    'output' => $classification + ['source' => 'manual_capture', 'captured_by' => $request->user()->id], 'analyzed_at' => now()]);
            }

            return Lead::firstOrCreate(['discovered_post_id' => $post->id], ['status' => 'reviewing', 'intent' => $classification['intent'],
                'score' => $classification['score'], 'priority' => 'unscored', 'service_category' => $classification['service_category'], 'rationale' => $classification['rationale']]);
        });

        return redirect()->route('leads.show', $lead)->with('success', 'Capture tersimpan untuk review. URL hanya menjadi referensi; halaman tidak diambil otomatis.');
    }
}
