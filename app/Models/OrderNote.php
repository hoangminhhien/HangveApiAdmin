<?php

namespace App\Models;

use App\Models\Traits\SubjectMorph;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderNote extends BaseModel
{
    use HasFactory, SubjectMorph, SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $dates = ['created_at'];

    protected $fillable = [
        'order_id',
        'supplier_id',
        'subject_type',
        'subject_id',
        'content',
        'is_public',
        'order_provisional_date',
        'thuongdo_id'
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function staffNote(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id', 'id');
    }

    public function subject()
    {
        return $this->morphTo();
    }

}
