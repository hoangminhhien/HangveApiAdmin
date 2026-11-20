<?php

namespace App\Http\Resources;

use App\Constants\ConsignmentConstant;
use App\Constants\TimeConstant;
use App\Helpers\StatusHelper;
use App\Helpers\TimeHelper;
use App\Http\Resources\Package\ConsignmentPackageResource;
use App\Models\Consignment;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsignmentResource extends JsonResource
{
    /*public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'created_at' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HIS),
            'warehouse_cn' => data_get($this->warehouseCn, 'name'),
            'packages_number' => $this->packages_number ?? 0,
            'status' => StatusHelper::getInfo($this->status, ConsignmentConstant::class),
            'total_fee' => 1000000,
            'staff_group' => data_get($this->customer, 'staffGroup.name'),
            // 'statuses' => StatusHelper::getStatuses(
            //     $this->id,
            //     Consignment::class,
            //     ConsignmentConstant::class,
            //     ConsignmentConstant::STATUES_SHOW_DETAILS
            // ),
            // 'packages' => new ListResource($this->packages, ConsignmentPackageResource::class),
        ];
    }*/

    public function toArray($request): array
    {
        $is_cancel = false;
        if (in_array($this->status, ConsignmentConstant::LIST_STATUS_ACCEPT_CANCEL)) {
            $is_cancel = true;
        }
        $packages = new ListResource($this->orderPackage, ConsignmentPackageResource::class) ?? null;
        $totalFee = [
            'total' => 0,
            'paid' => 0,
            'remaining' => 0
        ];
        if ($packages) {
            foreach ($packages as $package) {
                $total_fee = $package['total_fee'] ?? [];
                $totalFee['total'] += $total_fee['total'] ?? 0;
                $totalFee['paid'] += $total_fee['paid'] ?? 0;
                $totalFee['remaining'] += $total_fee['remaining'] ?? 0;
            }
        }
        $totalFee['total'] = number_format($totalFee['total']);
        $totalFee['paid'] = number_format($totalFee['paid']);
        $totalFee['remaining'] = number_format($totalFee['remaining']);
        return [
            'id' => $this->id,
            'is_cancel' => $is_cancel,
            'code' => $this->code,
            'ordered_at' => date('d-m-Y H:i:s', strtotime($this->created_at)),
            'warehouse_cn' => data_get($this->warehouseCn, 'name'),
            'packages_number' => $this->packages_number ?? 0,
            'staff_group' => data_get($this->customer, 'staffGroup.name'),
            'status' => [
                'name' => ConsignmentConstant::LIST_STATUS[$this->status]['name'] ?? '',
                'color' => ConsignmentConstant::LIST_STATUS[$this->status]['color'] ?? '',
                'value' => $this->status ?? '',
            ],
            'statuses' => StatusHelper::getStatuses(
                $this->id,
                Consignment::class,
                ConsignmentConstant::class,
                ConsignmentConstant::STATUES_SHOW_DETAILS
            ),
            'packages' => $packages,
            'total_fee' => $totalFee
        ];
    }
}
