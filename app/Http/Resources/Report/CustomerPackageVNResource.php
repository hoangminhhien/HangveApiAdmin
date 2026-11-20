<?php

namespace App\Http\Resources\Report;

use App\Constants\CustomerConstant;
use App\Constants\PackageConstant;
use App\Http\Resources\ListResource;
use App\Http\Resources\Note\NoteResource;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPackageVNResource extends JsonResource
{
    public function toArray($request): array
    {
        $packVN = optional($this->reportCustomerPackagesVN);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone_number,
            'email' => $this->email,
            'source' => $this->source ?? '',
            'source_name' => Customer::LIST_SOURCE[$this->source] ?? '',
            'packages' => $packVN ? $this->getListPack($packVN) : []
        ];
    }

    public function getListPack($packVN): array
    {
        $listPacks = [];
        $packs = $packVN->all();
        foreach ($packs as $item) {
            $timeStatus = $item->timeVN;
            $timeAgo = $timeStatus ? $this->getTimeAgo($timeStatus) : '';
            $content = '';
            $listPacks[] = [
                'id' => $item->id,
                'order_code' => optional($item->order)->code,
                'order_id' => $item->order_id,
                'bill_code' => $item->bill_code,
                'is_order' => $item->is_order,
                'shipping_cost' => $item->shipping_cost,
                'debt_cost' => optional($item->order)->debt_cost,
                'timeAgo' => $timeAgo,
                'content' => $content,
                'note' => $item->note_ordered,
                'listNotes'=> $this->getNotes($item->notes),
                'priority' => $this->getLevelPriority($timeStatus)
            ];
        }
        return $listPacks;
    }

    public function getTimeAgo($timeStatus): string
    {
        $now = Carbon::now();
        $days = $now->diffInDays($timeStatus);
        if ($days > 0) {
            return $days . ' ngày';
        }
        $hours = $now->diffInHours($timeStatus);
        return $hours . ' giờ';
    }

    public function getNotes($notes)
    {
        $listNotes = $notes->where('type', PackageConstant::TYPE_NOTE_ORDER);

        if ($listNotes->isEmpty()) {
            return '';
        }

        return (new ListResource($listNotes, NoteResource::class));
    }

    public function getLevelPriority($time)
    {

        $level = '';

        if (!$time) {
            return $level;
        }

        $timeStart = strtotime($time) + 86400; // time + 24h
        $timeEnd   = strtotime($time) + (3 * 86400); // time + 72h
        if (time() <= $timeStart) {
            $level = CustomerConstant::REPORT_PACKAGE_VN_LV1;
        } else if (time() > $timeStart && time() <= $timeEnd) {
            $level = CustomerConstant::REPORT_PACKAGE_VN_LV2;
        } else { // over 72 hours
            $level = CustomerConstant::REPORT_PACKAGE_VN_LV3;
        }

        return $level;
    }
}
