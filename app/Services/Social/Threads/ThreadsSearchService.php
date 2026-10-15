<?php

namespace App\Services\Social\Threads;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ThreadsSearchService
{
    public function search(
        SocialAccount $account,
        string $keyword,
        string $searchType = 'RECENT',
        int $limit = 25,
    ): array {
        if (
            $account->platform !== 'threads'
            || $account->status !== 'connected'
            || ($account->token_expires_at
                && $account->token_expires_at->isPast())
        ) {
            throw new RuntimeException(
                'Akun Threads tidak aktif atau token kedaluwarsa.'
            );
        }

        $token = $account->getAccessToken();

        if (blank($token)) {
            throw new RuntimeException(
                'Token Threads tidak tersedia.'
            );
        }

        if (! in_array($searchType, ['TOP', 'RECENT'], true)) {
            throw new RuntimeException('Search type tidak valid.');
        }

        $baseUrl = rtrim(
            config('services.threads.api_base_url', 'https://graph.threads.net'),
            '/'
        );

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->get($baseUrl . '/keyword_search', [
                'q' => $keyword,
                'search_type' => $searchType,
                'fields' => 'id,text,username,permalink,timestamp',
                'limit' => min(max($limit, 1), 100),
            ]);

        if ($response->failed()) {
            $code = $response->json('error.code')
                ?? $response->status();

            // Jangan sertakan body response atau token
            // dalam exception/log.
            throw new RuntimeException(
                'Threads API menolak pencarian. Kode: ' . $code
            );
        }

        $posts = $response->json('data');

        if (! is_array($posts)) {
            throw new RuntimeException(
                'Format hasil pencarian Threads tidak valid.'
            );
        }

        return array_values(array_filter(
            $posts,
            fn($post) => is_array($post)
                && filled($post['id'] ?? null)
        ));
    }
}
