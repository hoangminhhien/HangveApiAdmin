<?php

namespace App\Http\Resources\Report;

use App\Constants\UserConstant;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffCareCounselorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'code'                => $this->code,
            'name'                => $this->name,
            'orders'              => [
                'totalWaitingQuote'   => (int) $this->totalWaitingQuote,
                'totalWaitingDeposit' => (int) $this->totalWaitingDeposit,
                'totalOrdered'        => (int) $this->totalOrdered,
                'totalDone'           => (int) $this->totalDone,
                'totalAll'            => (int) $this->totalAll,
            ],
            'totalCustomers'      => (new OrderService())->getTotalCustomersByStaffId($this->id),
            'liveChat'            => [
                'total'    => 0,
                'complete' => 0,
                'fail'     => 0
            ],
            'zaloChat'            => [
                'total'    => 0,
                'complete' => 0,
                'fail'     => 0
            ],
            'callCenter'          => [
                'total'       => 0,
                'call'        => 0,
                'accept_call' => 0
            ],
            'facebook'          => [
                'total'    => 0,
                'complete' => 0,
                'fail'     => 0
            ],
        ];
    }
}
