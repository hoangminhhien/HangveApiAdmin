<?php

namespace App\Http\Resources\Delivery;

use App\Constants\DeliveryConstant;
use App\Constants\TimeConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\Customer\InfoDeliveryResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\Note\NoteResource;
use App\Http\Resources\ReportStatusResource;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $notes                     = $this->notesStaff;
        $deliveryOrderStatus       = $this->deliveryOrderStatus;
        $orderCreated              = (bool)$deliveryOrderStatus->count();
        $latestDeliveryOrderStatus = $deliveryOrderStatus->sortBy('created_at')->last();
        if($latestDeliveryOrderStatus) $latestDeliveryOrderStatus->carrier_order_id = $this->carrier_code;
        $orderPackages = $this->orderPackages;

        return [
            'id'           => $this->id,
            'time'         => TimeHelper::format($this->created_at),
            'customer'     => new InfoDeliveryResource($this->customer),
            'status'       => new ReportStatusResource(
                $this->status,
                DeliveryConstant::STATUSES,
                DeliveryConstant::STATUSES_COLOR
            ),
            'info'         => [
                'order_code' => optional($this->order)->code,
                'delivery_code'       => $this->code,
                'carrier' => optional($this->transporter)->name,
                'carrier_short_name' => optional($this->transporter)->short_name,
                'transporter_detail' => optional($this->transporterDetail)->only('id', 'name', 'phone_number', 'address'),
                'payment' => isset(DeliveryConstant::PAYMENTS[$this->payment]) ? DeliveryConstant::PAYMENTS[$this->payment] : "",
                'receiver' => optional($this->customerDelivery)->custom_name,
                'packages' => [
                    'quantity' => $orderPackages->count() ?? 0,
                    'data' => $orderPackages->map(function ($item) {
                        return $item->only('id', 'bill_code');
                    })
                ],
                'date' => TimeHelper::format($this->date, TimeConstant::DATE_VI),
                'delivery' => [
                    'delivery_cost' => $this->delivery_cost,
                    'is_delivery_cost_paid' => is_null(
                        $this->is_delivery_cost_paid
                    ) ? 'undefined' : (int)$this->is_delivery_cost_paid,
                    'fee_missing'  => $this->fee_missing, // Tiền còn thiếu nhân viên kho tự nhập
                ],
                'notes' => [
                    'customer' => $this->note_customer,
                    'staff' => $this->note,
                    'staff_quantity' => $notes->count(),
                    'data' => new ListResource($notes, NoteResource::class)
                ],
                'info_po_shi' => [
                    'postcode' => $this->postcode,
                    'carrier_code' => $this->carrier_code,
                    'shipper_phone_number' => $this->shipper_phone_number,
                ]
            ],
            'orderInfo' => $latestDeliveryOrderStatus,
            'orderCreated' => $orderCreated,
            'is_carrier_button' => $this->isCarrierButton($this->status, $this->carrier_code),
            'organization_id' => $this->organization_id,
        ];
    }

    private function isCarrierButton(string $status, ?string $carrierCode): bool
    {
        if($status === DeliveryConstant::KEY_STATUS_CANCEL  || $status === DeliveryConstant::KEY_STATUS_DONE) return false;
        return optional(
            $this->transporter
        )->is_get_delivery_price ? !$carrierCode : false;
    }
}
