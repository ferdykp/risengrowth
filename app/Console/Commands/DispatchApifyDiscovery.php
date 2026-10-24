<?php

namespace App\Console\Commands;

use App\Exceptions\ApifyDiscoveryException;
use App\Jobs\RunApifyDiscoveryJob;
use App\Services\Discovery\ApifyDiscoverySettings;
use App\Services\Discovery\RunApifyDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DispatchApifyDiscovery extends Command
{
    protected $signature = 'growth:apify-dispatch';

    protected $description = 'Dispatch due Apify keyword scans and recover queued or stalled polling jobs';

    public function handle(ApifyDiscoverySettings $settings, RunApifyDiscovery $discovery): int
    {
        $active = DB::table('discovery_runs')->where('source_type', 'apify')->whereIn('status', ['queued', 'running'])->orderBy('id')->limit(50)->get();
        foreach ($active as $run) {
            if (Carbon::parse($run->created_at)->addMinutes(30)->isPast()) {
                DB::table('discovery_runs')->where('id', $run->id)->whereIn('status', ['queued', 'running'])->update([
                    'status' => 'failed', 'error_code' => 'STALE_RUN', 'error_summary' => 'Antrean atau worker tertunda lebih dari 30 menit. Periksa worker; pulihkan memakai impor run Apify yang sama.',
                    'finished_at' => now(), 'updated_at' => now(),
                ]);
            } elseif (! $run->provider_poll_after || Carbon::parse($run->provider_poll_after)->isPast()) {
                RunApifyDiscoveryJob::dispatch($run->id);
            }
        }
        $config = $settings->get();
        if (! $config->enabled || ! $config->automation_enabled) {
            return self::SUCCESS;
        }
        $keywords = DB::table('search_keywords')->where('enabled', true)->where('platform', 'threads')->whereNull('deleted_at')
            ->orderBy('last_ran_at')->orderBy('id')->get();
        $dispatched = 0;
        foreach ($keywords as $keyword) {
            $latest = DB::table('discovery_runs')->where('source_type', 'apify')->where('search_keyword_id', $keyword->id)->latest('id')->first();
            $lastAttempt = $latest?->created_at;
            if ($lastAttempt && Carbon::parse($lastAttempt)->addMinutes(max($config->min_interval_minutes, $keyword->interval_minutes ?? $config->min_interval_minutes))->isFuture()) {
                continue;
            }
            try {
                $discovery->enqueue($keyword->id);
                if (++$dispatched >= $config->daily_runs) {
                    break;
                }
            } catch (ApifyDiscoveryException $exception) {
                if (in_array($exception->reason, ['DAILY_LIMIT', 'PROVIDER_PAUSED', 'TOKEN_MISSING'], true)) {
                    break;
                }
                $this->warn($exception->reason.': '.$exception->getMessage());
            }
        }
        $this->info('Apify: '.$dispatched.' keyword diproses untuk antrean.');

        return self::SUCCESS;
    }
}
