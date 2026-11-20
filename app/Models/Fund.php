<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Fund extends BaseModel
{
    use Filterable, SoftDeletes;
    use LogsActivity;

    public $fillable = [
        'name',
        'organization_id',
        'type_currency',
        'unit_currency',
        'initial_balance',
        'initial_total_money_in',
        'initial_total_money_out',
        'total_money_in',
        'total_money_out',
        'current_balance',
        'note',
        'thuongdo_id',
        'status',
        'bank_id',
        'type_common',
        'warehouse_id',
        'bank_number',
        'organization_bank_id',
    ];

    public const CASH_TYPE_CURRENCY = 1; // Tiền mặt
    public const BANK_TYPE_CURRENCY = 2; // Tiền ngân hàng
    public const VIRTUAL_TYPE_CURRENCY = 3; // Tiền ảo

    public const VND_UNIT_CURRENCY = 1; // đơn vị VNĐ
    public const CHINESE_UNIT_CURRENCY = 2; // đơn vị Tệ

    // Loại quỹ là gì ?
    public const TYPE_COMMON_DEFAULT   = 0; // Quỹ mặc định
    public const TYPE_COMMON_WAREHOUSE = 1; // Quỹ kho

    protected $casts = ['current_balance'];

    protected $table = 'funds';
    public $timestamps = true;

    protected static $logAttributes = [
        'name',
        'organization_id',
        'type_currency',
        'unit_currency',
        'initial_balance',
        'initial_total_money_in',
        'initial_total_money_out',
        'total_money_in',
        'total_money_out',
        'current_balance',
        'note',
        'thuongdo_id',
        'status',
        'bank_id',
        'type_common',
        "warehouse_id"
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'funds';

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

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'id');
    }

    public function org_bank()
    {
        return $this->belongsTo(OrganizationBank::class, 'organization_bank_id', 'id');
    }
}
