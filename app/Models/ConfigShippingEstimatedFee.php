<?php

namespace App\Models;

class ConfigShippingEstimatedFee extends BaseModel
{
    protected $table = 'config_shipping_estimated_fee';

    protected $fillable = [
        'id',
        'category_code',
        'category_id',
        'category_name',
        'weight',
        'measurement_unit',
        'count_unit'
    ];
}
