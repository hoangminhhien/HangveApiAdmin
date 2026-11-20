<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class CustomerOffer extends BaseModel
{
    use HasFactory;
    use LogsActivity;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    protected static $logAttributes = [
        "customer_id",
        "offer_rate",
        'consignment_rate',
        "deposit_rate",
        "level_rate",
        "trans_weight_hn_fee",
        "trans_weight_hcm_fee",
        "trans_weight_hp_fee",
        "trans_volumn_hn_fee",
        "trans_volumn_hcm_fee",
        "trans_volumn_hp_fee",

        "cn_trans_weight_hn_fee",
        "cn_trans_weight_hcm_fee",
        "cn_trans_volumn_hn_fee",
        "cn_trans_volumn_hcm_fee",

        'debt'
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'customer_offers';
}
