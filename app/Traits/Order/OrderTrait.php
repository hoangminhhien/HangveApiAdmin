<?php

namespace App\Traits\Order;

use App\Constants\OrderConstant;
use App\Helpers\AccountingHelper;
use App\Services\ConfigService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;

trait OrderTrait
{
    public function dateRange($time)
    {
        if(!$time) {
            throw new \Exception('Vui lòng nhập định dạng ngày');
        }
        if(!in_array($time, ['d', 'w', 'm', 'q', 'y'])) {
            throw new \Exception('Định dạng ngày tháng không hợp lệ');
        }

        $startDate = '';
        $endDate = Carbon::now();
        $startPrevious = '';
        $endPrevious = '';
        $today = Carbon::today();
        $now = Carbon::now();

        if ($time == 'd') {
            $startDate = $today->startOfDay();
            $startPrevious = $today->subDay()->startOfDay();
            $endPrevious = $today->subDay()->endOfDay();
        }
        if ($time == 'w'){
            $startDate = $now->startOfWeek();
            $startPrevious = $today->subWeek()->startOfWeek();
            $endPrevious = $today->subWeek()->endOfWeek();
        }
        if ($time == 'm'){
            $startDate = $now->firstOfMonth();
            $startPrevious = $today->subMonth()->startOfMonth();
            $endPrevious = $today->subMonth()->endOfMonth();
        }
        if ($time == 'q'){
            $startDate = $now->startOfQuarter();
            $startPrevious = $today->subMonth(3)->startOfQuarter();
            $endPrevious = $today->subMonth(3)->endOfQuarter();
        }
        if ($time == 'y'){
            $startDate = $now->startOfYear();
            $startPrevious = $today->subMonth(12)->startOfYear();
            $endPrevious = $today->subMonth(12)->endOfYear();
        }

        return array(
            'startDate'     => $startDate->format('Y-m-d H:i:s'),
            'endDate'       => $endDate->format('Y-m-d H:i:s'),
            'startPrevious' => $startPrevious->format('Y-m-d H:i:s'),
            'endPrevious'   => $endPrevious->format('Y-m-d H:i:s'),
        );
    }

    protected function ArrToCollection(
        $items,
        int $perPage = 10,
        $page = null,
        array $options = []
    ): LengthAwarePaginator {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            $options
        );
    }

    public function getTotalAmount($query): array
    {
        $amount = $query->where('status', OrderConstant::KEY_STATUS_DEPOSITED)->sum('deposit_cost');
        $amount = AccountingHelper::getCosts($amount);
        $amountCny = AccountingHelper::getCosts($amount / (new ConfigService())->getExchangeRate());

        return [$amount, $amountCny];
    }
}
