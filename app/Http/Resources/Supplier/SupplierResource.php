<?php

namespace App\Http\Resources\Supplier;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'logo' => $this->logo,
            'name' => $this->name,
            'order_amount' => $this->order_amount ?? 0,
            'complain_number' => $this->complain_number ?? 0,
            'industry' => $this->industry,
            'address' => $this->address,
            'website' => $this->website,
            'type' => $this->type,
            //'contact_type' => $this->getContactResource($this->contacts),
        ];
    }

    private function getContactResource($contacts): array
    {
        $data = [];
        foreach ($contacts as $contact) {
            $item = $contact->only(
                ['supplier_type_id', 'name', 'position', 'details']
            );
            $data[] = $item;
        }
        return $data;
    }
}
