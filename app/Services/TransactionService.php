<?php


namespace App\Services;

use App\Constants\CustomerConstant;
use App\Constants\TransactionConstant;
use App\Constants\DeliveryConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\RandomHelper;
use App\Http\Requests\Transaction\TransactionPaginateRequest;
use App\Http\Resources\Customer\CustomerWithdrawalPaginationResource;
use App\Http\Resources\TransactionResource;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\Role;
use App\Models\Transaction;
use App\Scopes\OrganizationScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\OrderPackage;
use App\Constants\PackageConstant;
use Carbon\Carbon;
use App\Models\FilePrint;

class TransactionService implements Service
{
    private ReportCustomerService $_reportCustomer;

    public function __construct()
    {
        $this->_reportCustomer = new ReportCustomerService();
    }

    /**
     * @param  float  $amount
     * @param  string  $status
     * @param  string  $content
     * @param  bool  $isIncrement
     * @param  string  $customerId
     * @param  null  $sourceable
     * @param bool $isBalanceNegative
     * @param null $order
     */
    public function setTransaction(
        float $amount,
        string $status,
        string $content,
        bool $isIncrement,
        string $customerId,
        $sourceable = null,
        $isBalanceNegative = false,
        $order = null
    ) {
        $balance = (new CustomerService())->getBalanceAmount($customerId);
        if ($isIncrement) {
            $result = $balance + $amount;
            $this->_reportCustomer->balanceAmountIncrease($amount, $customerId);
        } else {
            $result = $balance - $amount;
            if($result <= 0 && !$isBalanceNegative) {
                $amount = $balance;
                $result = 0;
            }
            $this->_reportCustomer->balanceAmountDecrease($amount, $customerId, $isBalanceNegative);
        }
        $balanceOld = 0;
        $idCheck = 0;
        if($customerId && ($sourceable && in_array(get_class($sourceable), [OrderPackage::class, FilePrint::class]))) {
            $time = 20; // Lấy mốc thời gian so sánh đơn vị là phút
            $timeCheck = Carbon::now()->subMinutes($time);
            $role = Role::where('name', 'like', '%ke-toan-tong-hop%')->first();
            // Lấy tất cả user thuộc vai trò kế  toán tổng hợp
            $userIds = User::whereHas('roles', function($q) use($role) {
                $q->where('id', '=', $role->id);
            })->pluck('id')->toArray();
            $queryCheck = Transaction::where('customer_id', $customerId)->whereBetween('time', ["2025-05-27 13:33:56", Carbon::now()])
                ->where('status', TransactionConstant::STATUS_DEPOSIT)->orderBy('time', 'asc')
                ->whereHas('fundTransaction', function($q) use ($userIds) {
                    return $q->whereIn('user_create_id', $userIds);
                })->first();
            if($queryCheck) {
                // Xử lý lưu thêm id của $queryCheck
                $total = Transaction::where('id_check', $queryCheck->id)->sum('amount');
                $balanceOld = $queryCheck->balance - $queryCheck->amount - $total;
                $idCheck = $queryCheck->id;
            }
        }

        $transactionDB = Transaction::query()->create(
            [
                'customer_id' => $customerId,
                'sourceable_type' => $sourceable ? get_class($sourceable) : null,
                'sourceable_id' => $sourceable ? $sourceable->id : null,
                'amount' => $amount,
                'time' => now(),
                'status' => $status,
                'content' => $content,
                'code' => RandomHelper::getTransactionCode(),
                'balance' => $result,
                'organization_id' => $sourceable->organization_id ?? getOrganization(),
                'order_type' => $order ? get_class($order) : null,
                'order_id' => optional($order)->id,
                'balance_old' => $balanceOld > 0 ? $balanceOld : 0,
                'id_check' => $idCheck
            ]
        );

        // Biến này mục đích để đẩy thử tự thanh toán và hoàn tiền của 2 hàm theo đúng vị trí
        // app/Services/DeliveryService.php : setTransactionsByOrderInDeliveryFix
        // app/Services/TransactionService.php : createTransationMergePack
        $postion_6308270688 = isset($_POST['postion_6308270688']) ? $_POST['postion_6308270688']: null;
        if (!is_null($postion_6308270688)) {
            // $transactionDB->created_at = date('Y-m-d H:i:s', (time() + $postion_6308270688));
            // $transactionDB->save();
        }
    }

