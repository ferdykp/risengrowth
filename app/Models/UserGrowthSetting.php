<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGrowthSetting extends Model
{
    protected $fillable = ['user_id', 'discovery_enabled', 'discovery_interval_minutes', 'notify_on_new_leads'];

    protected function casts(): array
    {
        return ['discovery_enabled' => 'boolean', 'notify_on_new_leads' => 'boolean', 'discovery_interval_minutes' => 'integer'];
    }
}
