<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscoveredPost extends Model
{
    protected $guarded = [];
    protected $casts = ['posted_at' => 'datetime', 'first_discovered_at' => 'datetime', 'last_seen_at' => 'datetime'];

    public function analyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class)->latest();
    }
}
