<?php

namespace App\Models;

use App\Models\Traits\Filters\CreatedAtFilter;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeStaffRequest extends Model
{
    use HasFactory, CreatedAtFilter, HasSortDescByCreated, SoftDeletes;

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $table = 'change_staff_requests';

    protected $fillable = [
        'customer_id',
        'reason',
        'staff_name_request',
        'staff_group_id_old',
        'staff_group_id',
        'status',
        'updated_by',
    ];

    public function getPrefixRoute(): ?string
    {
        return $this->_prefixRoute ?? null;
    }

    public function staffGroup(): BelongsTo
    {
        return $this->belongsTo(User::class, "staff_group_id", "id");
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScopes();
    }

}
