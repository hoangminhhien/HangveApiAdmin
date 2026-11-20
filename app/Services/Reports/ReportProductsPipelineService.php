<?php

namespace App\Services\Reports;

use App\Actions\ReportProductsPipeline\ActionQuantity;
use App\Actions\ReportProductsPipeline\CityListAction;
use App\Actions\ReportProductsPipeline\CityQuantity;
use App\Actions\ReportProductsPipeline\CustomerSummary;
use App\Actions\ReportProductsPipeline\KeywordQuantity;
use App\Actions\ReportProductsPipeline\KeywordSummary;
use App\Actions\ReportProductsPipeline\RequestSourceListAction;
use App\Services\BaseService;
use Illuminate\Http\JsonResponse;

class ReportProductsPipelineService extends BaseService
{
    /**
     * Lấy danh sách mọi thành phố đã từng phát sinh đo lường
     *
     * @return JsonResponse
     */
    public function getCityList(array $data = []): JsonResponse
    {
        $action = app()->make(CityListAction::class);
        return $action->handle($data);
    }

    /**
     * Lấy danh sách mọi thành phố đã từng phát sinh đo lường
     *
     * @return JsonResponse
     */
    public function getRequestSourceList(array $data = []): JsonResponse
    {
        $action = app()->make(RequestSourceListAction::class);
        return $action->handle($data);
    }

    /**
     * Thống kê số lượng request theo hành động (tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng)
     *
     * @return JsonResponse
     */
    public function getActionQuantity(array $data = []): JsonResponse
    {
        $action = app()->make(ActionQuantity::class);
        return $action->handle($data);
    }

    /**
     * Thống kê số lượng request theo từ khóa tìm kiếm
     *
     * @return JsonResponse
     */
    public function getKeywordQuantity(array $data = []): JsonResponse
    {
        $action = app()->make(KeywordQuantity::class);
        return $action->handle($data);
    }

    /**
     * Thống kê số lượng request theo thành phố
     *
     * @return JsonResponse
     */
    public function getCityQuantity(array $data = []): JsonResponse
    {
        $action = app()->make(CityQuantity::class);
        return $action->handle($data);
    }

    /**
     * Thống kê từ khóa theo cả 4 hành động: Tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng
     *
     * @return JsonResponse
     */
    public function getKeywordSummary(array $data = []): JsonResponse
    {
        $action = app()->make(KeywordSummary::class);
        return $action->handle($data);
    }

    /**
     * Thống kê theo từ khóa tìm kiếm của khách gắn với 4 hành dộng tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng
     *
     * @return JsonResponse
     */
    public function getCustomerSummary(array $data = []): JsonResponse
    {
        $action = app()->make(CustomerSummary::class);
        return $action->handle($data);
    }
}
