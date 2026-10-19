<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

class DiscoveryDoctor extends Command
{
    protected $signature = 'growth:doctor';

    protected $description = 'Check discovery prerequisites without querying external sources or changing data';

    public function handle(): int
    {
        $healthy = true;
        try {
            DB::connection()->getPdo();
            foreach (['jobs', 'failed_jobs', 'discovered_posts', 'discovery_runs', 'ai_analyses'] as $table) {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    throw new \RuntimeException('Missing required migration');
                }
            }
            $this->info('Database and discovery tables: OK');
        } catch (Throwable) {
            $this->error('Database unavailable or migrations missing. Check DB configuration and run migrations.');
            $healthy = false;
        }
        try {
            $sources = json_decode(file_get_contents(config('services.risengrowth_python.sources')), true, 32, JSON_THROW_ON_ERROR);
            $enabled = collect($sources['sources'] ?? [])->where('enabled', true);
            if ($enabled->isEmpty()) {
                throw new \RuntimeException('No active sources');
            }
            $this->info('Enabled source count: '.$enabled->count());
        } catch (Throwable) {
            $this->error('Source configuration is missing, invalid or has no enabled sources.');
            $healthy = false;
        }
        try {
            $result = Process::timeout(10)->run([config('services.risengrowth_python.bin'), '-c', 'import httpx, bs4; print("Python HTTP dependencies: OK")']);
            if (! $result->successful()) {
                throw new \RuntimeException('Python dependencies unavailable');
            }
            $this->info('Python HTTP dependencies: OK');
        } catch (Throwable) {
            $this->error('Python unavailable. Configure RISENGROWTH_PYTHON and install scraper/requirements.txt.');
            $healthy = false;
        }
        if (config('queue.default') !== 'database' || (int) config('queue.connections.database.retry_after') <= 110) {
            $this->error('Use QUEUE_CONNECTION=database and DB_QUEUE_RETRY_AFTER greater than 110 (default 150).');
            $healthy = false;
        } else {
            $this->info('Database queue timeout ordering: OK');
        }
        $this->line('Run queue workers and the scheduler separately. This check does not verify running processes, source permissions, Playwright browsers or live AI.');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
