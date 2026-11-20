<?php

namespace App\Traits\Relationships;

use App\Builders\AssignSupportOrderBuilders;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\SubOrderBuyder;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Query\Builder;

trait AssignSupportOrderRelationships
{
    /**
     * Customize eloquent query builder
     *
     * @param Builder $query
     *
     * @return AssignSupportOrderBuilders
     */
    public function newEloquentBuilder($query): AssignSupportOrderBuilders
    {
        return new AssignSupportOrderBuilders($query);
    }

    /**
     * Lấy người cần hỗ trợ (users)
     *
     * @return BelongsTo
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_id', 'id');
    }

    /**
     * Lấy người hỗ trợ (users)
     *
     * @return BelongsTo
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id', 'id');
    }

    /**
     * Lấy bản ghi tương ứng trong order_details
     *
     * @return BelongsTo
     */
    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_id', 'order_id');
    }

    /**
     * Lấy bản ghi tương ứng trong order_buyder_subs sau khi đặt hàng
     *
     * @return BelongsTo
     */
    public function orderBuyderSubs(): MorphOne
    {
        return $this->morphOne(SubOrderBuyder::class, 'sourceable');
    }

    /**
     * Lấy order_buyder_subs tương ứng với 1 order_detail
     * Chỉ gồm bản ghi của mình
     *
     * @return HasMany
     */
    public function subOrderBuyderSelf(): MorphOne
    {
        return $this->orderBuyderSubs()->where('staff_id', auth()->id());
    }

    /**
     * Tổng số sản phẩm có thể đặt hàng của nv được nhờ đặt hộ (là current auth user)
     * Bằng: (số sp được nhờ assign_support_orders.quantity) - (số sp của đơn nhờ này mà nv được nhờ đã đặt)
     *
     * @return int
     */
    public function totalAvailableQuantityAssignee(): int
    {
        $quantityAssigned = (int) $this->quantity;
        $quantityAssignedPlaced = $this->subOrderBuyderSelf()
            ->where('sourceable_id', $this->id)
            ->sum('quantity');

        return $quantityAssigned - $quantityAssignedPlaced;
    }

    /**
     * Lấy order_buyder tương ứng
     *
     * @return BelongsTo
     */
    public function orderBuyder(): BelongsTo
    {
        return $this->belongsTo(OrderBuyder::class, 'order_no', 'buyder_no');
    }
}
