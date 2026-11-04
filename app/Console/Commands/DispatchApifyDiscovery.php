<?php

namespace App\Console\Commands;

use App\Jobs\RunApifyDiscoveryJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DispatchApifyDiscovery extends Command
{
    protected $signature = 'growth:apify-dispatch';

    protected $description = 'Recover queued or stalled Apify polling jobs';

    public function handle(): int
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

        return self::SUCCESS;
    }
}
