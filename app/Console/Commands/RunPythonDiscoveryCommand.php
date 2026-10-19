<?php

namespace App\Console\Commands;

use App\Jobs\RunPythonDiscoveryJob;
use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunPythonDiscoveryCommand extends Command
{
    protected $signature = 'growth:scan {--keyword= : Optional keyword ID} {--now : Run synchronously for testing}';

    protected $description = 'Run configured self-hosted Python discovery sources';

    public function handle(): int
    {
        $query = DB::table('search_keywords')->where('enabled', true)->whereNull('deleted_at');
        if ($this->option('keyword')) {
            $query->where('id', (int) $this->option('keyword'));
        }
        $keywords = $query->orderBy('last_ran_at')->orderBy('id')->get()
            ->filter(fn ($keyword) => $this->option('keyword') || ! $keyword->last_ran_at
                || Carbon::parse($keyword->last_ran_at)->addMinutes($keyword->interval_minutes ?? 60)->isPast())
            ->take(50);
        foreach ($keywords as $keyword) {
            if ($this->option('now')) {
                app(RunPythonDiscovery::class)->execute($keyword);
            } else {
                RunPythonDiscoveryJob::dispatch($keyword->id);
            }
        }
        $this->info('Processed/dispatched '.$keywords->count().' keyword(s).');

        return self::SUCCESS;
    }
}
