<?php

namespace App\Http\Resources\Customer;

use App\Constants\CustomerConstant;
use App\Constants\TimeConstant;
use App\Constants\ViaConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\CustomerDelivery\CustomerDeliveryResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\OnlyIdNameResource;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class CustomerLevelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $report = $this->report;
//        $customerIntroduceDB = $this->customerIntroduce;
//        $customerNotes = $this->customerNotes->map(function ($note) {
//            return [
//                'user'     => $note->user ? $note->user->name: "",
//                'content'  => $note->content,
//                'date'     => Carbon::parse($note->created_at)->format('d-m-Y H:i:s'),
//            ];
//        });

        $gender = $this->gender == '' ? CustomerConstant::GENDER_UNDEFINED : $this->gender;
        // dd($this);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'info' => [
                'name' => $this->name,
                //'email' => $this->email,
                'address' => optional(optional($this->warehouse)->province)->name,
                //'address_2' => optional($this->province)->name,
                // 'gender' => CustomerConstant::GENDER[$this->gender ?? CustomerConstant::GENDER_UNDEFINED], // Logic cũ
                'gender' => CustomerConstant::GENDER[$gender ?? CustomerConstant::GENDER_UNDEFINED],
                'phone_number' => $this->phone_number,
                'created_at' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_HI_DAY),
                'updated_at' => TimeHelper::format($this->updated_at, TimeConstant::DATETIME_BY_HI_DAY),
                // 'via' => $this->via ? ViaConstant::STATUSES[$this->via] : null, // Logic cũ
                //'via' => $this->via,
                'level' => $this->level,
                //'bod' => $this->bod,
                'warehouse_id' => $this->warehouse_id,
                'warehouse_cn_id' => $this->warehouse_cn_id,
                'service' => $this->service,
                'staff_care_id' => $this->staff_care_id,
                'staff_counselor_id' => $this->staff_counselor_id,
                'staff_group_id' => $this->staff_group_id,
//                'business_type' => [
//                    'value' => $this->business_type,
//                    'name' => CustomerConstant::CUSTOMER_BUSINESS_TYPE[$this->business_type] ?? null
//                ],
            ],

            'order' => [
                //'orders_number' => (int)($report->orders_number ?? 0),
                //'order_consignment_number' => (int)($report->consignment_number ?? 0),
                //'order_packages_number' => (int)($report->packages_number ?? 0),
                //'order_amount' => (int)($report->order_amount ?? 0),
                //'order_cost' => (int) $this->sumOrderCost,
                //'order_cost' => (int) $this->orderDeposit->sum('order_cost'),
                'total_order_cost_done' => $this->orderDone->sum('order_cost'), // Tổng giá trị đơn hàng: Gồm các đơn hàng đã hoàn thành hoặc đã đặt cọc
                'total_order_number_done' => $this->orderDone->count(), // Tổng số lượng đơn hàng: Gồm các đơn hàng đã hoàn thành hoặc đặt cọc
            ],
            'transaction' => [
                'deposited_amount' => (float)($report->deposited_amount ?? 0),
                'balance_amount' => (float)($report->balance_amount ?? 0),
            ],
            'status' => $this->status,
//            'staff' => [
//                // 'order' =>new OnlyIdNameResource($this->staffOrder),
//                // 'sale' => new OnlyIdNameResource($this->staffSale),
//                // 'care' => new OnlyIdNameResource($this->staffCare),
//                'group' => new OnlyIdNameResource($this->staffGroup),
//                // 'counselor' => new OnlyIdNameResource($this->staffCounselor),
//            ],
            //'label' => new OnlyIdNameResource($this->label),
            //'offer' => $this->offer,
            // 'delivery' => (new ListResource($this->delivery, CustomerDeliveryResource::class)),

            //'customer_introduce_id' => $this->customer_introduce_id,
            //'customer_introduce_name' => !is_null($customerIntroduceDB) ? $customerIntroduceDB->name : "",
            //'discount_by_kg' => $this->discount_by_kg,
            //'discount_by_m3' => $this->discount_by_m3,
            //'point_level' => $this->point_level,
            'created_at' =>  $this->created_at ? $this->created_at->format('d-m-Y') : null,
            //'date_order_last' => $this->getLastOrder(), // Sau cần xử lý lại cục này,
            'date_order_last' => !empty($this->last_order_at) ? Carbon::parse($this->last_order_at)->format('d-m-Y') : null,
            'international_shipping_cost' => $this->packages->sum('international_shipping_cost'),
            //'customer_category' => $this->customer_categories,
            //'customer_notes' => $customerNotes
        ];
    }
}