    /**
     * @param  float  $amount
     * @param  string  $status
     * @param  string  $content
     * @param  string  $customerId
     * @param  null  $sourceable
     * @param  null  $order
     */
    public function setTransactionIncrement(
        float $amount,
        string $status,
        string $content,
        string $customerId,
        $sourceable = null,
        $order = null
    ) {
        $this->setTransaction($amount, $status, $content, true, $customerId, $sourceable, false, $order);
    }

    /**
     * @param  float  $amount
     * @param  string  $status
     * @param  string  $content
     * @param  string  $customerId
     * @param  null  $sourceable
     * @param  null  $order
     */
    public function setTransactionDecrement(
        float $amount,
        string $status,
        string $content,
        string $customerId,
        $sourceable = null,
        $order = null
    ) {
        $this->setTransaction($amount, $status, $content, false, $customerId, $sourceable, false, $order);
    }

    /**
     * @param  Delivery  $delivery
     * @param  array  $params
     */
    public function purchaseDelivery(Delivery $delivery, array $params): void
    {
        (new CustomerService())->getBalanceAmount($delivery->customer_id);
        DB::transaction(
            function () use ($delivery, $params) {
                $amount = $delivery->amount;
                if ($amount === 0) {
                    return;
                }
                $this->setDeliveryTransaction($delivery);
                $this->_reportCustomer->increaseOrderAmount($delivery->customer_id, $delivery->debt_cost);
                $this->_reportCustomer->updateLevel($delivery->customer_id);
            }
        );
    }

    /**
     * @param  Delivery  $delivery
     */
    public function setDeliveryTransaction(Delivery $delivery): void
    {
        $array = [
            'debt_cost' => 'delivery_debt_cost',
            'shipping_cost' => 'delivery_shipping_cost'
        ];
        $packages = $delivery->packages;
        $ordersPackage = $packages->filter(function ($item) {
            return $item->order_type == Order::class;
        });
        $consignmentsPackage = $packages->filter(function ($item) {
            return $item->order_type == Consignment::class;
        });
        $ordersCode = $this->getValuesByArrayAndColumn($ordersPackage, 'order_code');
        $consignmentsCode = $this->getValuesByArrayAndColumn($consignmentsPackage, 'order_code');

        foreach ($array as $key => $msg) {
            $amount = $delivery->{$key};
            if ($amount <= 0) {
                continue;
            }

            if ($key === 'shipping_cost') {
                $orders = $ordersCode;
            } else {
                $orders = array_unique(array_merge($ordersCode, $consignmentsCode));
            }

            $params = [
                'amount' => ConvertHelper::numericToVND($amount),
            ];

            if ($key !== 'debt_cost') {
                $params['bill'] = implode(
                    ',',
                    $this->getValuesByArrayAndColumn(
                        $key == 'shipping_cost' ? $ordersPackage : $packages,
                        'bill_code'
                    )
                );
            } else {
                $orders = $this->getOrders($ordersCode);
            }

            $params['code'] = implode(',', array_unique($orders));

//            $this->setTransaction(
//                $amount,
//                TransactionConstant::STATUS_PURCHASE,
//                $msg,
//                false,
//                $delivery->customer_id,
//                $delivery
//            );

            // set transaction by order
            $this->setTransactionByKey($key, $packages, $ordersPackage, $msg, $delivery);

            $msgNoti = trans(
                "transaction.$msg",
                $params
            );
            $this->updateNotiForClient($delivery->customer_id, $msgNoti);
        }
    }

