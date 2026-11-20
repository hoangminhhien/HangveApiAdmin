<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;

class ConfigShippingRange extends BaseModel
{
    use Filterable;

    public $fillable = [
        'value',
        'dislpay_order',
        'status',
        'config_shipping_time_id',
        'organization_id'
    ];

    public const CASH_TYPE_CURRENCY = 1; // Tiền mặt
    public const BANK_TYPE_CURRENCY = 2; // Tiền ngân hàng
    public const VIRTUAL_TYPE_CURRENCY = 3; // Tiền ảo

    public const VND_UNIT_CURRENCY = 1; // đơn vị VNĐ
    public const CHINESE_UNIT_CURRENCY = 2; // đơn vị Tệ

    protected $table = 'config_shipping_ranges';
    public $timestamps = true;

    public function scopeName($query)
    {
        return $query->where('name', request()->query('name'));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getTypeCurrency()
    {
        $list = [
            1 => 'Tiền mặt',
            2 => 'Tiền ngân hàng',
            3 => 'Tiền ảo'
        ];
        return array_key_exists($this->type_currency, $list) ? $list[$this->type_currency] : 'Không xác định';
    }

    public function getUnitCurrency()
    {
        $list = [
            1 => 'VND',
            2 => '¥'
        ];
        return array_key_exists($this->unit_currency, $list) ? $list[$this->unit_currency] : 'Không xác định';
    }
}
