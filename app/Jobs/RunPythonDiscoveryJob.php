<?php

namespace App\Jobs;

use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RunPythonDiscoveryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 900;

    public int $timeout = 110;

    public ?int $maxExceptions = null;

    /**
     * Declared with defaults (not promoted) so jobs queued before these fields existed still unserialize.
     */
    public string $engine = 'python';

    public ?string $postedFrom = null;

    public ?string $postedTo = null;

    public function __construct(public int $keywordId, string $engine = 'python', ?string $postedFrom = null, ?string $postedTo = null)
    {
        $this->engine = $engine;
        $this->postedFrom = $postedFrom;
        $this->postedTo = $postedTo;
        if ($engine === 'scrapling') {
            // Releases while waiting for the Threads cooldown count as attempts; a real failure is never retried.
            $this->tries = 30;
            $this->maxExceptions = 1;
        }
    }

    public function uniqueId(): string
    {
        return $this->engine.':'.$this->keywordId;
    }

    public function handle(RunPythonDiscovery $discovery): void
    {
        $keyword = DB::table('search_keywords')->where('id', $this->keywordId)
            ->where('enabled', true)->whereNull('deleted_at')->first();
        if ($keyword && $this->engine === 'scrapling' && ! $this->claimScraplingSlot()) {
            return;
        }
        if ($keyword) {
            $discovery->execute($keyword, $this->engine, $this->postedFrom, $this->postedTo);
        }
    }

    /**
     * Space Threads requests across all Scrapling jobs: claim the next slot, or release until it opens.
     */
    private function claimScraplingSlot(): bool
    {
        $gap = (int) config('services.scrapling.cooldown_seconds');
        if ($gap <= 0) {
            return true;
        }
        $nextAllowedAt = now()->timestamp + $gap + random_int(0, intdiv($gap, 2));
        if (Cache::add('scrapling-next-request-at', $nextAllowedAt, $nextAllowedAt - now()->timestamp)) {
            return true;
        }
        $this->release(max(1, (int) Cache::get('scrapling-next-request-at', 0) - now()->timestamp));

        return false;
    }
}
