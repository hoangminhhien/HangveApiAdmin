<?php

namespace App\Jobs\Order;

use App\Http\Resources\Order\OrderListResource;
use App\Http\Resources\PaginateJsonResource;
use App\Models\Order;
use App\Models\User;
use App\Services\PerService;
use App\Traits\Order\OrderTrait;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateAdminCacheOrderList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use OrderTrait;

    /**
     * Danh sách các page trong pagination dùng để update cache value
     *
     * @var int[]
     */
    private array $pages = [1, 2, 3, 4];

    /**
     * Danh sách per page dùng để update cache value
     *
     * @var int[]
     */
    private array $perPages = [10];

    /**
     * Danh sách các relationships của đơn hàng order
     *
     * @var string[]
     */
    private array $relationships = [
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

        // 'notePublic',
        'notePrivate',

        'packages',
        'complains',
        'deliveries',
        'warehouse',
        'details',
    ];

    private array $emails = [
        'admin@thuongdo.com'
    ];

    /**
     * Paginate resource
     *
     * @var string
     */
    private string $paginateResource = PaginateJsonResource::class;

    /**
     * Resource
     *
     * @var string
     */
    private string $resource = OrderListResource::class;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Job này cập nhật giá trị lưu cache danh sách đơn hàng của Admin
     * Chỉ lưu danh sách không có filter, chứa duy nhất 3 trường 'page', 'per_page' và 'created_at_sort'
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Bắt đầu UpdateAdminCacheOrderList.handle');
        $pages = $this->pages;
        $perPages = $this->perPages;
        $emails = $this->emails;
        $perService = app()->make(PerService::class);
        $relationships = array_merge(
            $this->relationships,
            ['orderContracts' => fn($query) => $query->orderBy('created_at', 'desc')]
        );

        foreach ($pages as $page) {
            foreach ($perPages as $perPage) {
                foreach ($emails as $email) {
                    $authUser = User::query()->firstWhere('email', $email);

                    $rawQuery = Order::query();
                    $rawQuery->with($relationships);
                    $query = $perService->getConditionByUserLogin($rawQuery, null, $authUser);
                    $data = $query->paginate($perPage, ['*'], 'page', $page);

                    list($amount, $amountCny) = $this->getTotalAmount($query);
                    $extra = [
                        'amount' => $amount,
                        'amount_cny' => $amountCny
                    ];
                    $result = new $this->paginateResource($data, $this->resource, $extra);

                    Cache::put("ListOrderActionData_{$page}_{$perPage}", json_encode([
                        'data' => $result,
                        'time' => Carbon::now()->toDateTimeString(),
                    ]));
                }
            }
        }
        Log::info('Kết thúc UpdateAdminCacheOrderList.handle');
    }
}
