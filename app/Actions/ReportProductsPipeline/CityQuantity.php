<?php

namespace App\Actions\ReportProductsPipeline;

use App\Actions\BaseAction;
use App\Constants\Report\ProductsPipelineConstant;
use App\Models\ReportProductsPipelineStatistics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CityQuantity extends BaseAction
{
    /**
     * Chứa logic thực thi chính của action
     * Các trường dùng để filter
     *   + period: Khoảng thời gian lấy báo cáo, định nghĩa trong ProductsPipelineConstant::PERIOD_*
     *
     * @param array $data
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

        $result = ReportProductsPipelineStatistics::query()
            ->whereAction(ProductsPipelineConstant::ACTION_SEARCH)
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
            ->select('city', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('city')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        $data = $result->map(fn ($rs) => ([
            'city' => ucfirst(data_get($rs, 'city')),
            'total_quantity' => (int)data_get($rs, 'total_quantity')
        ]));

        return resSuccessWithinData($data);
    }
}
