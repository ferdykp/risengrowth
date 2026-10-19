<?php

namespace App\Services\Discovery;

class QualifyDiscoveredPost
{
    public const VERSION = 'rules-v1';

    public const SERVICES = [
        'integration' => ['integration', 'integrating', 'integrasi'],
        'inventory' => ['inventory', 'warehouse', 'inventori', 'gudang', 'stok'],
        'pos' => ['pos', 'cashier', 'kasir', 'point of sale'],
        'website' => ['website', 'web site', 'company profile', 'situs'],
        'maintenance' => ['maintenance', 'deployment', 'pemeliharaan'],
        'custom_system' => ['custom business system', 'business software', 'sistem bisnis', 'custom software', 'developer for my small business', 'developer for our business'],
    ];

    public function matches(string $body, string $phrase): bool
    {
        $parts = preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY);
        if (! $parts) {
            return false;
        }

        $pattern = implode('\s+', array_map(fn (string $part): string => preg_quote($part, '/'), $parts));

        return preg_match('/(?<![\p{L}\p{N}_])'.$pattern.'(?![\p{L}\p{N}_])/iu', $body) === 1;
    }

    /** @return array{status: string, intent: string, service_category: ?string, score: int, rationale: string, evidence: array<string, array<int, string>>} */
    public function classify(string $body, string $keyword): array
    {
        $services = [];
        foreach (self::SERVICES as $category => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->matches($body, $phrase)) {
                    $services[$category][] = $phrase;
                }
            }
        }
        $buyer = $this->evidence($body, [
            'looking for someone', 'looking for a developer', 'need a developer', 'need help',
            'looking for a custom', 'can anyone recommend', 'looking to hire',
            'butuh', 'membutuhkan', 'cari developer', 'mencari developer', 'cari jasa',
            'looking for a website', 'looking for website', 'looking for a pos',
            'need someone', 'need a website', 'need website', 'need a custom',
        ]);
        $negative = $this->evidence($body, [
            'we offer', 'i offer', 'my portfolio', 'our services', 'jasa pembuatan', 'kami menyediakan',
            'open to work', 'hire me', 'tutorial', 'step by step', 'lowongan', 'job vacancy',
            'we are hiring', 'we\'re hiring', 'full-time', 'full time', 'apply now',
            'show hn', 'launching my', 'kami menawarkan', 'framework', 'news', 'course', 'learning', 'belajar',
        ]);
        $relevant = $this->matches($body, $keyword);
        $score = ($relevant ? 20 : 0) + ($services ? 25 : 0) + ($buyer ? 55 : 0);
        $score = max(0, $score - ($negative ? 60 : 0));
        $status = ! $relevant || ($negative && ! $buyer) ? 'rejected'
            : ($services && $buyer && ! $negative ? 'qualified' : 'uncertain');
        $reason = match ($status) {
            'qualified' => 'Keyword, supported service and buyer intent are present.',
            'rejected' => ! $relevant ? 'Keyword does not match a complete word or phrase.' : 'Promotional, employment or educational intent without buyer evidence.',
            default => $negative ? 'Conflicting buyer and negative evidence; manual review required.' : 'Insufficient evidence of a buyer seeking a supported service; manual review required.',
        };

        return [
            'status' => $status,
            'intent' => $status === 'qualified' ? 'buyer_request' : ($status === 'rejected' ? 'not_buyer' : 'uncertain'),
            'service_category' => array_key_first($services),
            'score' => $score,
            'rationale' => $reason,
            'evidence' => ['keyword' => $relevant ? [$keyword] : [], 'services' => array_merge([], ...array_values($services)), 'buyer' => $buyer, 'negative' => $negative],
        ];
    }

    /** @param array<int, string> $phrases
     * @return array<int, string>
     */
    private function evidence(string $body, array $phrases): array
    {
        return array_values(array_filter($phrases, fn (string $phrase): bool => $this->matches($body, $phrase)));
    }
}
