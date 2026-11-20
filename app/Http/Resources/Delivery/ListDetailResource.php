<?php

namespace App\Http\Resources\Delivery;

use App\Constants\TimeConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\Customer\InfoDeliveryResource;
use App\Http\Resources\Develiery\PackageResource;
use App\Http\Resources\ListResource;
use App\Models\OrderPackage;
use Illuminate\Http\Resources\Json\JsonResource;

class ListDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $nameTransporterDetail = '';
        if ($this->transporterDetail && optional($this->transporterDetail)->address) {
            $nameTransporterDetail = $this->transporterDetail->name . ' - ' .
                $this->transporterDetail->phone_number . ' - ' .
                $this->transporterDetail->address;
        } else if($this->transporterDetail){
            $nameTransporterDetail = data_get($this->transporterDetail, 'name') . ' - ' .
                data_get($this->transporterDetail, 'phone_number');
        }
        return [
                'payment' => $this->payment,
                'transporter_id' => $this->transporter_id,
                'transporter' => $this->transporter,
                'transporter_detail_id' => $this->transporter_detail_id,
                'customer_delivery_id' => $this->customer_delivery_id,
                'transporter_detail' => $nameTransporterDetail,
                'type' => $this->type,
                'date' => TimeHelper::format($this->date, TimeConstant::DATE),
                'note' => $this->note,
                'customer' => new InfoDeliveryResource($this->customer),
                'packages' => new ListResource($this->packages, PackageResource::class),
                'note_customer' => $this->note_customer
        ];
    }
}
