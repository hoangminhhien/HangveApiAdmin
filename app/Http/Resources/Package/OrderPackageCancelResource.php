<?php

namespace App\Http\Resources\Package;

use App\Constants\PackageConstant;
use App\Http\Resources\ReportStatusResource;
use App\Models\OrderPackage;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OnlyIdNameResource;
use App\Helpers\TimeHelper;
use App\Constants\TimeConstant;
use App\Models\OrderPackageCancelStatusTime;

use App\Constants\OrderConstant;
use App\Helpers\StatusHelper;
use App\Constants\ConsignmentConstant;
use App\Models\Order;
class OrderPackageCancelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $customer = $this->customer ?? [];
        $orderPackage = $this->orderPackage ?? [];

        $orderDB = $orderPackage->order ?? null;

        return [
            'id' => $this->id,
            'package_id' => $this->order_package_id,
            'bill_code' => $this->bill_code,
            'order_code' => data_get($orderPackage, 'order_code'),
            'order_id' => data_get($orderPackage, 'order_id'),
            'customer' => [
                'id' => optional($customer)->id,
                'name' => optional($customer)->name,
                'phone_number' => optional($customer)->phone_number,
                'code' => optional($customer)->code
            ],
            'staff_create' => optional($this->cause)->only('id', 'name', 'code'),
            'staff_manager' => optional($this->staff_managent)->only('id', 'name', 'code'),
            'type' => new ReportStatusResource(
                $this->type_cancel_value,
                PackageConstant::TYPES_CANCEL,
                PackageConstant::TYPES_CANCEL_COLOR
            ),
            'time_create' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HI),
            'time_update' => TimeHelper::format($this->updated_at, TimeConstant::DATETIME_BY_DAY_HI),
            'image' => $this->image,
            'status' => new ReportStatusResource(
                $this->status,
                PackageConstant::STATUSES_PACKAGE_CANCEL,
                PackageConstant::STATUSES_PACKAGE_CANCEL_COLOR
            ),

            'order_type' => $this->getOrderType($orderPackage),
            'order_status' => $this->getOrderStatus($orderPackage, $orderDB),
            'packages' => $this->getPackOfOder($orderPackage, $orderDB)
        ];
    }

    public function getOrderType($orderPackage)
    {
        if (empty($orderPackage)) {
            return null;
        }
        return !$orderPackage->order_type ? null : ($orderPackage->order_type === Order::class ? 'order' : 'consigment');
    }
    public function getOrderStatus($orderPackage, $orderDB)
    {
        if (empty($orderDB)) {
            return null;
        }

        if ($orderPackage->order_type == 'App\Models\Order') {
            return new ReportStatusResource(
                $orderDB->status,
                OrderConstant::STATUSES,
                OrderConstant::STATUSES_COLOR
            );
        }

        return StatusHelper::getInfo($orderDB->status, ConsignmentConstant::class);
    }

    public function getPackOfOder($orderPackage, $orderDB)
    {
        if(empty($orderPackage)) {
            return [];
        }
        if ($orderPackage->order_type == '' || $orderPackage->order_type == null) {
            return [];
        }

        if (is_null($orderDB)) {
            return [];
        }

        $packModel = \App::make('App\Models\OrderPackage');
        $packDBs = $packModel->where('order_type', $orderPackage->order_type)
                                    ->where('order_id', $orderDB->id)
                                    ->get();

        $data_pack = [];
        if (count($packDBs) > 0) {
            foreach ($packDBs as $key => $packDB) {
                $temp = [
                    'bill_code' => $packDB->bill_code,
                    'status' => new ReportStatusResource(
                        $packDB->status,
                        PackageConstant::STATUSES,
                        PackageConstant::STATUSES_COLOR
                    ),
                ];
                array_push($data_pack, $temp);
            }
        }
        return $data_pack;
    }
}
