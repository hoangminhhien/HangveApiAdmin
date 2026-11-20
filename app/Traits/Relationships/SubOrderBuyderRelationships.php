<?php

namespace App\Traits\Relationships;

use App\Builders\OrderBuyderBuilders;
use App\Builders\OrderBuyderSubBuilders;
use App\Models\AssignSupportOrder;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\OrderPackage;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder;

trait SubOrderBuyderRelationships
{
    /**
     * Model 'booted' method
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(
            'without_canceled',
            fn (\Illuminate\Database\Eloquent\Builder $builder) => $builder->whereNull('close_reason')
        );
    }

    /**
     * Customize eloquent query builder
     *
     * @param Builder $query
     *
     * @return OrderBuyderBuilders
     */
    public function newEloquentBuilder($query): OrderBuyderSubBuilders
    {
        return new OrderBuyderSubBuilders($query);
    }

    /**
     * Lấy ra record tương ứng từ 'order_buyders'
     *
     * @return BelongsTo
     */
    public function orderBuyder(): BelongsTo
    {
        return $this->belongsTo(OrderBuyder::class);
    }

    /**
     * Lấy ra record tương ứng trong bảng 'order_details' hoặc 'assign_support_orders'
     *
     * @return BelongsTo
     */
    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Lấy bản ghi tương ứng trong bảng order_details
     *
     * @return OrderDetail
     */
    public function orderDetail(): OrderDetail
    {
        // $sourceable có thể là instance của OrderDetail hoặc AssignSupportOrder
        $sourceable = $this->sourceable;

        if ($sourceable instanceof OrderDetail) {
            return $sourceable;
        }

        $sourceable->loadMissing(['orderDetail']);
        return $sourceable->orderDetail;
    }

    public function orderDetailSub(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'sourceable_id', 'id');
    }

    /**
     * Lấy nhà cung cấp của đơn đặt hàng thông qua order_detail
     *
     * @return Supplier
     */
    public function supplier(): Supplier
    {
        // $sourceable có thể là instance của OrderDetail hoặc AssignSupportOrder
        return $this->orderDetailSub->supplier;
        /*$sourceable = $this->sourceable;

        if ($sourceable instanceof OrderDetail) {
            $sourceable->loadMissing('supplier');
            return $sourceable->supplier;
        } else {
            $sourceable->loadMissing('orderDetailSub.supplier');
            return $sourceable->orderDetailSub->supplier;
        }*/
    }

    /**
     * Lấy ra sản phẩm tương ứng từ 'items'
     *
     * @return BelongsTo
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'mp_id', 'buyder_data->mpId');
    }

    /**
     * Lấy ra nhân viên phụ trách đơn hàng
     *
     * @return BelongsTo
     */
    public function staffOrder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }

    /**
     * Lấy ra đơn hàng (order) tương ứng
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Lấy ra bản ghi tương ứng trong bảng 'order_package'
     *
     * @return HasOne
     */
    public function orderPackage(): HasOne
    {
        return $this->hasOne(OrderPackage::class, 'bill_code', 'logistic_number');
    }

    public function assignSupportOrders(): BelongsTo
    {
        return $this->belongsTo(AssignSupportOrder::class, 'sourceable_id', 'order_detail_id');
    }
}
