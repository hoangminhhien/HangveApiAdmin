<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserExternal extends Model
{
    use HasFactory, HasSortDescByCreated, Filterable;

    protected $table = 'users_external';
    protected string $_tableNameFriendly = 'UserExternal';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable = ['user_id', 'source', 'user_name', 'user_password'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get prefix route thay the cho model
     * @return ?string
     */
    public function getPrefixRoute(): ?string
    {
        return $this->_prefixRoute ?? null;
    }
}
