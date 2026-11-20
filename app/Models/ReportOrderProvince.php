<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ReportOrderConsignmentDate
 * @package App\Models
 */
class ReportOrderProvince extends BaseModel
{
    use Filterable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public $fillable = [
        'organization_id',
        'province_id',
        'total_customer',
        'total_customer_deposit',
        'total_order',
        'total_order_created',
        'total_order_deposit',
        'total_amount',
        'total_fee',
        'total_transport',
        'conversion_rate_customer',
        'conversion_rate_order',
        'date_report',
        'type'
    ];

    protected $table = 'report_order_province';

    public function getPrefixRoute(): ?string
    {
        return $this->_prefixRoute ?? null;
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
