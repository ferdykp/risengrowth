<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    public const STAGES = ['discovered', 'reviewed', 'engaged', 'connected', 'opportunity', 'won', 'lost', 'dismissed'];

    public const CATEGORIES = ['community', 'potential_client', 'developer', 'freelancer', 'founder', 'agency'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['follow_up_on' => 'date'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
