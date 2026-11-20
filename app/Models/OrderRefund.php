<?php

namespace App\Models;

use App\Services\FilterService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRefund extends BaseModel
{
    use HasFactory;

    protected $table = 'order_refunds';
    protected $fillable = [
        'order_id',
        'type_refund',
        'status',
        'images_create_cancel',
        'images_create_refund',
        'reason_create_refund',
        'reason_create_cancel',
        'china_shipping_cost_refund',
        'price_quantity_order_details',
        'reason_cancel',
        'money_refund',
        'money_cny_refund',
        'created_user_id',
        'order_cost_real',
        'china_shipping_cost_real',
        'money_refund_cn_origin',
        'inspectioned',
        'money_refund_from_xadal',
        'order_code_from_xadal'
    ];

    public const TYPE_CANCEL = 2;
    public const TYPE_REFUND = 1;
    public const STATUS_PROCESSING = 0;
    public const STATUS_PROCESSED = 1;

    public const STATUS_CANCEL = 2;
    public const INSPECTIONED = 1;
    public const UN_INSPECTION = 0;

    public function user()
    {
        return $this->belongsTo(Staff::class, 'created_user_id', 'id');
    }
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    public function scopeCreatedAt($query)
    {
        if (empty(request()->query('created_at'))) {
            return $query;
        }
        $dates = (new FilterService())->rangeDateFilterV2(request()->query('created_at'));
        return $query->whereBetween('created_at', [$dates[0] . ' 00:00:00', $dates[1] . ' 23:59:59']);
    }
}
