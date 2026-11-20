<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Group extends Model
{
    use HasFactory, HasSortDescByCreated, Filterable;

    protected string $_tableNameFriendly = 'Nhóm';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable = ['name', 'manager_id', 'admin_id'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class, 'id', 'admin_id')->whereIn('id', explode(',', $this->admin_id));
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_group', 'group_id', 'user_id');
    }

    public function usersMonth(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_group', 'group_id', 'user_id')->where('month', date('m/Y'));
    }

    public function usersLastMonth(): BelongsToMany
    {
        $lastMonth = Carbon::now()->subMonth()->endOfMonth()->format('m/Y');
        return $this->belongsToMany(User::class, 'users_group', 'group_id', 'user_id')->where('month', $lastMonth);
    }

    public function kpiMonths(): HasMany
    {
        return $this->hasMany(GroupKpi::class);
    }

    public function kpiMonth(): HasOne
    {
        return $this->hasOne(GroupKpi::class, 'group_id', 'id')->where('month', date('m/Y'));
    }

    public function kpiLastMonth(): HasOne
    {
        $lastMonth = Carbon::now()->subMonth()->endOfMonth()->format('m/Y');
        return $this->hasOne(GroupKpi::class, 'group_id', 'id')->where('month', $lastMonth);
    }

    public function scopeGroupId($query)
    {
        if (!empty(request()->query('group_id'))) {
            return $query->where("id", request()->query('group_id'));
        }
    }

    public function kpiNearest(): HasOne
    {
        return $this->hasOne(GroupKpi::class, 'group_id', 'id')
            ->orderByRaw("STR_TO_DATE(month, '%m/%Y') DESC");
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
