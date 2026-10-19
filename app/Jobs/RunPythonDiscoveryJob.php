<?php

namespace App\Jobs;

use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RunPythonDiscoveryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 900;

    public int $timeout = 110;

    public function __construct(public int $keywordId) {}

    public function uniqueId(): string
    {
        return (string) $this->keywordId;
    }

    public function handle(RunPythonDiscovery $discovery): void
    {
        $keyword = DB::table('search_keywords')->where('id', $this->keywordId)
            ->where('enabled', true)->whereNull('deleted_at')->first();
        if ($keyword) {
            $discovery->execute($keyword);
        }
    }
}
