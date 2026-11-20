<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;

class CustomerPointTime extends BaseModel
{
    use Filterable;

    public $fillable = [
        'customer_id',
        'type',
        'case'
    ];

    protected $table = 'customer_point_times';
    public $timestamps = true;

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
}
