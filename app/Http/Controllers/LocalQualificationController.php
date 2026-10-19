<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;
use App\Services\Discovery\LocalAiAssistant;
use Illuminate\Http\RedirectResponse;

class LocalQualificationController extends Controller
{
    public function __invoke(DiscoveredPost $post, LocalAiAssistant $assistant): RedirectResponse
    {
        $result = $assistant->qualify((string) $post->body);
        if ($result === null) {
            return back()->withErrors(['ai' => 'AI lokal tidak tersedia atau hasil tidak valid. Kualifikasi berbasis aturan tetap tersimpan.']);
        }
        $previous = $post->analyses()->first();
        AiAnalysis::create([
            'discovered_post_id' => $post->id, 'provider' => 'ollama', 'model' => config('services.ollama.model'),
            'prompt_version' => 'advisory-v1', 'status' => 'completed', 'score' => $previous?->score,
            'output' => array_merge($previous?->output ?? ['status' => 'uncertain'], ['local_ai' => $result]),
            'analyzed_at' => now(),
        ]);

        return back()->with('success', 'Saran AI tersimpan. Review manual diperlukan untuk mengubah kualifikasi.');
    }
}
