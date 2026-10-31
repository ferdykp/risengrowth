<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentDraft extends Model
{
    public const PILLARS = ['developer_talks', 'behind_the_build', 'build_in_public', 'tech_made_simple', 'business_insights', 'soft_selling'];

    public const STATUSES = ['draft', 'ready', 'published', 'archived'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_on' => 'date'];
    }
}
