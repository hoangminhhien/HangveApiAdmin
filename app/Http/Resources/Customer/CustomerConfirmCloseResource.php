<?php

namespace App\Http\Resources\Customer;

use App\Models\CustomerConfirmClose;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerConfirmCloseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id ?? 0,
            'note' => $this->noteReason->name ?? '',
            'date' => date('d-m-Y H:i:s', strtotime($this->created_at)),
            'user_create' => $this->userCreate->name ?? '',
            'user_confirm' => $this->userConfirm->name ?? '',
            'customer' => $this->customer ?? [],
            'status' => $this->status ?? '',
            'status_text' => !empty($this->status) && isset(CustomerConfirmClose::LIST_STATUS[$this->status]) ?
                CustomerConfirmClose::LIST_STATUS[$this->status]['name'] : '',
            'status_color' => !empty($this->status) && isset(CustomerConfirmClose::LIST_STATUS[$this->status]) ?
                CustomerConfirmClose::LIST_STATUS[$this->status]['color'] : '',

        ];
    }
}
