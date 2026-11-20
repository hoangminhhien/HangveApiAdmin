<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ComplainAutoMessage extends BaseModel
{
    use HasFactory, Filterable, SoftDeletes;

    protected $table = 'complain_auto_message';

    protected string $_tableNameFriendly = 'Khiếu nại';

    
    public function scopeTitle($query)
    {
        return $query->whereHas(
            'title',
            function ($q) {
                return $q->where('title', request()->query('title'));
            }
        );
    }
}