    public function setTransactionByKey($key, $packages, $ordersPackage, $msg, $delivery)
    {
        if ($key === 'shipping_cost') {
            $countNextTime = 0;
            foreach($packages->all() as $package) {
                $order = $package->is_order ? $package->order : $package->consignment;

                $amount = $package->shipping_cost - $package->discount_cost;
                if ($amount <= 0) {
                    continue;
                }

                $params   = [
                    'amount' => ConvertHelper::numericToVND(floatval($amount)),
                    'bill'   => $package->bill_code,
                    'code'   => $package->order_code
                ];
                $msgTrans = trans(
                    "transaction.$msg",
                    $params
                );

                $countNextTime = $countNextTime +1;
                $_POST['postion_6308270688'] = $countNextTime;

                $this->setTransaction(
                    $amount,
                    TransactionConstant::STATUS_PURCHASE,
                    $msgTrans,
                    false,
                    $delivery->customer_id,
                    $delivery,
                    true,
                    $order
                );
            }
        } else { // debt_cost
            // Xử lý theo logic kiện hàng đầu tiên
            $this->createTransationFirstPack($ordersPackage, $delivery, $msg);
            return;
            // Xử lý logic mới
            $this->createTransationMergePack($ordersPackage, $delivery, $msg);
            return;
            \Log::info('Không chạy logic dưới nữa');
            foreach ($ordersPackage as $pack) {
                $order  = $pack->order;

                //$amount = (new OrderService())->getDebtCost([$pack->order_id]);
                $amount = $pack->is_order && $order ? optional($order)->debt_cost : 0;
                if ($amount <= 0) {
                    continue;
                }
                // Làm tròn lại tiền
                $amount = roundMoney($amount);

                $params   = [
                    'amount' => ConvertHelper::numericToVND(floatval($amount)),
                    'bill'   => $pack->bill_code,
                    'code'   => $pack->order_code
                ];
                $msgTrans = trans(
                    "transaction.$msg",
                    $params
                );

                $this->setTransaction(
                    $amount,
                    TransactionConstant::STATUS_PURCHASE,
                    $msgTrans,
                    false,
                    $delivery->customer_id,
                    $delivery,
                    true,
                    $order
                );
            }
        }
    }

