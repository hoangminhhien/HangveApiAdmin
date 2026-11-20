<?php

namespace App\Http\Resources\Group;

use App\Constants\GroupConstant;
use App\Helpers\StatusHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user         = $this->user;
        $userName     = optional($user)->email ? optional($user)->email : optional($user)->phone_number;
        $userFullName = optional($user)->name;

        //list($realSales, $realServices, $status) = $this->getKpiStatus();
        $now       = strtotime(date('Y-m-01 00:00:00'));
        $month     = str_replace('/', '-', $this->month);
        $allowEdit = strtotime(date('Y-m-t 23:59:59', strtotime($month))) < $now ? false : true;

        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'user_name'       => $userName,
            'user_fullName'   => $userFullName,
            'group_id'        => $this->group_id,
            'group_name'      => optional($this->group)->name,
            'month'           => $this->month,
            'kpi_sales'       => $this->kpi_sales,
            'kpi_services'    => $this->kpi_services,
            'kpi_transports'    => $this->kpi_transports,
            'real_sales'      => $this->real_sales,
            'real_services'   => $this->real_services,
            'real_transports'   => $this->real_transports,
            'kpi'             => $this->kpi,
            'status'          => StatusHelper::getInfo($this->status, GroupConstant::class),
            'customer_name'   => optional($this->customer)->name,
            'customer_id'     => $this->customer_id,
            'is_get_customer' => $this->is_get_customer,
            'total_orders'    => $this->total_orders,
            'allow_edit'      => $allowEdit
        ];
    }

    public function getKpiStatus()
    {
        $realSales    = 0;
        $realServices = 0;
        $status       = GroupConstant::KPI_STATUS_FAIL;

        $kpi = $this->kpi;

        return [$realSales, $realServices, $status];
    }
}
