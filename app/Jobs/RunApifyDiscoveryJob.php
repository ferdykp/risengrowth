<?php

namespace App\Jobs;

use App\Services\Discovery\RunApifyDiscovery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunApifyDiscoveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 110;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId)
    {
        $this->onConnection('database');
    }

    public function handle(RunApifyDiscovery $discovery): void
    {
        $discovery->advance($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('discovery_runs')->where('id', $this->runId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed', 'error_code' => 'APIFY_WORKER_FAILED',
            'error_summary' => 'Worker gagal atau timeout. Jangan memulai ulang Actor; periksa run Apify dan gunakan impor run yang sama.',
            'finished_at' => now(), 'updated_at' => now(),
        ]);
    }
}
