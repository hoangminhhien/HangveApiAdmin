<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationBankResource extends JsonResource
{
    public function toArray($request): array
    {
        $type_payment = '';
        if ($this->type_payment == 1) {
            $type_payment = 'Cá nhân';
        }
        if ($this->type_payment == 2) {
            $type_payment = 'Doanh nghiệp';
        }
        $websiteIds = [];
        $websiteName = [];
        if(!empty($this->websites)) {
            foreach ($this->websites as $website) {
                $websiteIds[] = $website->id;
                $websiteName[] = $website->name;
            }
        }

        return [
            'id' => $this->id,
            'bank_id' => $this->bank_id,
            'name' => $this->name,
            'account_number' => $this->account_number,
            'type_name' => $this->type == 1 ? 'Khách hàng dùng' : 'Nội bộ dùng',
            'type' => $this->type,
            'type_payment' => $type_payment,
            'bank_name' => $this->bank->name ?? '',
            'code' => $this->bank->bank_id ?? '',
            'status' => $this->status,
            'website_ids' => $websiteIds,
            'website_name' => $websiteName
        ];
    }
}
