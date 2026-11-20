<?php

namespace App\Actions\Order;

use App\Actions\BaseAction;
use App\Constants\Order\OrderListConstant;
use App\Constants\OrderConstant;
use App\Http\Resources\Order\OrderListResource;
use App\Models\Order;
use App\Models\User;
use App\Services\PerService;
use App\Traits\Order\OrderTrait;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListOrderAction extends BaseAction
{
    use OrderTrait;

    /**
     * List Resource
     *
     * @var string
     */
    private string $resource = OrderListResource::class;

    /**
     * Các field dùng để filter khi lấy danh sách đơn hàng
     *
     * @var string[]
     */
    private array $filterFields = [
        'supplier_name',
        'product_name',
        'customer_level',
        'code_po',
        'staff_order_id',
        'staff_quotation_id',
        'staff_care_id',
        'staff_counselor_id',
        'staff_group_id',
        'is_website',
        'warehouse_id',
        'is_tax',
        'is_purchase',
        'customer_via',
        'customer_business_type',
        'ecommerces',
        'is_waiting_for_sale',
        'is_out_date',
        'type_time',
        'time',
    ];

    private string $cacheKey = "%sListOrderActionData%d%d";

    /**
     * Tiếp nhận dữ liệu, phân phối tới các hàm xử lý tương ứng
     *
     * @param array|null $params
     * @return JsonResponse
     */
    public function handle(?array $params): JsonResponse
    {
        $request = request();
        $isSubmittedFilter = Arr::find(
            $this->filterFields,
            fn ($field) => data_get($request, $field)
        );
        $authUser = auth()->user();

        // nếu đã lưu cache và chưa có order mới thì trả luôn
        // chỉ lưu cache danh sách không có filter, nên phải check xem request có kèm filter không ($isSubmittedFilter)
        $page = data_get($params, 'page');
        $perPage = data_get($params, 'perPage');
        $rawCachedData = Cache::get(sprintf(
            $this->cacheKey,
            data_get($authUser, 'email'),
            $page,
            $perPage
        ));
        if ($rawCachedData && !$isSubmittedFilter) {
            Log::info('$rawCachedData && !$isSubmittedFilter');
            $cachedData = json_decode($rawCachedData);
            $data = data_get($cachedData, 'data');
            $time = data_get($cachedData, 'time');

            // nếu có ít nhất 1 đơn đã được update sau khi lưu cache thì lấy lại dữ liệu để update cache
            $lastUpdatedOrder = Order::query()->firstWhere('updated_at', '>', $time);
            if (!$lastUpdatedOrder) {
                Log::info('!$lastUpdatedOrder');
                return resSuccessWithinData($data);
            }
        }

        // đã có order mới thì lấy lại dữ liệu
        Log::info('$this->getUpdatedData($params, $authUser)');
        return $this->getUpdatedData($params, $authUser);
    }

    /**
     * Lấy mới dữ liệu danh sách đơn hàng
     *
     * @param array $params
     * @param User|null $authUser
     * @return JsonResponse
     */
    private function getUpdatedData(array $params = [], ?User $authUser): JsonResponse
    {
        // dữ liệu truyền vào từ service
        $page = data_get($params, 'page');
        $perPage = data_get($params, 'perPage');
        $status = data_get($params, 'status');
        $ecommerces = data_get($params, 'ecommerces');
        $time = data_get($params, 'time');

        $perService = app()->make(PerService::class);
        $query = Order::query();
        // TODO: Refactor nhằm giảm số query khi with quá nhiều relationships
        // Có thể xử lý bằng cách tách rời dữ liệu ra các API khác nhau, rồi xử lý lại ở Frontend
        //   + Một API chỉ chứa đúng các dữ liệu để hiển thị lần đầu load
        //   + Một hoặc một vài API chứa các dữ liệu khác như accounting...
        $query->with([
            'customer.warehouse.province',
            'customerDelivery',
            'customer.report',
            'customer.delivery',
            'customer.offer',

            'staffOrder',
            'staffSale',
            'staffCare',
            'staffCounselor',
            'staffQuotation',

            'orderStatusTime',
            'orderSuppliers',
            'orderContracts' => fn($query) => $query->orderBy('created_at', 'desc'),

            // 'notePublic',
            'notePrivate',

            'packages',
            'complains',
            'deliveries',
            'warehouse',
            'details',
        ]);

        $query = $perService->getConditionByUserLogin($query, $authUser);

        if ($ecommerces) {
            if ($ecommerces == OrderListConstant::ECOMMERCE_OTHER) {
                $query->whereNull('ecommerce');
            } else {
                $query->where('ecommerce', $ecommerces);
            }
        }
        if ($status == OrderListConstant::ORDER_STATUS_1 && $time) {
            $dateRange = $this->dateRange($time);
            $query = $query->withoutGlobalScopes()
                ->whereHas(
                    'orderStatusTime',
                    fn($q) => $q->whereBetween(
                        'order_status_times.status_1',
                        [data_get($dateRange, 'startDate'), data_get($dateRange, 'endDate')],
                    )
                );
        }
        if ($status == OrderListConstant::ORDER_STATUS_EXCEPTION_DONE) {
            $query = $query->withoutGlobalScopes()
                ->whereNotIn(
                    'status',
                    [OrderConstant::KEY_STATUS_DONE, OrderConstant::KEY_STATUS_CANCEL]
                );
        }
        if ($time && $status != OrderListConstant::ORDER_STATUS_1) {
            $dateRange = $this->dateRange($time);
            if ($status == 'outdate_not_done') {
                $startDate = date('Y-m-d H:i:s', strtotime('-7 day', strtotime($dateRange['startDate'])));
                $endDate = date('Y-m-d H:i:s', strtotime('-7 day', strtotime($dateRange['endDate'])));
                $query->whereBetween('created_at', [$startDate, $endDate]);
            } else if ($status == '') {
                $query->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']]);
            } else {
                $query->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']]);
            }
        }

        $data = $query->paginate($perPage, ['*'], 'page', $page);
        if ($status == OrderListConstant::ORDER_STATUS_OUTDATE) {
            $orderOutDate = $query->whereNotIn(
                'status',
                [OrderConstant::KEY_STATUS_DONE, OrderConstant::KEY_STATUS_CANCEL]
            )->withoutGlobalScopes()->orderBy('created_at', 'desc')->get();
            $data = $this->ArrToCollection($orderOutDate, $perPage);
        }

        list($amount, $amountCny) = $this->getTotalAmount($query);
        $extra = [
            'amount' => $amount,
            'amount_cny' => $amountCny,
        ];

        // query xong thì lưu log lại, rồi return
        $result = new $this->paginateResource($data, $this->resource, $extra);
        $cacheKey = sprintf(
            $this->cacheKey,
            data_get($authUser, 'email'),
            $page,
            $perPage
        );
        Cache::put($cacheKey, json_encode([
            'data' => $result,
            'time' => Carbon::now()->toDateTimeString(),
        ]));
        return resSuccessWithinData($result);
    }
}
