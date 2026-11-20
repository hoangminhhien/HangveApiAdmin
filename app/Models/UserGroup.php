<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGroup extends BaseModel
{
    use HasFactory, HasSortDescByCreated, Filterable;

    protected string $_tableNameFriendly = 'Nhân viên nhóm';
    protected $table = 'users_group';
    protected $fillable = ['user_id', 'customer_id', 'kpi', 'kpi_sales', 'kpi_services', 'is_get_customer', 'real_sales',
        'kpi_transports', 'real_transports',
        'real_services', 'month', 'total_orders', 'group_id'];

    protected $casts = [
        'kpi_sales' => 'float',
        'kpi_services' => 'float',
        'kpi_transports' => 'float',
        'real_sales' => 'float',
        'real_services' => 'float',
        'real_transports' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'staff_group_id', 'user_id');
    }
}
