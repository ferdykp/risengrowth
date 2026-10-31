<?php

namespace App\Services\Discovery;

use App\Models\AiAnalysis;
use App\Models\DiscoveredPost;

class RecordOpportunityAssessment
{
    public function __construct(private readonly QualifyDiscoveredPost $qualifier) {}

    /** @return array<string, mixed> */
    public function record(DiscoveredPost $post, string $keyword, string $source, int $keywordId, int $runId): array
    {
        $classification = $this->qualifier->classify($post->body ?? '', $keyword);
        $output = $classification + ['source' => $source, 'keyword_id' => $keywordId, 'run_id' => $runId];
        $previous = $post->analyses()->first()?->output;
        if (isset($previous['reviewer_id'])) {
            $output = array_merge($output, array_intersect_key($previous, array_flip(['status', 'rationale', 'reviewer_id', 'category', 'review_status', 'intent'])), ['rule_assessment' => $classification]);
        }
        AiAnalysis::create(['discovered_post_id' => $post->id, 'provider' => 'rules', 'model' => QualifyDiscoveredPost::VERSION,
            'prompt_version' => QualifyDiscoveredPost::VERSION, 'status' => 'completed', 'score' => $classification['score'], 'output' => $output, 'analyzed_at' => now()]);

        return $output;
    }
}
