<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteHistory extends Model
{
    use HasFactory;

    protected $table = 'note_histories';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'content',
        'organization_id',
        'created_at',
        'updated_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
