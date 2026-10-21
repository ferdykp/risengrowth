<?php

namespace App\Services\Discovery;

use App\Exceptions\ApifyDiscoveryException;
use Illuminate\Support\Carbon;
use Throwable;

class PostNormalizer
{
    /** @param array<string, mixed> $entry */
    public function isApifyPlanNotice(array $entry): bool
    {
        return ($entry['platform'] ?? null) === 'threads'
            && ($entry['item_type'] ?? null) === 'hint'
            && ($entry['mode'] ?? null) === 'search_latest'
            && ($entry['source_input'] ?? null) === ''
            && ($entry['item_id'] ?? null) === null
            && ($entry['url'] ?? null) === null
            && ($entry['error_message'] ?? null) === 'Free Apify plan: this actor returns up to 10 results per run, without comment replies. Upgrade to any paid Apify plan for unlimited results and all modes.';
    }

    /** @param array<string, mixed> $entry */
    public function isApifyEmptySearchNotice(array $entry, string $keyword): bool
    {
        return ($entry['platform'] ?? null) === 'threads'
            && ($entry['item_type'] ?? null) === 'hint'
            && ($entry['mode'] ?? null) === 'search_latest'
            && ($entry['source_input'] ?? null) === $keyword
            && ($entry['item_id'] ?? null) === null
            && ($entry['url'] ?? null) === null
            && ($entry['error_message'] ?? null) === "No results for '".$keyword."' on Threads.";
    }

    /** @param array<string, mixed> $entry
     * @param  array<int, string>  $excludedUsernames
     * @return array{platform: string, external_post_id: string, username: string, body: string, permalink: string, posted_at: ?string}|null
     */
    public function fromApify(array $entry, array $excludedUsernames): ?array
    {
        if (in_array($entry['item_type'] ?? null, ['hint', 'error'], true)) {
            throw new ApifyDiscoveryException('ACTOR_OUTPUT_ERROR', 'Actor mengembalikan baris hint/error. Periksa run di Apify; ini bukan hasil pencarian kosong yang terverifikasi.');
        }
        if (($entry['platform'] ?? null) !== 'threads' || ($entry['item_type'] ?? null) !== 'post') {
            throw new ApifyDiscoveryException('INVALID_POST', 'Dataset Actor tidak sesuai kontrak postingan Threads.');
        }
        $id = $entry['item_id'] ?? null;
        $username = $entry['author_username'] ?? null;
        $body = $entry['description'] ?? $entry['title'] ?? null;
        $url = $entry['url'] ?? null;
        if ((! is_string($id) && ! is_int($id)) || ! preg_match('/\A[0-9]{1,64}\z/', (string) $id)
            || ! is_string($username) || ! preg_match('/\A[a-zA-Z0-9._]{1,191}\z/', $username)
            || ! is_string($body) || trim($body) === '' || mb_strlen($body) > 20000
            || ! is_string($url) || strlen($url) > 2048) {
            throw new ApifyDiscoveryException('INVALID_POST', 'Postingan Actor kehilangan identitas, penulis, URL atau teks yang valid.');
        }
        $parsed = parse_url($url);
        if (! is_array($parsed) || ($parsed['scheme'] ?? null) !== 'https'
            || ! in_array($parsed['host'] ?? null, ['threads.net', 'www.threads.net', 'threads.com', 'www.threads.com'], true)
            || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['port'])
            || ! preg_match('~\A/@([a-zA-Z0-9._]+)/post/([a-zA-Z0-9_-]+)/?\z~', $parsed['path'] ?? '', $matches)
            || strcasecmp($matches[1], $username) !== 0) {
            throw new ApifyDiscoveryException('INVALID_POST_URL', 'URL postingan Actor bukan permalink Threads yang valid untuk penulisnya.');
        }
        $excluded = array_map(fn (string $name): string => strtolower(ltrim(trim($name), '@')), $excludedUsernames);
        if (in_array(strtolower($username), $excluded, true)) {
            return null;
        }
        $postedAt = null;
        if (isset($entry['created_at'])) {
            try {
                if (! is_string($entry['created_at']) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T/', $entry['created_at'])) {
                    throw new \InvalidArgumentException;
                }
                $postedAt = Carbon::parse($entry['created_at'])->utc()->toIso8601String();
            } catch (Throwable) {
                throw new ApifyDiscoveryException('INVALID_POST_DATE', 'Tanggal postingan Actor tidak valid.');
            }
        }

        return [
            'platform' => 'threads', 'external_post_id' => (string) $id, 'username' => $username,
            'body' => trim($body), 'permalink' => 'https://'.$parsed['host'].$parsed['path'],
            'posted_at' => $postedAt,
        ];
    }
}
