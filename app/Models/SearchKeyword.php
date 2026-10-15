<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SearchKeyword extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id','platform','phrase','normalized_phrase','category','search_type','enabled','interval_minutes','notes'];

    protected function casts(): array
    {
        return ['enabled'=>'boolean','last_ran_at'=>'datetime','interval_minutes'=>'integer'];
    }
}
