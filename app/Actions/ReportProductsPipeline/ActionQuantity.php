<?php

namespace App\Actions\ReportProductsPipeline;

use App\Actions\BaseAction;
use App\Constants\Report\ProductsPipelineConstant;
use App\Models\ReportProductsPipelineStatistics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ActionQuantity extends BaseAction
{
    /**
     * Chứa logic thực thi chính của action
     * Các trường dùng để filter
     *   + period: Khoảng thời gian lấy báo cáo, định nghĩa trong ProductsPipelineConstant::PERIOD_*
     *
     * @param array|null $data
     * @return JsonResponse
     */
    public function handle(?array $data): JsonResponse
    {
        $requestSource = request()->get('request_source');
        $period = data_get($data, 'period');
        $now = Carbon::now();
        $day = $now->dayOfWeek;
        $week = $now->weekOfMonth;
        $month = $now->month;
        $year = $now->year;

        $actions = ProductsPipelineConstant::AVAILABLE_ACTIONS;
        $totalQuantities = ReportProductsPipelineStatistics::query()
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
                $period === ProductsPipelineConstant::PERIOD_DAY,
                fn ($query) => $query->whereYear($year)
            )
            // lọc theo nguồn bắn request
            ->when($requestSource, fn($query) => $query->whereSource($requestSource))
            // các constraints khác
            ->whereIn('action', $actions)
            ->selectRaw('action, SUM(quantity) as total_quantity')
            ->groupBy('action')
            ->pluck('total_quantity', 'action');

        $titles = ProductsPipelineConstant::ACTION_TITLES;

        $result = Arr::map($actions, fn($action) => ([
            'action' => 'Số lượng ' . mb_strtolower(data_get($titles, $action)),
            'quantity' => (int)$totalQuantities->get($action, 0),
        ]));

        return resSuccessWithinData($result);
    }
}
