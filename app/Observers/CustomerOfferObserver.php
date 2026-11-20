<?php

namespace App\Observers;

use App\Constants\ReportConstant;
use App\Helpers\RandomHelper;
use App\Models\CustomerOffer;
use App\Models\ReportLevel;
use App\Services\ActivityService;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\ReportService;

class CustomerOfferObserver
{
    /**
     * Handle the CustomerOffer "created" event.
     *
     * @param  \App\Models\CustomerOffer  $customerOffer
     * @return void
     */
    public function created(CustomerOffer $customerOffer)
    {
        if ($customerOffer->level_rate >= 1) {
            // Đồng bộ level sang bảng customer
            $customer = $customerOffer->customer;
            if($customer){
                $customer->level = $customerOffer->level_rate;
                $customer->save();
            }
        }
    }

    /**
     * Handle the CustomerOffer "updated" event.
     *
     * @param  \App\Models\CustomerOffer  $customerOffer
     * @return void
     */
    public function updated(CustomerOffer $customerOffer)
    {
        \Log::info('CustomerOfferObserver updated');

        // update order fee, discount cost for order with status is waiting quote, waiting deposit
        if ($customerOffer->getOriginal('offer_rate') != $customerOffer->offer_rate) {
            (new OrderService())->updateFeeForOrderWaitingPurchase($customerOffer->customer_id);
        }
        if ($customerOffer->level_rate >= 1
            && $customerOffer->getOriginal('level_rate') != $customerOffer->level_rate) {
            // Đồng bộ level sang bảng customer
            $customer = $customerOffer->customer;
            if($customer){
                $customer->level = $customerOffer->level_rate;
                $customer->save();
            }
        }
        // set customer offer log
        (new ActivityService())->activitiesCustomerOffer($customerOffer);
        if($customerOffer->getOriginal('cn_trans_weight_hn_fee') != $customerOffer->cn_trans_weight_hn_fee ||
            $customerOffer->getOriginal('cn_trans_weight_hcm_fee') != $customerOffer->cn_trans_weight_hcm_fee ||
            $customerOffer->getOriginal('cn_trans_volumn_hn_fee') != $customerOffer->cn_trans_volumn_hn_fee ||
            $customerOffer->getOriginal('cn_trans_volumn_hcm_fee') != $customerOffer->cn_trans_volumn_hcm_fee
        ) {
            $weightFee = 0;
            $volumnFee = 0;
            $customer = $customerOffer->customer;
            if($customer) {
                $warehouse = $customer->warehouse;
                if($warehouse) {
                    // 28 - ID tỉnh Hà Nội
                    if($warehouse->province_id == 28) {
                        $weightFee = $customerOffer->cn_trans_weight_hn_fee;
                        $volumnFee = $customerOffer->cn_trans_volumn_hn_fee;
                    }
                    // 43 - ID tỉnh HCM
                    if($warehouse->province_id == 43) {
                        $weightFee = $customerOffer->cn_trans_weight_hcm_fee;
                        $volumnFee = $customerOffer->cn_trans_volumn_hcm_fee;
                    }
                }
                $data = [
                    'phone_number' => $customer->phone_number,
                    'weight_fee' => $weightFee,
                    'volumn_fee' => $volumnFee
                ];
                $job = new \App\Jobs\Quote\SyncCustonerOfferJob($data);
                dispatch($job)->onQueue('sync-data-report');
            }
        }
    }

    /**
     * Handle the CustomerOffer "deleted" event.
     *
     * @param  \App\Models\CustomerOffer  $customerOffer
     * @return void
     */
    public function deleted(CustomerOffer $customerOffer)
    {
        //
    }

    /**
     * Handle the CustomerOffer "restored" event.
     *
     * @param  \App\Models\CustomerOffer  $customerOffer
     * @return void
     */
    public function restored(CustomerOffer $customerOffer)
    {
        //
    }

    /**
     * Handle the CustomerOffer "force deleted" event.
     *
     * @param  \App\Models\CustomerOffer  $customerOffer
     * @return void
     */
    public function forceDeleted(CustomerOffer $customerOffer)
    {
        //
    }
}
