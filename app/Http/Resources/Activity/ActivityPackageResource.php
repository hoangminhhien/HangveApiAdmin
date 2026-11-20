<?php

namespace App\Http\Resources\Activity;

use App\Constants\ActivityConstant;
use App\Constants\PackageConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\ReportStatusResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $content = $this->log_name === ActivityConstant::PACKAGE_STATUS
            ? $this->getContentObject($this->content)
            : $this->content;

        if ($content == 'international_shipping_cost') {
            $content = ActivityConstant::PACKAGE_PROPERTIES[$content];
        }

        $properties = json_decode($this->properties, true) ?? [];

        $staff = optional($this->causer)->only(['id', 'name']);

        if (!empty($properties['user_dongdong_name'])) {
            $staff['name'] = $properties['user_dongdong_name'];
        }

        return [
            'time' => TimeHelper::format($this->created_at),
            'staff' => $staff,
            'staff_warehouse' => $this->getNameDongDongWareHouse(optional($this->causer)->dongdong_warehouse),
            'content' => $content,
            'properties' => $properties,
        ];
    }

    public function getNameDongDongWareHouse($dongdong_warehouse)
    {
        if (!is_null($dongdong_warehouse)) {
            $list = [
                'quangchau-001' => 'Kho Quảng Châu',
                'donghung-002'  => 'Kho Đông Hưng',
                'vietnam-003'   => 'Kho Việt Nam',
            ];
            return array_key_exists($dongdong_warehouse, $list) ? $list[$dongdong_warehouse] : '';
        }
        return '';
    }

    private function getContentObject($status)
    {
        return [
            'value' => $status,
            'name' => PackageConstant::STATUSES[$status],
            'color' => PackageConstant::STATUSES_COLOR[$status]
        ];
    }
}
