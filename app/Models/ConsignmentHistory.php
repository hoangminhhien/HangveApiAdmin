<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'old',
        'new',
        'content',
        'status',
        'user_id',
        'user_name',
        'consignment_id'
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class, 'consignment_id', 'id');
    }
}
