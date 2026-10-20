<?php

namespace App\Console\Commands;

use App\Exceptions\ApifyDiscoveryException;
use App\Models\SocialAccount;
use App\Services\Discovery\Providers\ApifyThreadsProvider;
use Illuminate\Console\Command;

class ProbeApifyDiscovery extends Command
{
    protected $signature = 'growth:apify-probe
        {--start : Start one credit-consuming bounded Actor run}
        {--run= : Read an existing run without starting another Actor}
        {--keyword=website : One keyword for this feasibility probe}';

    protected $description = 'Validate Apify Threads discovery without saving posts, creating leads or enabling schedules';

    public function handle(ApifyThreadsProvider $provider): int
    {
        if ((bool) $this->option('start') === filled($this->option('run'))) {
            $this->error('Pilih tepat satu: --start untuk satu run baru, atau --run=ID untuk melanjutkan pemeriksaan.');

            return self::FAILURE;
        }
        $keyword = (string) $this->option('keyword');
        try {
            $run = $this->option('start') ? $provider->start($keyword) : $provider->inspect((string) $this->option('run'));
            $this->info('Apify run ID: '.$run['id']);
            $this->line('Status: '.$run['status']);
            $this->line('Keyword yang diperiksa: '.$keyword);
            $this->line('Usage yang dilaporkan provider (USD): '.($run['reported_usage_usd'] === null ? 'belum tersedia' : number_format($run['reported_usage_usd'], 6, '.', '')));
            if (in_array($run['status'], ['READY', 'RUNNING', 'TIMING-OUT', 'ABORTING'], true)) {
                $this->warn('Belum selesai. Periksa ulang dengan --run='.$run['id'].' dan keyword yang sama; jangan ulangi --start. Tidak ada polling tanpa batas.');

                return self::SUCCESS;
            }
            if ($run['status'] !== 'SUCCEEDED') {
                $this->error('Actor tidak berhasil. Periksa run di Apify Console. Belum ada data yang diimpor.');

                return self::FAILURE;
            }
            $excluded = array_merge(config('services.apify.excluded_usernames', []), SocialAccount::query()
                ->where('platform', 'threads')->whereNotNull('username')->pluck('username')->all());
            $result = $provider->posts($run['id'], $excluded, $keyword);
            $this->table(['Baris diterima', 'Info paket', 'Akun sendiri dikecualikan', 'Duplikat', 'Postingan akun lain'], [[
                $result['received'], $result['plan_notices'], $result['excluded_own'], $result['duplicates'], count($result['posts']),
            ]]);
            if (! $result['posts']) {
                $this->error('Uji belum memenuhi syarat: tidak ada postingan valid dari akun lain. Penyimpanan dan otomasi belum diaktifkan.');

                return self::FAILURE;
            }
            $this->table(['Penulis', 'Post ID', 'URL'], array_map(fn (array $post): array => [
                '@'.$post['username'], $post['external_post_id'], $post['permalink'],
            ], $result['posts']));
            $this->info('Data akun lain terdeteksi. Periksa URL asli, relevansi dan billing run sebelum integrasi penyimpanan.');
            $this->line('Tidak ada postingan atau lead yang disimpan; tidak ada komentar yang dikirim. Usage run bukan jaminan biaya keseluruhan akun.');

            return self::SUCCESS;
        } catch (ApifyDiscoveryException $exception) {
            $this->error($exception->reason.': '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
