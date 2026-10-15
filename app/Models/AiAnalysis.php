<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    protected $guarded = [];
    protected $casts = ['output' => 'array', 'analyzed_at' => 'datetime'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(DiscoveredPost::class, 'discovered_post_id');
    }
}