    public function createTransationFirstPack($ordersPackage, $delivery, $msg)
    {
        // Tạo giao dịch tiền hàng còn thiếu chỉ theo đơn gộp
        $collection = collect($ordersPackage->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $orderModel = \App::make('App\Models\Order');
        $sum_debt_cost = 0;
        foreach ($grouped as $order_id => $item_packages) {
            $orderDB = $orderModel->where('id', $order_id)->first();
            // Nếu tồn tại orderDB thì nghĩa là nó là đơn order bth
            if (!is_null($orderDB) && $item_packages[0]['order_type'] == 'App\Models\Order') {

                $package_ids = $item_packages->pluck('id')->all();
                // $check = OrderPackage::query()->where(['order_id' => $order_id])
                //                     ->where('order_type', Order::class)
                //                     ->whereNotIn('id', $package_ids)
                //                     ->where('delivery_id', '!=', $delivery->id)
                //                     ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                //                     ->with(['delivery' => function($q) {
                //                         $q->whereIn('status', [DeliveryConstant::KEY_STATUS_RUN, DeliveryConstant::KEY_STATUS_DONE]);
                //                     }])
                //                     ->get();

                // Check theo logic mới như sau:
                // delivery_pay_id ở order = 0 nghĩa là chưa đc thanh toán phát nào
                $check = $orderDB->delivery_pay_id == 0 ? 0 : 1;
                // Nếu chưa có giao hàng nào thì mới tính tiền
                if ($check == 0) {
                    $amount = $orderDB->debt_cost;
                    if ($amount <= 0) {
                        continue;
                    }
                    // Làm tròn lại tiền
                    // $amount = roundMoney($amount); // Logic mới không cần làm tròn nữa
                    $sum_debt_cost += $amount;

                    $params   = [
                        'amount' => ConvertHelper::numericToVND(floatval($amount)),
                        'bill'   => $orderDB->code,
                        'code'   => $orderDB->code
                    ];
                    $msgTrans = trans(
                        "transaction.$msg",
                        $params
                    );

                    $orderDB->remaining_amount = 0;
                    $orderDB->save();

                    $this->setTransaction(
                        $amount,
                        TransactionConstant::STATUS_PURCHASE,
                        $msgTrans,
                        false,
                        $delivery->customer_id,
                        $delivery,
                        true,
                        $orderDB
                    );
                }

                // Nếu đã nhận 1 phần
                if ($check == 1 && $orderDB->remaining_amount > 0) {
                    $amount = $orderDB->debt_cost - $orderDB->delivery_pay_debt_cost;

                    if ($amount <= 0) {
                        continue;
                    }
                    // Làm tròn lại tiền
                    // $amount = roundMoney($amount); // Logic mới không cần làm tròn nữa
                    $sum_debt_cost += $amount;

                    $params   = [
                        'amount' => ConvertHelper::numericToVND(floatval($amount)),
                        'bill'   => $orderDB->code,
                        'code'   => $orderDB->code
                    ];
                    $msgTrans = trans(
                        "transaction.$msg",
                        $params
                    );

                    $orderDB->delivery_pay_debt_cost += $amount;
                    $orderDB->remaining_amount = 0;
                    $orderDB->save();

                    $this->setTransaction(
                        $amount,
                        TransactionConstant::STATUS_PURCHASE,
                        $msgTrans,
                        false,
                        $delivery->customer_id,
                        $delivery,
                        true,
                        $orderDB
                    );
                }
            }
        }
        return $sum_debt_cost;
    }

    public function createTransationMergePack($ordersPackage, $delivery, $msg)
    {
        // Tạo giao dịch tiền hàng còn thiếu chỉ theo đơn gộp
        $collection = collect($ordersPackage->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $orderModel = \App::make('App\Models\Order');
        foreach ($grouped as $order_id => $item_packages) {
            $orderDB = $orderModel->where('id', $order_id)->first();
            // Nếu tồn tại orderDB thì nghĩa là nó là đơn order bth
            if (!is_null($orderDB)) {
                $amount = $orderDB->debt_cost;
                if ($amount <= 0) {
                    continue;
                }
                // Làm tròn lại tiền
                $amount = roundMoney($amount);

                $params   = [
                    'amount' => ConvertHelper::numericToVND(floatval($amount)),
                    'bill'   => $orderDB->code,
                    'code'   => $orderDB->code
                ];
                $msgTrans = trans(
                    "transaction.$msg",
                    $params
                );

                $this->setTransaction(
                    $amount,
                    TransactionConstant::STATUS_PURCHASE,
                    $msgTrans,
                    false,
                    $delivery->customer_id,
                    $delivery,
                    true,
                    $orderDB
                );
            }
        }
    }

    private function updateNotiForClient($customerId, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content'  => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8012,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customerId,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    /**
     * @param $object
     * @param  string  $column
     * @return array
     */
    private function getValuesByArrayAndColumn($object, string $column): array
    {
        return optional($object)->pluck($column)->all();
    }

    /**
     * @param  array  $orders
     * @return array
     */
    private function getOrders(array $orders): array
    {
        return Order::query()->whereIn('code', $orders)->whereRaw('order_cost > deposit_cost')->get()->pluck(
            'code'
        )->all();
    }

    /**
     * get ewallet info by customer
     * @param array $params
     * @return JsonResponse
     */
    public function getTransactionByCustomer($params)
    {
        $data = Transaction::query()
            ->with('order')
            ->where('customer_id', $params['customer_id'])
            ->orderBy('id', 'DESC')
            ->paginate($params['per_page']);

        return resSuccessWithinData(new CustomerWithdrawalPaginationResource($data, TransactionResource::class));
    }

    /**
     * force delete transaction
     * @return mixed
     */
    public function forceDeleteTransaction() {
        $list = Transaction::query()
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
     * restore transactions by customer
     * @param array $customerIds
     * @return mixed
     */
    public function restoreByCustomer(array $customerIds) {
        $list = Transaction::query()
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

    public function getWithdrawalStatusPending()
    {
        $customerWithdrawalModel = \App::make('App\Models\CustomerWithdrawal');
        return $customerWithdrawalModel::query()
            ->where('status', CustomerConstant::KEY_WITHDRAWAL_STATUS_PENDING);
    }
}
