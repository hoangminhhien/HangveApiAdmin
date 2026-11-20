<?php

namespace App\Http\Controllers;

use App\Constants\Report\ProductsPipelineConstant;
use App\Constants\ResourceConstant;
use App\Http\Requests\Report\ProductsPipeline\CustomerSummaryRequest;
use App\Http\Requests\Report\ProductsPipeline\KeywordSummaryRequest;
use App\Services\Reports\ReportProductsPipelineService;
use Illuminate\Http\JsonResponse;

class RequestReportController extends Controller
{
    public function __construct()
    {
        $this->_service = app()->make(ReportProductsPipelineService::class);
    }

    /**
     * Lấy danh sách mọi thành phố đã từng phát sinh đo lường
     *
     * @return JsonResponse
     */
    public function getCityList(): JsonResponse
    {
        $request = request();

        return $this->_service->getCityList([
            'period' => $request->get('period') ?? ProductsPipelineConstant::PERIOD_DAY
        ]);
    }

    /**
     * Lấy danh sách mọi nguồn tìm kiếm cho phép lấy báo cáo
     * Định nghĩa trong ProductsPipelineConstant::AVAILABLE_SOURCES
     *
     * @return JsonResponse
     */
    public function getRequestSourceList(): JsonResponse
    {
        return $this->_service->getRequestSourceList();
    }

    /**
     * Thống kê số lượng request theo hành động (tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng)
     *
     * @return JsonResponse
     */
    public function getActionQuantity(): JsonResponse
    {
        $request = request();

        return $this->_service->getActionQuantity([
            'period' => $request->get('period') ?? ProductsPipelineConstant::PERIOD_DAY
        ]);
    }

    /**
     * Thống kê số lượng request theo từ khóa tìm kiếm
     *
     * @return JsonResponse
     */
    public function getKeywordQuantity(): JsonResponse
    {
        $request = request();

        return $this->_service->getKeywordQuantity([
            'period' => $request->get('period') ?? ProductsPipelineConstant::PERIOD_DAY
        ]);
    }

    /**
     * Thống kê số lượng request theo thành phốn
     *
     * @return JsonResponse
     */
    public function getCityQuantity(): JsonResponse
    {
        $request = request();

        return $this->_service->getCityQuantity([
            'period' => $request->get('period') ?? ProductsPipelineConstant::PERIOD_DAY
        ]);
    }

    /**
     * Thống kê từ khóa theo cả 4 hành động: Tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng
     *
     * @return JsonResponse
     */
    public function getKeywordSummary(KeywordSummaryRequest $request): JsonResponse
    {
        $currentRequest = request();

        return $this->_service->getKeywordSummary(array_merge($request->validated(), [
            'page' => $currentRequest->get('page') ?? 1,
            'per_page' => $currentRequest->get('per_page') ?? ResourceConstant::PER_PAGE_MIN,
        ]));
    }

    /**
     * Thống kê theo từ khóa tìm kiếm của khách gắn với 4 hành dộng tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng
     *
     * @return JsonResponse
     */
    public function getCustomerSummary(CustomerSummaryRequest $request): JsonResponse
    {
        $currentRequest = request();

        return $this->_service->getCustomerSummary(array_merge($request->validated(), [
            'page' => $currentRequest->get('page') ?? 1,
            'per_page' => $currentRequest->get('per_page') ?? ResourceConstant::PER_PAGE_MIN,
        ]));
    }
}
