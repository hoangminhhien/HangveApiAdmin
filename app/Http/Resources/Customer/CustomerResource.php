<?php

namespace App\Http\Resources\Customer;

use App\Constants\CustomerConstant;
use App\Constants\TimeConstant;
use App\Constants\ViaConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\CustomerDelivery\CustomerDeliveryResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\OnlyIdNameForLabelResource;
use App\Http\Resources\OnlyIdNameResource;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        $report = $this->report ?? null;
        $customerNotes = !empty($this->customerNotes) ? $this->customerNotes->map(function ($note) {
            return [
                'user' => $note->user ? $note->user->name: "",
                'content' => $note->content,
                'date' => Carbon::parse($note->created_at)->format('d-m-Y H:i:s'),
            ];
        }) : [];
        $customerNotesType1And0 = !empty($this->customerNotesType1And0)
            ? $this->customerNotesType1And0->map(function ($note) {
            return [
                'user' => $note->user ? $note->user->name: "",
                'content' => $note->content,
                'id' => $note->id,
                'date' => Carbon::parse($note->created_at)->format('d-m-Y H:i:s'),
            ];
        }) : [];
        $gender = $this->gender == '' ? CustomerConstant::GENDER_UNDEFINED : $this->gender;
        $source = '';
        $colorSource = '';
        if($this->source == CustomerConstant::SOURCE_HV) {
            $source = 'Hàng Về';
            $colorSource = '#1FD094';
        } else if($this->source == CustomerConstant::SOURCE_TD) {
            $source = 'Thương Đô';
            $colorSource = '#8A6EFF';
        } else if($this->source == CustomerConstant::SOURCE_NH) {
            $source = 'Nguồn hàng 247';
            $colorSource = '#900';
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'info' => [
                'name' => $this->name,
                'email' => $this->email,
                'source' => $source,
                'source_id' => $this->source,
                'color_source' => $colorSource,
                'address' => optional(optional($this->warehouse)->province)->name,
                'address_real' => data_get($this->deliveryDefault, 'address'),
                'ward_name' => $this->deliveryDefault ? data_get($this->deliveryDefault->ward, 'name') : "",
                'province_name' => $this->deliveryDefault ? data_get($this->deliveryDefault->province, 'name') : "",
                'district_name' => $this->deliveryDefault ? data_get($this->deliveryDefault->district, 'name') : "",
                'gender' => CustomerConstant::GENDER[$gender ?? CustomerConstant::GENDER_UNDEFINED],
                'phone_number' => $this->phone_number,
                'created_at' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_HI_DAY),
                'via' => $this->via,
                'level' => data_get($this->offer, 'level_rate') ? data_get($this->offer, 'level_rate') : $this->level,
                'warehouse_id' => $this->warehouse_id,
                'warehouse_cn_id' => $this->warehouse_cn_id,
                'service' => $this->service,
                'staff_care_id' => $this->staff_care_id,
                'staff_counselor_id' => $this->staff_counselor_id,
                'staff_group_id' => $this->staff_group_id,
                'business_type' => [
                    'value' => $this->business_type,
                    'name' => CustomerConstant::CUSTOMER_BUSINESS_TYPE[$this->business_type] ?? null
                ],
            ],

            'order' => [
                'orders_number' => (int)($report->orders_number ?? 0),
                'order_consignment_number' => (int)($report->consignment_number ?? 0),
                'order_packages_number' => (int)($report->packages_number ?? 0),
                'order_amount' => (int)($report->order_amount ?? 0),
            ],
            'transaction' => [
                'deposited_amount' => (float)($report->deposited_amount ?? 0),
                'balance_amount' => (float)($report->balance_amount ?? 0),
            ],
            'status' => $this->status,
            'staff' => [
                'group' => new OnlyIdNameResource($this->staffGroup),
                'group_id' => $this->staff_group_id
            ],
            'label' => new OnlyIdNameForLabelResource($this->labels),
            'offer' => $this->offer,
            'customer_introduce_id' => $this->customer_introduce_id,
            'discount_by_kg' => $this->discount_by_kg,
            'discount_by_m3' => $this->discount_by_m3,
            'point_level' => $this->point_level,
            'created_at' =>  $this->created_at ? $this->created_at->format('d-m-Y') : null,
            'date_order_last' => !empty($this->last_order_at) && strtotime($this->last_order_at) ?
                Carbon::parse($this->last_order_at)->format('d-m-Y') : null,
            'customer_category' => $this->customer_categories,
            'customer_notes' => $customerNotes,
            'customer_notes_type1' => $customerNotesType1And0,
            'organization_id' => $this->organization_id
        ];
    }
}
