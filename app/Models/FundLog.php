<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;

class FundLog extends BaseModel
{
    use Filterable;

    protected $table = 'fund_log';

    public $fillable = [
        'type',
        'fund_id',
        'fund_name',
        'data',
        'user_id',
        'user_name',
        'organization_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id', 'id');
    }

    public function scopeUserName($query)
    {
        return $query->where('user_name', 'LIKE', '%'. request()->query('user_name') . '%');
    }

    public function scopeFundName($query)
    {
        return $query->where('fund_name', 'LIKE', '%'. request()->query('fund_name') . '%');
    }

    public function scopeType($query)
    {
        return $query->where('type', request()->query('type'));
    }

    public function scopeDate($query)
    {
        $date     = request()->query('date');
        $dates    = $date ? explode(',', $date) : [];

        return $query->where(function ($q) use ($dates) {
            return $q->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1]);
        });
    }
}
