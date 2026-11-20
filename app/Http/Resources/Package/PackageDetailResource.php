<?php

namespace App\Http\Resources\Package;

use App\Constants\PackageConstant;
use App\Helpers\StatusHelper;
use App\Models\Consignment;
use App\Models\OrderDetailPackage;
use App\Models\OrderPackage;
use App\Models\ConsignmentDetail;
use App\Services\OrderPackageService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Arrayable;
use App\Constants\TimeConstant;

class PackageDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return array_merge(
            $this->getGeneralData(),
            $this->getInfoAndReceiver(),
            $this->getNote(),
            $this->getImages()
        );
    }

    private function getGeneralData(): array
    {
        $id = $this->id;
        $status = $this->status;

        return [
            'id' => $id,
            'order_id' => $this->order_id,
            'is_order' => $this->is_order,
            'code_po' => $this->code_po,
            'bill_code' => $this->bill_code,
            'category' => [
                'other' => $this->category,
                'id' => $this->category_id,
                'name' => optional($this->categoryRelation)->name
            ],
            'transporter' => [
                'name' => optional($this->transporterRelation)->name,
                'id' => $this->transporter_id,
                'other' => $this->transporter
            ],
            'package_number' => $this->package_number ?? 0,
            'costs' => (new OrderPackageService())->getCost($this),
            'status' => $this->getStatus($status),
            // 'statuses' => StatusHelper::getStatuses(
            //     $id,
            //     OrderPackage::class,
            //     PackageConstant::class,
            //     PackageConstant::STATUES_SHOW_DETAILS
            // ),
            'statuses' => $this->getStatuses(PackageConstant::class, PackageConstant::STATUES_SHOW_DETAILS),
            'order_cost' => $this->order_cost
        ];
    }

    private function getInfoAndReceiver(): array
    {
        $receiver = optional($this->order)->customerDelivery;
        return [
            'info' => [
                'order_code' => $this->order_code,
                'order_kind_of' => $this->order_kind_of,
                'weight' => $this->weight,
                'weight_real' => $this->weight_real,
                'volume' => $this->thuongdo_id > 0 ? $this->thuongdo_volumn : $this->volume,
                'is_inspection' => $this->is_inspection,
                'is_insurance' => $this->is_insurance,
            ],
            'receiver' => [
                'name' => optional($receiver)->custom_name,
                'inspection_number' => $this->quantity,
                'product_number' => $this->order_type === Consignment::class
                    //? ConsignmentDetail::query()->firstWhere('order_package_id', $id)->quantity
                    ? optional(ConsignmentDetail::query()->firstWhere('order_package_id', $this->id))->quantity
                    : (int)OrderDetailPackage::query()->where('order_package_id', $this->id)->sum('quantity'),
            ],
        ];
    }

    private function getNote(): array
    {
        return [
            'note' => $this->note,
            'notes_order' => $this->notes->filter(function ($item) {
                return $item->type == PackageConstant::TYPE_NOTE_ORDER;
             })->pluck('content'),
        ];
    }

    private function getStatuses($const, $statuses): array
    {
        $data = [];
        foreach ($statuses as $status) {
            $check = $this->statusTimes->filter(function ($item) use ($status) {
                if($status == PackageConstant::STATUS_IN_CHINA) {
                    return in_array($item->key, [PackageConstant::STATUS_IN_GUANGZHOU, PackageConstant::STATUS_IN_PINGXIANG, PackageConstant::STATUS_IN_CHINA]);
                }else {
                    return $item->key == $status;
                }
            })->first();
            $data[] = [
                    'name' => $const::STATUSES[$status],
                    'time' => $check ? $check->created_at->format(TimeConstant::DATETIME_VI) : null
            ];
        }
        return $data;
    }

    private function getStatus($status)
    {
        $check = $this->statusTimes->filter(function ($item) use ($status) {
            return $item->key == $status;
        })->first();
        return [
            'value' => $status,
            'name' => PackageConstant::STATUSES[$status],
            'color' => PackageConstant::STATUSES_COLOR[$this->status],
            'time' => $check ? $check->created_at->format(TimeConstant::DATETIME_VI) : null
        ];
    }
    
    private function getImages()
    {
        $images = [];
        foreach($this->images as $image) {
            array_push($images, env('BASE_URL_MEDIA_DONGDONG'). $image->image);
        }
        return [
            'images' => $images
        ];
    }
}
