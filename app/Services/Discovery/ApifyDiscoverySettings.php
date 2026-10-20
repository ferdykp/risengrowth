<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\DB;

class ApifyDiscoverySettings
{
    public function get(): object
    {
        return DB::table('discovery_provider_settings')->where('id', 1)->firstOrFail();
    }
}
