<?php

namespace App\Console\Commands;

use App\Jobs\RunPythonDiscoveryJob;
use App\Services\Discovery\RunPythonDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunPythonDiscoveryCommand extends Command
{
    protected $signature = 'growth:scan {--keyword= : Optional keyword ID} {--now : Run synchronously for testing} {--engine=python : python (configured web sources) or scrapling (Threads search)}';

    protected $description = 'Run configured self-hosted Python discovery sources';

    public function handle(): int
    {
        $engine = (string) $this->option('engine');
        if (! in_array($engine, ['python', 'scrapling'], true)) {
            $this->error('Engine must be python or scrapling.');

            return self::INVALID;
        }
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
                app(RunPythonDiscovery::class)->execute($keyword, $engine);
            } else {
                RunPythonDiscoveryJob::dispatch($keyword->id, $engine);
            }
        }
        $this->info('Processed/dispatched '.$keywords->count().' keyword(s).');

        return self::SUCCESS;
    }
}
