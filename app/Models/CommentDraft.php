<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommentDraft extends Model { protected $guarded = []; public function lead(): BelongsTo { return $this->belongsTo(Lead::class); } }
