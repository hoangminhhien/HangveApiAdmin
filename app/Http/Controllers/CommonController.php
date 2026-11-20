<?php

namespace App\Http\Controllers;

use App\Constants\CustomerConstant;
use App\Http\Resources\OnlyValueKeyResource;
use App\Services\CommonService;
use Illuminate\Http\JsonResponse;

class CommonController extends Controller
{
    public function __construct(CommonService $service)
    {
        $this->_service = $service;
    }

    /**
     * @return JsonResponse
     */
    public function getCategoriesCustomer(): JsonResponse
    {
        return resSuccessWithinData($this->_service->getCategoriesCustomer());
    }

    /**
     * @return JsonResponse
     */
    public function getListCategoriesOrder(): JsonResponse
    {
        return resSuccessWithinData($this->_service->getCategoriesOrder());
    }

    /**
     * @return JsonResponse
     */
    public function getReportOrder(): JsonResponse
    {
        try {
            return resSuccessWithinData($this->_service->getReportOrder());
        } catch (\Exception $e) {
            return resErrorWithinData([]);
        }
    }

    public function getListCategoriesFine(): JsonResponse
    {
        return resSuccessWithinData($this->_service->getListCategoriesFine());
    }

    public function getCategoriesWithdrawal(): JsonResponse
    {
        return resSuccessWithinData(['statuses' => new OnlyValueKeyResource(CustomerConstant::WITHDRAWAL_STATUSES)]);
    }

    public function getCategoriesPackages(): JsonResponse
    {
        return resSuccessWithinData($this->_service->getCategoriesPackages());
    }

    public function getCategoriesDelivery(): JsonResponse
    {
        $customerId = request()->query('customer_id');
        $deliveryId = request()->query('delivery_id');
        $packageIds = request()->query('package_ids', []);
        return resSuccessWithinData($this->_service->getCategoriesDelivery($customerId, $deliveryId, $packageIds));
    }

    public function getCategoriesComplain(): JsonResponse
    {
        return resSuccessWithinData($this->_service->getCategoriesComplain());
    }

    public function getConsignmentStatus(): JsonResponse
    {
        $listStatus = $this->_service->getConsignmentStatus();
        $listStatus = array_values($listStatus);
        return resSuccessWithinData($listStatus);
    }

    public function getStaffs(): JsonResponse
    {
        return resSuccessWithinData($this->_service->getStaffs());
    }
}
