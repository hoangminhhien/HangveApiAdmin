<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class ReportCustomer
 * @package App\Models
 *
 * @property int $status_0
 */
class ReportCustomer extends BaseModel
{
    use HasFactory;
    use LogsActivity;

    public $fillable = [
        "customer_id",
        "orders_number",
        "orders_number_increment",
        "order_amount",
        "consignment_number",
        "packages_received_number",
        "packages_number",
        "init_total_deposit",
        "deposited_amount",
        "balance_amount",
        "blance_negative_last",
        "withdrawal_amount",
        "purchase_amount",
        "discount_amount",
        "thuongdo_id",
        "status_0",
        "status_1",
        "status_2",
        "status_8",
        "status_3",
        "status_4",
        "status_5",
        "status_6"
    ];
    
    protected $casts = [
        'balance_amount' => 'float',
        'order_amount' => 'float',
        'init_total_deposit' => 'float',
        'deposited_amount' => 'float',
        'withdrawal_amount' => 'float',
        'purchase_amount' => 'float',
        'discount_amount' => 'float',
    ];

    protected static $logAttributes = [
        "customer_id",
        "orders_number",
        "orders_number_increment",
        "order_amount",
        "consignment_number",
        "packages_received_number",
        "packages_number",
        "init_total_deposit",
        "deposited_amount",
        "balance_amount",
        "blance_negative_last",
        "withdrawal_amount",
        "purchase_amount",
        "discount_amount",
        "thuongdo_id",
        "status_0",
        "status_1",
        "status_2",
        "status_8",
        "status_3",
        "status_4",
        "status_5",
        "status_6"
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'report_customers';
}
