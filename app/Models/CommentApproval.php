<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentApproval extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CommentDraft::class, 'comment_draft_id');
    }
}
