<?php

namespace App\Http\Resources\Group;

use App\Constants\GroupConstant;
use App\Helpers\StatusHelper;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class GroupDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $userR = $this->usersMonth->where('status', 1);
        $users = $userR->map(function($item) {
            return [
                'id'           => $item->id,
                'name'         => $item->name,
                'code'         => $item->code,
                'email'        => $item->email,
                'phone_number' => $item->phone_number
            ];
        });
        $userIds = $userR->pluck('id');

        //$users = $this->users->only('id', 'name', 'code');
        $groupKpi = $this->kpiNearest;

        $adminIds = explode(',', $this->admin_id);
        $admins = User::select('id', 'name', 'code', 'email', 'phone_number')->find($adminIds)->toArray();

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'kpi_sales'    => optional($groupKpi)->kpi_sales,
            'kpi_services' => optional($groupKpi)->kpi_services,
            'kpi_transports' => optional($groupKpi)->kpi_transports,
            'kpi'          => optional($groupKpi)->kpi,
            'manager_id'   => $this->manager_id,
            'manager'   => [
                [
                    'id'           => $this->manager_id,
                    'name'         => optional($this->manager)->name,
                    'code'         => optional($this->manager)->code,
                    'email'        => optional($this->manager)->email,
                    'phone_number' => optional($this->manager)->phone_number,
                ]
            ],
            'admin_id'     => explode(',', $this->admin_id),
            'admins'       => $admins,
            'note'         => optional($groupKpi)->note,
            'user_id'      => $userIds,
            'users'        => $users,
            'month'        => optional($groupKpi)->month
        ];
    }
}
