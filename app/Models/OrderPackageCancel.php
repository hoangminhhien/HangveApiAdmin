<?php


namespace App\Models;

use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Helpers\AccountingHelper;
use App\Models\Traits\CustomerDeliveryRelation;
use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\StatusFilter;
use App\Models\Traits\OrderMorphRelation;
use App\Models\Traits\StaffServicesRelation;
use App\Models\Traits\WarehouseRelation;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use App\Services\FilterService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Traits\OrderPackageRelation;


class OrderPackageCancel extends BaseModel
{
    use HasFactory, WarehouseRelation, CustomerDeliveryRelation, StaffServicesRelation, CustomerRelation, OrderMorphRelation,
        HasSortDescByCreated, Filterable, StatusFilter, CreatedAtFilter, OrderPackageRelation;

    protected $table = 'order_package_cancels';
    protected $fillable = ['type_cancel_value', 'order_package_id', 'bill_code', 'customer_id', 'staff_id', 'staff_management_id', 'image', 'status'];

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }
    public function staff_managent(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_management_id', 'id');
    }

    public function scopeBillCode($query)
    {

        $billCode = request()->query('bill_code');
        if (!empty($billCode)) {
            return $query->where('bill_code', 'LIKE', '%' . $billCode . '%');
        }
        return $query;
    }
    public function scopeOrderCode($query)
    {
        $orderCode = request()->query('order_code');
        if (!empty($orderCode)) {
            return $query->whereHas('orderPackage', function ($q) {
                $q->where('order_code', request()->query('order_code'));
            });
        }
        return $query;
    }

}

