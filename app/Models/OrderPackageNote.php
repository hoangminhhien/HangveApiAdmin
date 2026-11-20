<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderPackageNote extends BaseModel
{
    use HasFactory, SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function staff(): MorphTo
    {
        return $this->morphTo(null , 'cause_type', 'cause_id', 'id');
    }

    public function cause(): MorphTo
    {
        return $this->morphTo();
    }
}
