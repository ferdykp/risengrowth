<?php

namespace App\Services\Discovery\Providers;

use App\Exceptions\ApifyDiscoveryException;
use App\Services\Discovery\PostNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ApifyThreadsProvider
{
    private const BASE_URL = 'https://api.apify.com/v2';

    public const ACTOR = 'openclawai~threads-scraper';

    public function __construct(private readonly PostNormalizer $normalizer) {}

    /** @return array{id: string, status: string, dataset_id: ?string, reported_usage_usd: ?float} */
    public function start(string $keyword, ?int $dailyLimit = null): array
    {
        if (trim($keyword) === '' || mb_strlen($keyword) > 191) {
            throw new ApifyDiscoveryException('INVALID_KEYWORD', 'Keyword wajib diisi, maksimal 191 karakter.');
        }
        $this->client();
        $lock = Cache::lock('apify-probe-start', 60);
        if (! $lock->get()) {
            throw new ApifyDiscoveryException('ALREADY_STARTING', 'Permintaan Actor lain sedang dimulai.');
        }
        try {
            if (Cache::has('apify-probe-paused')) {
                throw new ApifyDiscoveryException('PROVIDER_PAUSED', 'Probe dijeda setelah error kuota. Periksa billing sebelum menghapus cache apify-probe-paused.');
            }
            $limit = $dailyLimit === null ? max(1, min(2, (int) config('services.apify.probe_daily_runs'))) : max(1, min(10, $dailyLimit));
            $counter = 'apify-probe-starts:'.now()->utc()->format('Y-m-d');
            if ((int) Cache::get($counter, 0) >= $limit) {
                throw new ApifyDiscoveryException('DAILY_LIMIT', 'Batas probe harian tercapai. Gunakan --run untuk membaca run yang sudah ada.');
            }
            $charge = (float) config('services.apify.probe_max_charge_usd');
            $budget = (float) config('services.apify.monthly_budget_usd');
            if ($charge <= 0 || $charge > 0.05 || $budget <= $charge || $budget > 4.5) {
                throw new ApifyDiscoveryException('INVALID_BUDGET', 'Batas biaya probe harus >0 hingga USD 0.05 dan budget bulanan >biaya probe hingga USD 4.50.');
            }
            $usage = $this->request('GET', '/users/me/limits');
            $spent = data_get($usage, 'data.current.monthlyUsageUsd');
            $providerLimit = data_get($usage, 'data.limits.maxMonthlyUsageUsd');
            if (! is_numeric($spent) || ! is_numeric($providerLimit) || (float) $spent < 0 || (float) $providerLimit <= 0) {
                throw new ApifyDiscoveryException('USAGE_UNAVAILABLE', 'Pemakaian dan limit akun tidak dapat diverifikasi; Actor tidak dijalankan.');
            }
            Cache::put('apify-last-account-usage', ['usage_usd' => (float) $spent, 'limit_usd' => (float) $providerLimit, 'checked_at' => now()->toIso8601String()], now()->addDay());
            if ((float) $spent + $charge >= min($budget, (float) $providerLimit)) {
                throw new ApifyDiscoveryException('MONTHLY_BUDGET', 'Pemakaian akun mendekati batas budget; Actor tidak dijalankan.');
            }
            // Reserve before POST; uncertain network outcomes must not trigger a second charged run.
            Cache::put($counter, (int) Cache::get($counter, 0) + 1, now()->utc()->endOfDay()->addMinute());
            $query = http_build_query(['timeout' => 120, 'maxItems' => 10, 'maxTotalChargeUsd' => $charge, 'restartOnError' => 'false', 'waitForFinish' => 0]);
            $payload = $this->request('POST', '/actors/'.self::ACTOR.'/runs?'.$query, [
                'mode' => 'search_latest', 'queries' => [trim($keyword)], 'maxItems' => 10,
            ]);

            return $this->normalizeRun($payload);
        } finally {
            $lock->release();
        }
    }

    /** @return array{id: string, status: string, dataset_id: ?string, reported_usage_usd: ?float} */
    public function inspect(string $runId): array
    {
        $this->validateId($runId);
        $payload = $this->request('GET', '/actor-runs/'.$runId);
        $actorId = data_get($payload, 'data.actId');
        if (! is_string($actorId)) {
            throw new ApifyDiscoveryException('INVALID_RUN', 'Run tidak memiliki Actor ID.');
        }
        $actor = $this->request('GET', '/actors/'.self::ACTOR);
        if ($actorId !== data_get($actor, 'data.id')) {
            throw new ApifyDiscoveryException('WRONG_ACTOR', 'Run bukan milik Actor Threads yang dipilih.');
        }
        $run = $this->normalizeRun($payload);
        if ($run['id'] !== $runId) {
            throw new ApifyDiscoveryException('INVALID_RUN', 'Identitas respons run tidak cocok.');
        }

        return $run;
    }

    /** @param array<int, string> $excludedUsernames
     * @return array{posts: array<int, array<string, mixed>>, excluded_own: int, duplicates: int, received: int, plan_notices: int}
     */
    public function posts(string $runId, array $excludedUsernames, string $keyword = 'website'): array
    {
        $run = $this->inspect($runId);
        if ($run['status'] !== 'SUCCEEDED' || ! $run['dataset_id']) {
            throw new ApifyDiscoveryException('RUN_NOT_SUCCEEDED', 'Run belum SUCCEEDED; dataset belum digunakan. Status: '.$run['status']);
        }
        $entries = $this->request('GET', '/datasets/'.$run['dataset_id'].'/items?format=json&clean=true&limit=12');
        if (! array_is_list($entries) || count($entries) > 11) {
            throw new ApifyDiscoveryException('INVALID_DATASET', 'Dataset tidak berbentuk daftar atau melebihi 10 postingan dan 1 info paket.');
        }
        $posts = [];
        $own = 0;
        $duplicates = 0;
        $notices = 0;
        $postRows = 0;
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new ApifyDiscoveryException('INVALID_DATASET', 'Baris dataset bukan objek.');
            }
            if ($this->normalizer->isApifyPlanNotice($entry)) {
                if (++$notices > 1) {
                    throw new ApifyDiscoveryException('INVALID_DATASET', 'Dataset berisi informasi paket yang berulang.');
                }

                continue;
            }
            if (($entry['item_type'] ?? null) === 'post' && ++$postRows > 10) {
                throw new ApifyDiscoveryException('INVALID_DATASET', 'Dataset melebihi batas probe 10 postingan.');
            }
            if (! in_array($entry['item_type'] ?? null, ['hint', 'error'], true)
                && (($entry['mode'] ?? null) !== 'search_latest' || ($entry['source_input'] ?? null) !== $keyword)) {
                throw new ApifyDiscoveryException('WRONG_QUERY', 'Dataset bukan hasil search_latest untuk keyword yang diuji.');
            }
            $post = $this->normalizer->fromApify($entry, $excludedUsernames);
            if ($post === null) {
                $own++;

                continue;
            }
            if (isset($posts[$post['external_post_id']])) {
                $duplicates++;

                continue;
            }
            $posts[$post['external_post_id']] = $post;
        }

        return ['posts' => array_values($posts), 'excluded_own' => $own, 'duplicates' => $duplicates, 'received' => count($entries), 'plan_notices' => $notices];
    }

    private function client(): PendingRequest
    {
        $token = config('services.apify.token');
        if (! is_string($token) || trim($token) === '') {
            throw new ApifyDiscoveryException('TOKEN_MISSING', 'APIFY_TOKEN belum diisi. Simpan di .env server, jangan kirim ke chat.');
        }

        return Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)
            ->withOptions(['allow_redirects' => false]);
    }

    /** @param array<string, mixed> $body
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        try {
            $client = $this->client();
            $response = $method === 'POST' ? $client->post(self::BASE_URL.$path, $body) : $client->get(self::BASE_URL.$path);
        } catch (ConnectionException) {
            throw new ApifyDiscoveryException('CONNECTION_FAILED', 'Koneksi Apify terputus. Jika sedang memulai Actor, run mungkin sudah dibuat; periksa Console sebelum mengulang.');
        }
        if (! $response->successful()) {
            if (in_array($response->status(), [402, 429], true)) {
                Cache::put('apify-probe-paused', true, now()->addDay());
                throw new ApifyDiscoveryException('QUOTA_OR_RATE_LIMIT', 'Apify menolak karena kredit, kuota atau rate limit. Probe dijeda; tidak ada retry otomatis.');
            }
            throw new ApifyDiscoveryException('HTTP_ERROR', 'Apify mengembalikan HTTP '.$response->status().'. Periksa token, Actor dan akses akun di Console.');
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new ApifyDiscoveryException('INVALID_JSON', 'Respons Apify bukan JSON objek/daftar yang valid.');
        }

        return $payload;
    }

    /** @param array<mixed> $payload
     * @return array{id: string, status: string, dataset_id: ?string, reported_usage_usd: ?float}
     */
    private function normalizeRun(array $payload): array
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['status'] ?? null)
            || ! in_array($data['status'], ['READY', 'RUNNING', 'SUCCEEDED', 'FAILED', 'TIMING-OUT', 'TIMED-OUT', 'ABORTING', 'ABORTED'], true)) {
            throw new ApifyDiscoveryException('INVALID_RUN', 'Respons run Apify tidak valid. Periksa Console sebelum memulai run baru.');
        }
        $this->validateId($data['id']);
        $datasetId = $data['defaultDatasetId'] ?? null;
        if ($datasetId !== null) {
            if (! is_string($datasetId)) {
                throw new ApifyDiscoveryException('INVALID_RUN', 'Dataset ID tidak valid.');
            }
            $this->validateId($datasetId);
        }
        $usage = $data['usageTotalUsd'] ?? null;

        return ['id' => $data['id'], 'status' => $data['status'], 'dataset_id' => $datasetId,
            'reported_usage_usd' => is_numeric($usage) && (float) $usage >= 0 ? (float) $usage : null];
    }

    private function validateId(string $id): void
    {
        if (! preg_match('/\A[a-zA-Z0-9]{1,64}\z/', $id)) {
            throw new ApifyDiscoveryException('INVALID_ID', 'Identitas Apify tidak valid.');
        }
    }
}
