<?php


namespace App\Services;


use App\Constants\PackageConstant;
use App\Models\Consignment;
use App\Models\ConsignmentStatusTime;
use App\Models\Customer;
use App\Models\OrderPackage;
use App\Models\ReportConsignment;
use Illuminate\Support\Facades\DB;
use App\Helpers\PaginateHelper;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ConsignmentResource;

class ConsignmentService extends BaseService
{
    protected string $_resource = ConsignmentResource::class;

    public function pagination(int $perPage): JsonResponse
    {
        $query = Consignment::query();
        $query->with([
            'warehouseCn',
            'customer',
            'customer.staffGroup',
            'orderPackage'
        ])->orderBy('id', 'DESC');

        if (request()->input('code')) {
            $query->where('code', request()->input('code'));
        }

        if (request()->input('warehouse_cn')) {
            $query->where('warehouse_cn', request()->input('warehouse_cn'));
        }

        if (request()->input('date')) {
            $query = (new FilterService())->rangeDateFilter($query, request()->input('date'), 'date_ordered');
        }

        if (request()->input('status')) {
            $query->where('status', request()->input('status'));
        }

        if (request()->input('bill_code')) {
            $query->whereHas('orderPackage', function ($packages) {
                $packages->where('bill_code', request()->input('bill_code'));
            });
        }

        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function incrementByColumn(string $orderId, string $column): void
    {
        optional(Consignment::query()->find($orderId))->increment($column);
    }

    /**
     * @param  string  $orderId
     * @param  array  $data
     */
    public function updateById(string $orderId, array $data)
    {
        return Consignment::query()->where('id', $orderId)->update($data);
    }

    /**
     * @param  string  $id
     * @param  string  $newStatus
     * @param  string|null  $oldStatus
     */
    public function reportStatuses(string $id, string $newStatus, ?string $oldStatus = null)
    {
        if (!$oldStatus) {
            ReportConsignment::query()->firstOrCreate(
                ['customer_id' => Auth::user()->id, 'organization_id' => getOrganization()]
            )->increment($newStatus);
        } else {
            ReportConsignment::query()->where($oldStatus, '>', 0)->decrement($oldStatus);
            ReportConsignment::query()->increment($newStatus);
        }
        ConsignmentStatusTime::query()->create(['consignment_id' => $id, 'key' => $newStatus]);
    }

    public function setLogActivity(string $id)
    {
        $consignment = Consignment::find($id);
        (new ActivityService())->setConsignmentLog(
            $consignment,
            trans("activity.consignment_$consignment->status"),
            $consignment->id
        );
    }

    /**
     * force delete consignment
     * @return mixed
     */
    public function forceDeleteConsignment() {
        $list = Consignment::query()
            ->withTrashed(true)
            ->whereNotNull('deleted_at')
            ->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) >= UNIX_TIMESTAMP(DATE_ADD(deleted_at,INTERVAL 60 MINUTE))')
            ->get();

        foreach ($list as $item) {
            DB::transaction(function () use ($item) {
                $item->forceDelete();
            });
        }
    }

    /**
     * restore consignment by customer
     * @param array $customerIds
     * @return mixed
     */
    public function restoreByCustomer(array $customerIds) {
        $list = Consignment::query()
            ->withTrashed(true)
            ->whereNotNull('deleted_at')
            ->whereIn('customer_id', $customerIds)
            ->get();

        foreach ($list as $item) {
            DB::transaction(function () use ($item) {
                $item->restore();
            });
        }
    }

    public function getConsignmentDoneByDate($startDate = null, $endDate = null)
    {
        $result = Consignment::query()
            ->with(["orderPackage"])
            ->whereHas('orderPackage', function ($query) {
                $query->where('order_type', OrderPackage::ORDER_TYPE_CONSIGNMENT);
            })
            ->where("status", Consignment::STATUS_RECEIVED_GOODS);
        if ($startDate) {
            $result = $result->whereDate("date_done", ">=", $startDate);
        }
        if ($endDate) {
            $result = $result->whereDate("date_done", "<=", $endDate);
        }
        return $result->get();
    }

    public function getConsignmentByDate($startDate = null, $endDate = null, $provinceId = null)
    {
        return Consignment::query()
            ->with(["customerDelivery.province", "orderStatusTime", "orderPackage"])
            ->where(function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->where(function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->whereDate("created_at", ">=", $startDate)
                            ->whereDate("created_at", "<=", $endDate);
                    })
                    ->orWhereHas('orderStatusTime', function ($subQueryTime) use ($startDate, $endDate) {
                        $subQueryTime->where("key", "status_2")
                            ->whereDate("created_at", ">=", $startDate)
                            ->whereDate("created_at", "<=", $endDate);
                    });
                }
            })
            ->whereHas('customerDelivery.province', function ($subQuery) use ($provinceId) {
                if ($provinceId) $subQuery->where('id', $provinceId);
            })
            ->get();
    }

    public function getListStatusByCustomer($customerId = false)
    {
        return $this->_model->query()->select('status', DB::raw('count(*) as total'))
            ->when($customerId, function ($when) use ($customerId){
                $when->where('customer_id', $customerId);
            })
            ->groupBy('status')->get();
    }
    public function syncWarehouseByCustomer(Customer $customer): void
    {
        $updatePackage = [];
        $updateConsignment = [];
        if (
            !is_null($customer->warehouse_id) &&
            $customer->getOriginal('warehouse_id') != $customer->warehouse_id
        ) {
            $updatePackage['warehouse_id'] = $customer->warehouse_id;
            $updateConsignment['warehouse_vi'] = $customer->warehouse_id;
        }
        if (
            !is_null($customer->warehouse_cn_id) &&
            $customer->getOriginal('warehouse_cn_id') != $customer->warehouse_cn_id
        ) {
            $updatePackage['warehouse_cn'] = $customer->warehouse_cn_id;
            $updateConsignment['warehouse_cn'] = $customer->warehouse_cn_id;
        }
        if (empty($updatePackage)) {
            return;
        }
        $consignmentIds = OrderPackage::query()
            ->where('customer_id', $customer->id)
            ->where('order_type', Consignment::class)
            ->whereIn('status', [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
            ])
            ->distinct()
            ->pluck('order_id')
            ->toArray();
        if (empty($consignmentIds)) {
            return;
        }
        OrderPackage::query()
            ->where('order_type', Consignment::class)
            ->whereIn('order_id', $consignmentIds)
            ->whereIn('status', [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
            ])
            ->update($updatePackage);
        Consignment::query()
            ->whereIn('id', $consignmentIds)
            ->update($updateConsignment);
    }
}
