<?php

namespace App\Http\Controllers;

use App\Constants\ConsignmentConstant;
use App\Http\Resources\ReportStatusResource;
use App\Models\Consignment;
use App\Services\ConsignmentService;
use Illuminate\Http\JsonResponse;


class ConsignmentController extends Controller
{
    public function __construct(ConsignmentService $service)
    {
        $this->_service = $service;
    }

    public function statusCount(): JsonResponse
    {
        $columns = array_filter(array_keys(ConsignmentConstant::STATUSES));
        $data = [];
        $reportsV2 = [];
        $listStatus = $this->_service->getListStatusByCustomer();
        foreach($columns as $status){
            $checkExist = $listStatus->filter(function($item) use ($status) {
                return $item->status == $status;
            })->first();
            $reportsV2[$status] = $checkExist ? $checkExist->total : 0;
        }
        $listStatusCons = ConsignmentConstant::LIST_STATUS;
        foreach ($listStatusCons as $key => $listStatusCon) {
            $checkExist = $listStatus->filter(function($item) use ($listStatusCon) {
                return $item->status == $listStatusCon['value'];
            })->first();
            $reportsV2[$key] = [
                'name' => $listStatusCon['name'],
                'value' => $listStatusCon['value'],
                'color' => $listStatusCon['color'],
                'quantity' => $checkExist ? $checkExist->total : 0
            ];
        }
        return resSuccessWithinData(array_values($reportsV2));
    }
}
