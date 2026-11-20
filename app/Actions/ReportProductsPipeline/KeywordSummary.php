<?php

namespace App\Actions\ReportProductsPipeline;

use App\Actions\BaseAction;
use App\Constants\Report\ProductsPipelineConstant;
use App\Models\ReportProductsPipelineStatistics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KeywordSummary extends BaseAction
{
    /**
     * Chứa logic thực thi chính của action
     * Các trường dùng để filter
     *   + period: Khoảng thời gian lấy báo cáo, định nghĩa trong ProductsPipelineConstant::PERIOD_*
     *   + keyword: Từ khóa tìm kiếm
     *   + city: Thành phố
     *   + request_source: Nguồn tìm kiếm
     *   + page, per_page: Dùng để paginate
     *
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function handle(?array $data): JsonResponse
    {
        // danh sách các từ khóa sẽ làm việc ở page hiện tại
        $rawData = $this->getRawData($data);
        $items = $this->getItems($rawData);
        // lấy pagination
        $pagination = $this->getPagination($rawData);

        $result = [
            'items' => $items,
            'pagination' => $pagination
        ];

        return resSuccessWithinData($result);
    }

    /**
     * Lấy danh sách các từ khóa sẽ làm việc ở page hiện tại
     *
     * @param array $data
     * @return LengthAwarePaginator
     */
    private function getRawData(array $data): LengthAwarePaginator
    {
        $period = data_get($data, 'period');
        $keyword = data_get($data, 'keyword');
        $city = data_get($data, 'city');
        $requestSource = data_get($data, 'request_source');
        $page = (int)data_get($data, 'page');
        $perPage = (int)data_get($data, 'per_page');

        $now = Carbon::now();
        $day = $now->dayOfWeek;
        $week = $now->weekOfMonth;
        $month = $now->month;
        $year = $now->year;

        $result = ReportProductsPipelineStatistics::query()
            // lọc theo từ khóa tìm kiếm
            ->when($keyword, fn($query) => $query->whereKeywordLike($keyword))
            // lọc theo khoảng thời gian lấy báo cáo
            ->when(
                $period === ProductsPipelineConstant::PERIOD_DAY,
                fn($query) => $query->whereDay($day)->whereWeek($week)->whereMonth($month)->whereYear($year)
            )
            ->when(
                $period === ProductsPipelineConstant::PERIOD_WEEK,
                fn($query) => $query->whereWeek($week)->whereMonth($month)->whereYear($year)
            )
            ->when(
                $period === ProductsPipelineConstant::PERIOD_MONTH,
                fn($query) => $query->whereMonth($month)->whereYear($year)
            )
            ->when(
                $period === ProductsPipelineConstant::PERIOD_DAY,
                fn($query) => $query->whereYear($year)
            )
            // lọc theo thành phố
            ->when($city, fn($query) => $query->whereCity($city))
            // lọc theo nguồn bắn request
            ->when($requestSource, fn($query) => $query->whereSource($requestSource))
            // các constraints khác
            ->whereNotNull('keyword')
            ->where('keyword', '!=', 'no_keyword')
            ->select(['keyword'])
            ->groupBy('keyword')
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return $result;
    }

    /**
     * Lấy 'items' trong response trả về Frontend
     *
     * @param LengthAwarePaginator $rawListKeywords
     * @return Collection
     */
    private function getItems(LengthAwarePaginator $rawData): Collection
    {
        $listKeywords = $rawData->pluck('keyword');

        $countResult = ReportProductsPipelineStatistics::query()
            ->select('action', 'keyword', 'created_at', 'day', DB::raw('SUM(quantity) as quantity'))
            ->whereIn('action', ProductsPipelineConstant::AVAILABLE_ACTIONS)
            ->whereIn('keyword', $listKeywords)
            ->groupBy('action', 'keyword')
            ->orderByDesc('created_at')
            ->get();

        $searchAction = ProductsPipelineConstant::ACTION_SEARCH;
        $addToCartAction = ProductsPipelineConstant::ACTION_ADD_TO_CART;
        $orderUpAction = ProductsPipelineConstant::ACTION_ORDER_UP;
        $placeOrderAction = ProductsPipelineConstant::ACTION_PLACE_ORDER;

        $result = $listKeywords->map(function ($keyword) use (
            $countResult, $searchAction, $addToCartAction, $orderUpAction, $placeOrderAction
        ) {
            $numberSearch = (int)data_get(
                $countResult->where('keyword', $keyword)->firstWhere('action', $searchAction),
                'quantity'
            ) ?? 0;
            $numberAddToCart = (int)data_get(
                $countResult->where('keyword', $keyword)->firstWhere('action', $addToCartAction),
                'quantity'
            ) ?? 0;
            $numberOrderUp = (int)data_get(
                $countResult->where('keyword', $keyword)->firstWhere('action', $orderUpAction),
                'quantity'
            ) ?? 0;
            $numberPlaceOrder = (int)data_get(
                $countResult->where('keyword', $keyword)->firstWhere('action', $placeOrderAction),
                'quantity'
            ) ?? 0;

            $numberTotal = $numberSearch + $numberAddToCart + $numberOrderUp + $numberPlaceOrder;
            $rate = $numberTotal ? round($numberPlaceOrder * 100 / $numberTotal, 1) : 0;
            return [
                'keyword' => mb_ucfirst($keyword),
                'rate_place_order' => $rate . '%',
                'number' => [
                    'search' => $numberSearch,
                    'add_to_cart' => $numberAddToCart,
                    'order_up' => $numberOrderUp,
                    'place_order' => $numberPlaceOrder,
                ]
            ];
        });

        return $result;
    }

    /**
     * Lấy thông tin phân trang
     *
     * @param LengthAwarePaginator $rawdata
     * @return array
     */
    private function getPagination(LengthAwarePaginator $rawData): array
    {
        return [
            'total' => $rawData->total(),
            'count' => $rawData->count(),
            'per_page' => $rawData->perPage(),
            'current_page' => $rawData->currentPage(),
            'last_page' => $rawData->lastPage(),
        ];
    }
}
