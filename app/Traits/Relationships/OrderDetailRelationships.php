<?php

namespace App\Traits\Relationships;

use App\Builders\OrderDetailBuilders;
use App\Helpers\AccountingHelper;
use App\Helpers\MediaHelper;
use App\Models\AssignSupportOrder;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderDetailImage;
use App\Models\OrderDetailNote;
use App\Models\OrderDetailPackage;
use App\Models\OrderPackage;
use App\Models\SubOrderBuyder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

trait OrderDetailRelationships
{
    /**
     * Customize eloquent query builder
     *
     * @param Builder $query
     *
     * @return OrderDetailBuilders
     */
    public function newEloquentBuilder($query): OrderDetailBuilders
    {
        return new OrderDetailBuilders($query);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }


    public function getAmountAttribute(): float
    {
        return AccountingHelper::getCosts(
            $this->amount_cny * getExchangeRate($this->order_id)
        );
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->assignSupportOrders->sum('quantity') + $this->orderBuyderSubs->sum('quantity');
    }

    /**
     * Lấy tổng số sản phẩm đã đặt hàng
     *
     * @return int
     */
    public function getTotalNumberPlacedOrder(): int
    {
        return $this->orderBuyderSubs->sum('quantity');
    }

    public function getTotalNumberPlacedOrderAttribute(): int
    {
        return $this->orderBuyderSubs->sum('quantity');
    }

    public function getUnitPriceAttribute(): float
    {
        return AccountingHelper::getCosts($this->amount_cny * getExchangeRate($this->order_id));
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderDetailImage::class);
    }

    /**
     * Lấy ra order tương ứng
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getImage()
    {
        // Ban dầu trùng trait ImageAttribute
        $first_http = substr($this->image, 0, 4);
        if ($first_http == 'http') {
            return $this->image;
        }
        if ($first_http == '//im') {
            return 'https:' . $this->image;
        }
        if ($first_http == '//gd4') {
            return 'https:' . $this->image;
        }
        if ($first_http == '//gw') {
            return 'https:' . $this->image;
        }

        $value = $this->image;
        if (Str::isUuid($value)) {
            return MediaHelper::getDomain($value);
        }
        return MediaHelper::getFullUrlByValue($value);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderPackages(): belongsToMany
    {
        return $this->belongsToMany(OrderPackage::class, 'order_detail_packages', 'order_detail_id', 'order_package_id');
    }

    public function orderPackageDetail(): HasMany
    {
        return $this->HasMany(OrderDetailPackage::class, 'order_detail_id', 'id');
    }

    /**
     * Danh sách các đơn hàng nhờ từ bảng 'assign_support_orders'
     *
     * @return HasMany
     */
    public function assignSupportOrders(): HasMany
    {
        return $this->hasMany(AssignSupportOrder::class);
    }

    /**
     * Lấy các order_buyder_subs tương ứng với 1 order_detail
     * Gồm cả bản ghi của mình và các bản ghi nhờ đặt hộ
     *
     * @return HasMany
     */
    public function orderBuyderSubs(): MorphMany
    {
        return $this->morphMany(SubOrderBuyder::class, 'sourceable');
    }

    /**
     * Lấy các order_buyder_subs tương ứng với 1 order_detail
     * Chỉ gồm các bản ghi nhờ đặt hộ
     *
     * @return MorphMany
     */
    public function subOrderBuydersAssigned(): MorphMany
    {
        return $this->orderBuyderSubs()->where('staff_id', '!=', auth()->id());
    }

    public function orderDetailAssigned(): HasMany
    {
        return $this->hasMany(AssignSupportOrder::class, 'order_detail_id', 'id');
    }

    public function orderSubBuyerOrder(): HasMany
    {
        return $this->hasMany(SubOrderBuyder::class, 'sourceable_id', 'id');
    }

    /**
     * Lấy các order_buyder_subs tương ứng với 1 order_detail
     * Chỉ gồm bản ghi của mình
     *
     * @return MorphMany
     */
    public function subOrderBuyderSelf(): MorphMany
    {
        return $this->orderBuyderSubs()->where('staff_id', auth()->id());
    }

    public function item(): HasOne
    {
        return $this->hasOne(Item::class, 'num_iid', 'mp_id')->whereNotNull('buyder_data_last_time_sync');
        /*return $this->belongsTo(Item::class, 'link', 'detail_url');*/
    }

    /**
     * Tổng số sản phẩm đã đặt hàng của order_detail này
     *
     * @return int
     */
    public function totalPlacedQuantity(): int
    {
        return $this->orderBuyderSubs->sum('quantity');
    }

    /**
     * Tổng số sản phẩm còn có thể đặt hàng của order_detail này
     * Bằng: (tổng số sp khách đặt) - (tổng số sp đã assign) - (tổng số sp đã đặt của mình)
     *
     * @return int
     */
    public function totalAvailableQuantity(): int
    {
        $quantityCustomer = (int) $this->quantity;
        $quantityPlacedSelf = (int) $this->subOrderBuyderSelf->sum('quantity');
        $quantityAssigned = (int) $this->assignSupportOrders->sum('quantity');

        return $quantityCustomer - $quantityPlacedSelf - $quantityAssigned;
    }

    /**
     * Lấy các thông số liên quan tới số lượng sản phẩm của 1 order_detail
     *   + product_number_customer: số sp khách đặt
     *   + product_number_assigned: số sp đã nhờ
     *   + product_number_placed: số sp đã đặt (cả của mình và được nhờ)
     *   + product_number_assign_available: số sp có thể nhờ (tổng khách đặt - mình đã đặt - đã nhờ)
     *
     * @return int[]
     */
    public function getNumberInfo(): array
    {
        $productNumberCustomer = (int) $this->quantity;
        $productNumberAssigned = (int) $this->assignSupportOrders()->sum('quantity');
        $productNumberPlaced = (int) $this->orderBuyderSubs()->sum('quantity');

        $productNumberPlacedSelf = (int) $this->subOrderBuyderSelf()->sum('quantity');
        $productNumberAssignAvailable = $productNumberCustomer - $productNumberPlacedSelf - $productNumberAssigned;

        return [
            'product_number_customer' => $productNumberCustomer,
            'product_number_assigned' => $productNumberAssigned,
            'product_number_placed' => $productNumberPlaced,
            'product_number_assign_available' => $productNumberAssignAvailable,
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderDetailNote::class, 'order_detail_id', 'id');
    }
}
