<?php

namespace App\Services;

use App\Constants\OrderConstant;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\Report\ReportSaleStaffResource;
use App\Jobs\Report\SyncReportOrderProvinceByIdJob;
use App\Jobs\Report\SyncReportOrderProvinceJob;
use App\Models\Order;
use App\Models\ReportOrderProvince;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportProvinceService extends BaseService
{
    protected string $_paginateResource = PaginateJsonResource::class;

    protected string $_resource = ReportSaleStaffResource::class;

    protected ConsignmentService $_consignmentService;

    protected OrderService $_orderService;

    public function __construct(ConsignmentService $consignmentService, OrderService $orderService)
    {
        parent::__construct();
        $this->_consignmentService = $consignmentService;
        $this->_orderService = $orderService;
    }

    /**
     * @param array $params
     * @return JsonResponse
     * @throws \Exception
     */
    public function getReportByProvince(array $params): JsonResponse
    {
        try {
            $province = $params["province_id"] ?? null;
            $typeTime = $params["type"] ?? null;
            if (!$typeTime) {
                return resError('The field time is required.');
            }

            $resultData = ReportOrderProvince::query()
                ->with(['province'])
                ->where("type", $typeTime)
                ->where(function ($query) use ($province) {
                    if ($province) {
                        $query->where("province_id", $province);
                    }
                })
                ->orderBy('total_amount', 'DESC')
                ->get();
            $createdAt = ReportOrderProvince::query()->first()->created_at ?? Carbon::now();
            $createdAt = Carbon::parse($createdAt)->format('d-m-Y H:i:s');
            return resSuccessWithinData($resultData, $createdAt);
        } catch (\Exception $exception) {
            return resError($exception->getMessage());
        }
    }

    public function syncReportProvince(): JsonResponse
    {
        try {
            dispatch(new SyncReportOrderProvinceJob());
            return resSuccess("Done: Sync Report Order Province");
        } catch (\Exception $exception) {
            return resError($exception->getMessage());
        }
    }

    public function processDataReport($startDate, $endDate, $provinceId = null): bool
    {
        try {
            $rangeTimes = [
                'day' => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
                'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
                'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
                'year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]
            ];
            foreach ($rangeTimes as $key => $value) {
                $orders = DB::table('orders')->select([
                    'provinces.id as province_id',
                    DB::raw('count(DISTINCT(orders.customer_id)) as total_customer'),
                    DB::raw('count(orders.id) AS total_order'),
                    DB::raw("sum(CASE WHEN orders.status in ('status_2', 'status_3', 'status_4', 'status_8') THEN orders.order_cost END) as total_amount"),
                    DB::raw("sum(CASE WHEN orders.status in ('status_2', 'status_3', 'status_4', 'status_8') THEN orders.order_fee END) as total_fee"),
                    DB::raw('sum(orders.international_shipping_cost) AS total_transport'),
                    DB::raw("count(CASE WHEN orders.status in ('status_0', 'status_1') THEN 1 END) as total_order_created"),
                    DB::raw("
                                count(CASE WHEN orders.status in ('status_2', 'status_3', 'status_4', 'status_8') THEN 1 END)
                                as total_order_deposit"),
                    DB::raw("
                                count(DISTINCT(CASE WHEN orders.status in ('status_2', 'status_3', 'status_4', 'status_8') THEN orders.customer_id END))
                                as total_customer_deposit")
                ])
                    ->join('customer_deliveries', 'customer_deliveries.id', 'orders.customer_delivery_id')
                    ->join('provinces', 'customer_deliveries.province_id', 'provinces.id')
                    ->whereBetween('orders.created_at', $value)
                    ->groupBy(['provinces.id'])->get()->toArray();
                $orders = array_map(function ($item) use ($key) {
                    $item = (array)$item;
                    $item['conversion_rate_customer'] = $item["total_customer"]
                        ? ($item["total_customer_deposit"] / $item["total_customer"]) * 100
                        : $item["total_customer_deposit"] * 100;
                    $item['conversion_rate_order'] = $item["total_order_created"]
                        ? ($item["total_order_deposit"] / ($item["total_order"])) * 100
                        : $item["total_order_created"] * 100;
                    $item['date_report'] = date("Y-m-d");
                    $item['created_at'] = Carbon::now();
                    $item['updated_at'] = Carbon::now();
                    $item['type'] = $key;
                    $item['total_transport'] = $item['total_transport'] ? $item['total_transport'] : 0;
                    return $item;
                }, $orders);
                ReportOrderProvince::where('type', $key)->delete();
                ReportOrderProvince::insert($orders);
            }
            Log::info('Progressed done: SyncReportOrderProvinceJob');
            return true;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return false;
        }
    }

    public function progressDataOrderGroupByProvinceId($itemProvince, $provinceId, $dateType)
    {
        DB::beginTransaction();
        try {
            $dataCreate = [];
            if (!count($itemProvince)) {
                DB::commit();
                return;
            }
            foreach ($itemProvince as $item) {
                $dataCreate = $this->mapDateOrderByProvince($dataCreate, $item, $provinceId,
                    $dateType);
            }
            if (!count($dataCreate)) {
                DB::commit();
                return;
            }
            foreach ($dataCreate as $key => $itemList) {
                foreach ($itemList as $keyChild => $itemChild) {
                    $dataCheckConditions = [
                        "province_id" => $key,
                        "type" => $keyChild
                    ];

                    $itemChild["total_customer"] = count($itemChild["customer_ids"]);
                    $itemChild["total_customer_created"] = count($itemChild["customer_ids_created"]);
                    $itemChild["total_customer_deposit"] = count($itemChild["customer_ids_deposit"]);
                    $itemChild["date_report"] = date("Y-m-d");
                    $itemChild["conversion_rate_customer"] = 0;
                    $itemChild["conversion_rate_order"] = 0;
                    if ($itemChild["total_customer_deposit"]) {
                        if ($itemChild["total_customer_created"]) {
                            $itemChild["conversion_rate_customer"] = ($itemChild["total_customer_deposit"] / $itemChild["total_customer_created"]) * 100;
                        } else {
                            $itemChild["conversion_rate_customer"] = $itemChild["total_customer_deposit"] * 100;
                        }
                        $itemChild["conversion_rate_customer"] = $itemChild["conversion_rate_customer"] > 0 ? round($itemChild["conversion_rate_customer"],
                            2) : 0;
                    }

                    if ($itemChild["total_order_deposit"]) {
                        if ($itemChild["total_order_created"]) {
                            $itemChild["conversion_rate_order"] = ($itemChild["total_order_deposit"] / $itemChild["total_order_created"]) * 100;
                        } else {
                            $itemChild["conversion_rate_order"] = $itemChild["total_order_deposit"] * 100;
                        }
                        $itemChild["conversion_rate_order"] = $itemChild["conversion_rate_order"] > 0 ? round($itemChild["conversion_rate_order"],
                            2) : 0;
                    }

                    unset($itemChild["customer_ids"]);
                    unset($itemChild["customer_ids_deposit"]);
                    unset($itemChild["customer_ids_created"]);
                    $dataCreateOrUpdate = array_merge($itemChild, $dataCheckConditions);

                    ReportOrderProvince::query()->updateOrCreate($dataCheckConditions, $dataCreateOrUpdate);
                }
            }
            DB::commit();
        } catch (\Exception $exception) {
            Log::error(
                'SYNC_DATA_ORDER_PROVINCE_BY_ID# Lỗi khi tổng hợp báo cáo tỉnh thành phố',
                [
                    'line' => __LINE__,
                    'method' => __METHOD__,
                    'error_message' => $exception->getMessage(),
                ]
            );
            DB::rollBack();
            return;
        }
    }

    public function mapDateOrderByProvince($dataList, $item, $provinceId, $dateByType): array
    {
        [$startDateDay, $endDateDay] = $dateByType[0];
        [$startDateWeek, $endDateWeek] = $dateByType[1];
        [$startDateMonth, $endDateMonth] = $dateByType[2];
        [$startDateYear, $endDateYear] = $dateByType[3];

        $dateCreated = date("Y-m-d", strtotime($item["created_at"]));
        $orderStatusTime = $item["order_status_time"];
        if (isset($orderStatusTime) && !empty($orderStatusTime["status_6"])) {
            return $dataList;
        }

        if ($this->checkValidateTimeOrder($dateCreated, $item, $startDateDay, $startDateDay)) {
            $type = 'day';
            $dataList = $this->mergeDataOrderByType($dataList, $item, $type, $provinceId, $startDateDay, $endDateDay);
        }

        if ($this->checkValidateTimeOrder($dateCreated, $item, $startDateWeek, $endDateWeek)) {
            $type = 'week';
            $dataList = $this->mergeDataOrderByType($dataList, $item, $type, $provinceId, $startDateWeek, $endDateWeek);
        }

        if ($this->checkValidateTimeOrder($dateCreated, $item, $startDateMonth, $endDateMonth)) {
            $type = 'month';
            $dataList = $this->mergeDataOrderByType($dataList, $item, $type, $provinceId, $startDateMonth,
                $endDateMonth);
        }
        $type = 'year';
        return $this->mergeDataOrderByType($dataList, $item, $type, $provinceId, $startDateYear, $endDateYear);
    }

    public function checkValidateTimeOrder($dateCreated, $item, $startDate, $endDate): bool
    {
        $orderStatusTime = $item["order_status_time"];
        if (($dateCreated >= $startDate && $dateCreated <= $endDate)
            || (!empty($orderStatusTime)
                && (($item["status"] == OrderConstant::KEY_STATUS_WAITING_DEPOSIT && !empty($orderStatusTime["status_1"])
                        && date("Y-m-d", strtotime($orderStatusTime["status_1"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_1"])) <= $endDate)
                    || ($item["status"] == OrderConstant::KEY_STATUS_DEPOSITED && !empty($orderStatusTime["status_2"])
                        && date("Y-m-d", strtotime($orderStatusTime["status_2"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_2"])) <= $endDate)
                    || ($item["status"] == OrderConstant::KEY_STATUS_ORDERED && !empty($orderStatusTime["status_3"])
                        && date("Y-m-d", strtotime($orderStatusTime["status_3"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_3"])) <= $endDate)
                    || ($item["status"] == OrderConstant::KEY_STATUS_DONE && !empty($orderStatusTime["status_4"])
                        && date("Y-m-d", strtotime($orderStatusTime["status_4"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_4"])) <= $endDate)
                    || ($item["status"] == OrderConstant::KEY_STATUS_WAIT_TO_PAY && !empty($orderStatusTime["status_8"])
                        && date("Y-m-d", strtotime($orderStatusTime["status_8"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_8"])) <= $endDate))
            )
        ) {
            return true;
        }
        return false;
    }

    public function mergeDataOrderByType($dataList, $item, $type, $provinceId, $startDate, $endDate): array
    {
        try {
            $dataMerge = $dataList[$provinceId][$type] ?? null;
            $orderStatusTime = $item["order_status_time"];
            $dateCreated = date("Y-m-d", strtotime($item["created_at"]));
            $checkOrderByStatus = false;

            $totalDiscountCostPacks = 0;
            $totalWoodworkingCostPacks = 0;
            $totalInternationalCostPacks = 0;
            if (!empty($item['packages'])) {
                $totalDiscountCostPacks = array_sum(array_column($item['packages'], 'discount_cost'));
                $totalWoodworkingCostPacks = array_sum(array_column($item['packages'], 'woodworking_cost'));
                $totalInternationalCostPacks = array_sum(array_column($item['packages'], 'international_shipping_cost'));
            }
            $totalTransport = $totalWoodworkingCostPacks + $totalInternationalCostPacks - $totalDiscountCostPacks;
            $checkOrderDeposit = !empty($orderStatusTime)
                && (
                    ($item["status"] == OrderConstant::KEY_STATUS_DEPOSITED && $orderStatusTime["status_2"]
                        && date("Y-m-d", strtotime($orderStatusTime["status_2"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_2"])) <= $endDate)
                    || ($item["status"] == OrderConstant::KEY_STATUS_ORDERED && $orderStatusTime["status_3"]
                        && date("Y-m-d", strtotime($orderStatusTime["status_3"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_3"])) <= $endDate)
                    || ($item["status"] == OrderConstant::KEY_STATUS_DONE && $orderStatusTime["status_4"]
                        && date("Y-m-d", strtotime($orderStatusTime["status_4"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_4"])) <= $endDate)
                    || ($item["status"] == OrderConstant::KEY_STATUS_WAIT_TO_PAY && $orderStatusTime["status_8"]
                        && date("Y-m-d", strtotime($orderStatusTime["status_8"])) >= $startDate
                        && date("Y-m-d", strtotime($orderStatusTime["status_8"])) <= $endDate)
                );

            $checkOrderDepositNow = $checkOrderDeposit
                && (
                    (!$orderStatusTime["status_2"] || (date("Y-m-d", strtotime($orderStatusTime["status_2"])) >= $startDate
                            && date("Y-m-d", strtotime($orderStatusTime["status_2"])) <= $endDate))
                    && (!$orderStatusTime["status_3"] || (date("Y-m-d", strtotime($orderStatusTime["status_3"])) >= $startDate
                            && date("Y-m-d", strtotime($orderStatusTime["status_3"])) <= $endDate))
                    && (!$orderStatusTime["status_4"] || (date("Y-m-d", strtotime($orderStatusTime["status_4"])) >= $startDate
                            && date("Y-m-d", strtotime($orderStatusTime["status_4"])) <= $endDate))
                    && (!$orderStatusTime["status_8"] || (date("Y-m-d", strtotime($orderStatusTime["status_8"])) >= $startDate
                            && date("Y-m-d", strtotime($orderStatusTime["status_8"])) <= $endDate))
                );

            if ($dataMerge) {
                if ($dateCreated >= $startDate && $dateCreated <= $endDate) {
                    $dataMerge["total_order"] += 1;
                    $dataMerge["total_order_created"] += 1;
                    $checkExitsCustomerOrderCreated = in_array($item["customer_id"],
                        $dataMerge["customer_ids_created"]);
                    if (!$checkExitsCustomerOrderCreated) {
                        $dataMerge["customer_ids_created"][] = $item["customer_id"];
                    }

                    if ($checkOrderDeposit) {
                        $dataMerge["total_order_deposit"] += 1;
                        $checkExitsCustomerCreated = in_array($item["customer_id"], $dataMerge["customer_ids_deposit"]);
                        if (!$checkExitsCustomerCreated) {
                            $dataMerge["customer_ids_deposit"][] = $item["customer_id"];
                        }
                    }
                    $checkOrderByStatus = true;
                } else {
                    if ($checkOrderDepositNow) {
                        $dataMerge["total_order_deposit"] += 1;
                        $checkExitsCustomerCreated = in_array($item["customer_id"], $dataMerge["customer_ids_deposit"]);
                        if (!$checkExitsCustomerCreated) {
                            $dataMerge["customer_ids_deposit"][] = $item["customer_id"];
                        }
                        $checkOrderByStatus = true;
                    }
                }

                if (!$checkOrderByStatus) {
                    return $dataList;
                }

                $checkExitsCustomer = in_array($item["customer_id"], $dataMerge["customer_ids"]);
                if (!$checkExitsCustomer) {
                    $dataMerge["customer_ids"][] = $item["customer_id"];
                }
                $dataMerge["total_amount"] += (float)$item["order_cost"];
                $dataMerge["total_fee"] += (float)$item["order_fee"];
                $dataMerge["total_transport"] += $totalTransport;
                $dataList[$provinceId][$type] = $dataMerge;
            } else {
                $reportFirst = [
                    "customer_ids" => [$item["customer_id"]],
                    "customer_ids_created" => [],
                    "customer_ids_deposit" => [],
                    "total_order" => 0,
                    "total_order_created" => 0,
                    "total_order_deposit" => 0,
                    "total_amount" => (float)$item["order_cost"],
                    "total_fee" => (float)$item["order_fee"],
                    "total_transport" => $totalTransport
                ];
                if ($dateCreated >= $startDate && $dateCreated <= $endDate) {
                    $reportFirst["total_order"] += 1;
                    $reportFirst["total_order_created"] += 1;
                    $reportFirst["customer_ids_created"][] = $item["customer_id"];

                    if ($checkOrderDeposit) {
                        $reportFirst["total_order_deposit"] += 1;
                        $reportFirst["customer_ids_deposit"][] = $item["customer_id"];
                    }
                    $checkOrderByStatus = true;
                } else {
                    if ($checkOrderDepositNow) {
                        $reportFirst["total_order_deposit"] += 1;
                        $reportFirst["customer_ids_deposit"][] = $item["customer_id"];
                        $checkOrderByStatus = true;
                    }
                }
                if (!$checkOrderByStatus) {
                    return $dataList;
                }
                $dataList[$provinceId][$type] = $reportFirst;
            }
            return $dataList;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $dataList;
        }
    }

    /*public function mapDataConsignments($consignments)
    {
        foreach ($consignments as &$item) {
            $item["order_fee"] = 0;
            $item["international_shipping_cost"] = 0;
            $item["order_cost"] = 0;
            foreach ($item["order_package"] as $itemChild) {
                $item["international_shipping_cost"] += (float)$itemChild["international_shipping_cost"];
                $item["order_cost"] += (float)$itemChild["order_cost"];
            }
        }
        return $consignments;
    }*/

}
