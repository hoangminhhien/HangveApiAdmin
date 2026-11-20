<?php

namespace App\Actions\ReportProductsPipeline;

use App\Actions\BaseAction;
use App\Constants\Report\ProductsPipelineConstant;
use App\Models\ReportProductsPipelineStatistics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CustomerSummary extends BaseAction
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
        // đầu tiên sẽ tìm các record đi theo 2 constraint là 'keyword' và 'customer_code', có paginate
        $rawData = $this->getRawData($data);
        // sau đó sẽ đếm số quantity ứng với từng record bên trên theo 4 hành động
        $items = $this->getItems($data, $rawData);
        // lấy pagination
        $pagination = $this->getPagination($rawData, count($items));

        $result = [
            'items' => $items,
            'pagination' => $pagination,
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

        return ReportProductsPipelineStatistics::query()
            ->whereNotNull('customer_code')
            // lọc theo từ khóa tìm kiếm
            ->when($keyword, fn ($query) => $query->whereKeyword($keyword))
            // lọc theo khoảng thời gian lấy báo cáo
            ->when(
                $period === ProductsPipelineConstant::PERIOD_DAY,
                fn ($query) => $query->whereDay($day)->whereWeek($week)->whereMonth($month)->whereYear($year)
            )
            ->when(
                $period === ProductsPipelineConstant::PERIOD_WEEK,
                fn ($query) => $query->whereWeek($week)->whereMonth($month)->whereYear($year)
            )
            ->when(
                $period === ProductsPipelineConstant::PERIOD_MONTH,
                fn ($query) => $query->whereMonth($month)->whereYear($year)
            )
            ->when(
                $period === ProductsPipelineConstant::PERIOD_YEAR,
                fn ($query) => $query->whereYear($year)
            )
            // lọc theo thành phố
            ->when($city, fn ($query) => $query->whereCity($city))
            // lọc theo nguồn bắn request
            ->when($requestSource, fn($query) => $query->whereSource($requestSource))
            // các constraints khác
            ->where('keyword', '!=', 'no_keyword')
            ->select(['keyword', 'customer_code',
                'customer_name', 'customer_phone', 'day', 'week', 'month', 'year',
                'order_id', 'order_code'])
            ->distinct(['keyword', 'customer_code'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Lấy 'items' trong response trả về Frontend
     *
     * @param LengthAwarePaginator $rawListKeywords
     * @return Collection
     */
    private function getItems(array $params, LengthAwarePaginator $rawData): array
    {
        $actions = ProductsPipelineConstant::AVAILABLE_ACTIONS;

        $searchAction = ProductsPipelineConstant::ACTION_SEARCH;
        $addToCartAction = ProductsPipelineConstant::ACTION_ADD_TO_CART;
        $orderUpAction = ProductsPipelineConstant::ACTION_ORDER_UP;
        $placeOrderAction = ProductsPipelineConstant::ACTION_PLACE_ORDER;
        $result = [];
        $keyCheck = [];
        $period = data_get($params, 'period');
        $now = Carbon::now();
        $day = $now->dayOfWeek;
        $week = $now->weekOfMonth;
        $month = $now->month;
        $year = $now->year;

        foreach($rawData->items() as $data) {
            $keyword = data_get($data, 'keyword');
            $customerCode = data_get($data, 'customer_code');
            if(in_array($keyword.$customerCode, $keyCheck)) {
                continue;
            }
            $keyCheck[] = $keyword.$customerCode;
            $statistics = ReportProductsPipelineStatistics::query()
                ->whereCustomerCode($customerCode)
                ->whereKeyword($keyword)
                ->whereIn('action', $actions)
                ->when(
                    $period === ProductsPipelineConstant::PERIOD_DAY,
                    fn ($query) => $query->whereDay($day)->whereWeek($week)->whereMonth($month)->whereYear($year)
                )
                ->when(
                    $period === ProductsPipelineConstant::PERIOD_WEEK,
                    fn ($query) => $query->whereWeek($week)->whereMonth($month)->whereYear($year)
                )
                ->when(
                    $period === ProductsPipelineConstant::PERIOD_MONTH,
                    fn ($query) => $query->whereMonth($month)->whereYear($year)
                )
                ->when(
                    $period === ProductsPipelineConstant::PERIOD_YEAR,
                    fn ($query) => $query->whereYear($year)
                )
                ->get();

            $isSearch = (bool)data_get(
                $statistics->firstWhere('action', $searchAction),
                'quantity'
            );
            $isAddToCart = (bool)data_get(
                $statistics->firstWhere('action', $addToCartAction),
                'quantity'
            );
            $isOrderUp = (bool)data_get(
                $statistics->firstWhere('action', $orderUpAction),
                'quantity'
            );
            $isPlaceOrder = (bool)data_get(
                $statistics->firstWhere('action', $placeOrderAction),
                'quantity'
            );
            $order = $statistics->filter(function ($item) {
                return $item->order_id > 0 && $item->order_id != null;
            })->first();
            $orderCode = $order ? $order->order_code : null;
            $orderId = $order ? $order->order_id : null;

            $rate = ((int)$isSearch + (int)$isAddToCart + (int)$isOrderUp + (int)$isPlaceOrder) * 100 / 4;

            $result[] = [
                'keyword' => mb_ucfirst($keyword),
                'customer' => [
                    'code' => $customerCode,
                    'name' => data_get($data, 'customer_name'),
                    'phone' => data_get($data, 'customer_phone')
                ],
                'action' => [
                    'search' => (int)$isSearch,
                    'add_to_cart' => (int)$isAddToCart,
                    'order_up' => (int)$isOrderUp,
                    'place_order' => (int)$isPlaceOrder,
                ],
                'order_code' => $orderCode,
                'order_id' => $orderId,
                'rate' => $rate . '%'
            ];
        }

        return $result;
    }

    /**
     * Lấy thông tin phân trang
     *
     * @param array $rawdata
     * @return array
     */
    private function getPagination(LengthAwarePaginator $rawData, $count): array
    {
        return [
            'total' => $rawData->total(),
            'count' => $count,
            'per_page' => $rawData->perPage(),
            'current_page' => $rawData->currentPage(),
            'last_page' => $rawData->lastPage(),
        ];
    }
}
