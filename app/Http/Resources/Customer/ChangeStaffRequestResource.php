<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class ChangeStaffRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $dataResource = $this->resource;
        return [
            'id' => $dataResource->id,
            'customer_id' => $dataResource->customer_id ?? null,
            'customer_name' => $dataResource->customer->name ?? null,
            'customer_code' => $dataResource->customer->code ?? null,
            'customer_phone' => $dataResource->customer->phone_number ?? null,
            'reason' => $dataResource->reason,
            'staff_name_request' => $dataResource->staff_name_request,
            'staff_group_id' => $dataResource->staff_group_id,
            'staff_group_id_old' => $dataResource->staff_group_id_old,
            'staff_detail' => [
                "id" => $dataResource->staffGroup->id ?? null,
                "name" => $dataResource->staffGroup->name ?? null,
                "code" => $dataResource->staffGroup->code ?? null,
            ],
            'status' => $dataResource->status,
            'updated_by' => $dataResource->updated_by,
            'created_at' => date("Y-m-d H:i", strtotime($dataResource->created_at)),
            'updated_at' => $dataResource->updated_at ? date("Y-m-d H:i", strtotime($dataResource->updated_at)) : null,
        ];
    }
}
