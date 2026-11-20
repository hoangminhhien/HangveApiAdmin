<?php

namespace App\Actions\ReportProductsPipeline;

use App\Actions\BaseAction;
use App\Constants\Report\ProductsPipelineConstant;
use App\Models\ReportProductsPipelineStatistics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class CityListAction extends BaseAction
{
    /**
     * Chứa logic thực thi chính của action
     *
     * @param array $data
     * @return JsonResponse
     */
    public function handle(?array $data): JsonResponse
    {
        $period = data_get($data, 'period');
        $now = Carbon::now();
        $day = $now->dayOfWeek;
        $week = $now->weekOfMonth;
        $month = $now->month;
        $year = $now->year;

        $cities = ReportProductsPipelineStatistics::query()
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
            // các constraints khác
            ->select(['city'])
            ->distinct()
            ->get()->pluck('city');

        return resSuccessWithinData($cities);
    }
}
