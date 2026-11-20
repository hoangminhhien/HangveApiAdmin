<?php

namespace App\Models;

use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\StatusFilter;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class CustomerWithdrawal
 * @package App\Models
 *
 * @property string $id
 * @property string $status
 * @property string $organization_id
 * @property string $customer_id
 * @property float $amount
 */
class CustomerWithdrawal extends BaseModel
{
    use HasFactory, Filterable, StatusFilter, CreatedAtFilter, CustomerRelation, HasSortDescByCreated;

    protected $table = 'customer_withdrawal';

    protected $_prefixRoute = 'transaction';

    protected string $_tableNameFriendly = 'Rút tiền';

    protected $casts = ['amount' => 'float'];

    protected $dates = ['created_at'];

    protected $appends = ['info'];

    public function getInfoAttribute(): string
    {
        return $this->account_number.','.$this->account_holder.','. $this->bank;
    }

    public function scopeCustomerCode($query)
    {
        return $query->whereHas('customer', function ($q) {
            $q->where('code', request()->query('customer_code'));
        });
    }

    public function scopePhoneNumber($query)
    {
        return $query->whereHas('customer', function ($q) {
            $q->where('phone_number', request()->query('phone_number'));
        });
    }

    public function scopeCreatedAtSort($query)
    {
        return $query->orderBy('created_at', request()->query('created_at_sort'));
    }

    public function scopeStaffName($query)
    {
        $staffName = request()->query('staff_name');
        if (!$staffName) {
            return $query;
        }
        return $query->whereHas('customer.staffGroup', function ($q) use ($staffName) {
            $q->where('name', 'like', "%{$staffName}%");
        });
    }
    public function scopeStaffId($query)
    {
        $staffId = request()->query('staff_id');
        if (!$staffId) {
            return $query;
        }

        return $query->whereHas('customer.staffGroup', function ($q) use ($staffId) {
            $q->where('id', $staffId);
        });
    }
}
