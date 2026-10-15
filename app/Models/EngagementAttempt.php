<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CommentDraft::class, 'comment_draft_id');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(CommentApproval::class, 'comment_approval_id');
    }
}
