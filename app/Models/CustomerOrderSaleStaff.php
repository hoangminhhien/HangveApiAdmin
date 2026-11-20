<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerOrderSaleStaff extends Model
{
    use HasFactory;

    protected $fillable = [
        "total_second_customer_amount",
        "total_second_customer_service_change",
        "total_second_customer_google",
        "total_second_customer_facebook",
        "total_amount_all",
        "total_service_change_all",
        "total_first_amount_google_personal",
        "total_first_service_change_google_personal",
        "total_first_customer_google_personal",
        "total_first_amount_google_ads_personal",
        "total_first_service_change_google_ads_personal",
        "total_first_customer_google_ads_personal",
        "total_first_amount_facebook_ads_personal",
        "total_first_service_change_facebook_ads_personal",
        "total_first_customer_facebook_ads_personal",
        "total_first_amount_google_business",
        "total_first_service_change_google_business",
        "total_first_customer_google_business",
        "total_first_amount_google_ads_business",
        "total_first_service_change_google_ads_business",
        "total_first_customer_google_ads_business",
        "total_first_amount_facebook_ads_business",
        "total_first_service_change_facebook_ads_business",
        "total_first_customer_facebook_ads_business",
        "user_id",
        "user_name",
        "week",
        "month",
        "year",
    ];
}
