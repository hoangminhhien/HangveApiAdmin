<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigLog extends Model
{
    use HasFactory;

    public $fillable = [
        'user_id',
        'type',
        'value_old',
        'value_new'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
