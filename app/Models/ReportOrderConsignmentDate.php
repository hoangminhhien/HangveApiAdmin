<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ReportOrderConsignmentDate
 * @package App\Models
 */
class ReportOrderConsignmentDate extends Model
{
    use Filterable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public $fillable = [
        'staff_group_id',
        'count_order',
        'order_cost',
        'order_fee',
        'international_shipping_cost',
        'woodworking_cost',
        'shock_proof_cost',
        'storage_cost',
        'insurance_cost',
        'inspection_cost',
        'china_shipping_cost',
        'order_cost_old',
        'china_shipping_cost_old',
        'date',
        'type',
        'week',
        'month',
        'year',
        'official_service_fee'
    ];

    protected $table = 'report_order_consignment_date';

    protected $casts = [
        'order_cost' => 'double',
        'order_fee' => 'double',
        'international_shipping_cost' => 'double',
        'woodworking_cost' => 'double',
        'shock_proof_cost' => 'double',
        'storage_cost' => 'double',
        'insurance_cost' => 'double',
        'inspection_cost' => 'double',
        'china_shipping_cost' => 'float',
        'order_cost_old' => 'double',
        'china_shipping_cost_old' => 'double',
    ];

    public function getPrefixRoute(): ?string
    {
        return $this->_prefixRoute ?? null;
    }

    public function staffGroup(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_group_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_group_id', 'id');
    }
}
