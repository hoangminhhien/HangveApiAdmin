<?php

namespace App\Http\Resources\Order;

use App\Constants\OrderConstant;
use App\Constants\TimeConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\ReportStatusResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderRelatedListResource extends JsonResource
{

    public function toArray($request): array
    {
        $totalAmount = $this->thuongdo_id ? $this->thuongdo_tong_tien_don_hang : $this->getTotalAmount();
        $depositAmount = $this->thuongdo_id ? $this->thuongdo_tong_tien_tam_ung : $this->deposit_cost;
        $amount_remaining = $totalAmount - $depositAmount;
        if ($this->notePublic->isNotEmpty()) {
            foreach ($this->notePublic as &$note) {
                $note->date_created = TimeHelper::format($note->created_at, TimeConstant::DATETIME_BY_DAY_HI);
                $note->user_name = $note->staff->name;
                unset($note->staff);
                unset($note);
            }
        }
        return [
            'id' => $this->id ?? '',
            'code' => $this->code ?? '',
            'total_amount' => roundMoney($totalAmount),
            'deposit_amount' => roundMoney($depositAmount),
            'amount_remaining' => roundMoney($amount_remaining),
            'total_note' => $this->notePublic->count() ?? 0,
            'list_note' => $this->notePublic ?? [],
            'status' => new ReportStatusResource(
                $this->status ?? 0,
                OrderConstant::STATUSES,
                OrderConstant::STATUSES_COLOR
            ),
            'created_at' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HI),
        ];
    }
}
