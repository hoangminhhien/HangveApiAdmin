<?php


namespace App\Services;


use App\Constants\CarrierConstant;
use App\Constants\DeliveryConstant;
use App\Constants\NoteConstant;
use App\Constants\TransactionConstant;
use App\Helpers\AccountingHelper;
use App\Helpers\ConvertHelper;
use App\Helpers\RandomHelper;
use App\Http\Resources\Delivery\DeliveryResource;
use App\Http\Resources\Delivery\ListDetailResource;
use App\Jobs\ExportDeliveryJob;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryNote;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderStatus;
use App\Models\Order;
use App\Models\Consignment;
use App\Models\OrderPackage;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

use App\Constants\PackageConstant;
use App\Models\CustomerOffer;

use App\Exports\DeliveryExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;


class DeliveryService extends BaseService
{
    protected string $_resource = DeliveryResource::class;

    /**
     * @param string $orderId
     * @return int
     */

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        $perService = \App::make('App\Services\PerService');
        $params = request()->all();

        $query = $this->_model->newQuery()->select('deliveries.*');
        $query->with([
            'notesStaff', 'deliveryOrderStatus', 'transporter', 'transporterDetail', 'customerDelivery',
            'customer.warehouse', 'customer.report', 'order:id,code',
            'customer' => function($q){
                return $q->select('id', 'name', 'phone_number', 'warehouse_id', 'source');
            },
            'orderPackages' => function($q){
                return $q->select('id', 'delivery_id', 'bill_code');
            },
        ])
            ->when(data_get($params, 'is_received') != null, function($q) use ($params){
                $q->where('deliveries.status', data_get($params, 'is_received') == 1 ? '=' : '!=', DeliveryConstant::KEY_STATUS_DONE);
            });

        $query = $perService->getConditionByUserLogin($query);

        // Lọc thêm theo cả sdt của khách
        $phone_number = isset($params['phone_number']) ? $params['phone_number'] : null;
        if ($phone_number) {
            $customerModel = \App::make('App\Models\Customer');
            $customerDB = $customerModel->where('phone_number', $phone_number)->first();

            $query->where(function ($q) use ($phone_number, $customerDB) {
                $q->where('deliveries.phone_number', $phone_number); // thêm bảng deliveries
                if ($customerDB) {
                    $q->orWhere('deliveries.customer_id', $customerDB->id); // thêm bảng deliveries
                }
            });
        }
        $query = $query->orderBy('created_at', 'desc');
        $query = $query->groupBy('deliveries.id');
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function getStatusesDone(string $orderId): int
    {
        return Delivery::query()->join('orders', 'orders.delivery_id', '=', 'deliveries.id')->where(
            ['orders.id' => $orderId, 'deliveries.status' => DeliveryConstant::KEY_STATUS_DONE]
        )->count();
    }

    public function store(array $data)
    {
        $fees        = [];
        $checkWallet = $this->checkWallet($fees, $data['packages'], $data['customer_delivery_id']);
        $data        += $fees;
        return DB::transaction(
            function () use ($data, $checkWallet) {
                $packages = $data['packages'];
                $delivery = $this->storeByOrder($data);
                if ($data['payment'] === DeliveryConstant::PAYMENT_E_WALLET) {
                    // enoughMoneyToPay($checkWallet); // Logic ngày 29/09/2024
                    //(new TransactionService())->purchaseDelivery($delivery, $packages);
                }
                // Logic ngày 29/09/2024 ngắt hàm dưới này, chuyển về sau khi chọn hoàn thành giao hàng mới thực hiện gọi nó để trừ tiền
                // Logic ngày 27/01/2026 Lại bật lại hàm này, trừ tiền ngay
                // (new TransactionService())->purchaseDelivery($delivery, $packages);
                // Thêm noti
                $this->updateDeliveryNotiLogic($delivery);

                return $delivery;
            }
        );
    }

    public function updateDeliveryNotiLogic($delivery)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_pack = [
            'delivery_id' => $delivery->id,
        ];

        $data = [
            'group'           => 4,
            'name'            => 4001,
            'data'            => json_encode($data_pack),
            'time_create'     => time(),
            'status'          => 1
        ];

        $orderDB = $delivery->order;

        if ($orderDB) {
            $staff_care_id      = $orderDB->staff_care_id;
            $staff_counselor_id = $orderDB->staff_counselor_id;
            if ($staff_care_id) {
                $notificationService->storeNoti($staff_care_id, $data);
            }
            if ($staff_counselor_id) {
                $notificationService->storeNoti($staff_counselor_id, $data);
            }
        }
    }

    /**
     * @param array $data
     * @return Delivery
     */
    public function storeByOrder(array $data): Delivery
    {
        /** @var CustomerDelivery $customDelivery */
        $customDelivery            = CustomerDelivery::query()->select(
            'receiver',
            'address',
            'phone_number',
            'delivery_cost',
            'province_id',
            'district_id',
            'ward_id',
            'customer_id'
        )->findOrFail(
            $data['customer_delivery_id']
        )->toArray();
        $customDelivery['address'] = $customDelivery['address_only'];
        $data                      += $customDelivery;

        // bỏ phí vc tạm tính
        $data['delivery_cost']           = 0;
        $params['is_delivery_cost_paid'] = null;

        $packages                            = OrderPackage::query()->find($data['packages']);
        $order                               = optional($packages->first())->order;
        $packageFirst = $packages->first();
        $data['code']                        = RandomHelper::getDeliveryCode();
        //$data['is_delivery_cost_paid']       = $data['delivery_cost'] > 0;
        $data['shock_proof_cost']            = $packages->sum('shock_proof_cost');
        $data['storage_cost']                = $packages->sum('storage_cost');
        $data['woodworking_cost']            = $packages->sum('woodworking_cost');
        $data['inspection_cost']             = $packages->sum('inspection_cost');
        $data['insurance_cost']              = $packages->sum('insurance_cost');
        $data['international_shipping_cost'] = $packages->sum('international_shipping_cost');
        $data['china_shipping_cost']         = $packages->sum('china_shipping_cost') * data_get($packageFirst, 'exchange_rate') ?? 1;
        $data['discount_cost']               = $packages->sum('discount_cost');
        $data['organization_id']             = getOrganization();
        $data['order_id']                    = optional($order)->id;
        $data['order_type']                  = $order ? get_class($order) : null;
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create($data);
        foreach ($packages as $package) {
            $order = $package->order;
            if($order) {
                $dataDeliveryOrder = [
                    'delivery_id' => $delivery->id,
                    'order_type' => $order ? get_class($order) : null,
                    'order_id' => data_get($order, 'id'),
                ];
                $checkExist = DeliveryOrder::where($dataDeliveryOrder)->first();
                if($checkExist == null) {
                    DeliveryOrder::query()->create($dataDeliveryOrder);
                    $order->update(['delivery_id' => $delivery->id]);
                    if ($order instanceof Order) {
                        (new ActivityService())->setOrderLog($delivery, trans("activity.order_delivery"), $order->id);
                        (new OrderService())->incrementByColumn($order->id, 'deliveries_number');
                    }else {
                        (new ActivityService())->setConsignmentLog($delivery, trans("activity.order_delivery"), $order->id);
                        (new ConsignmentService())->incrementByColumn($order->id, 'deliveries_number');
                    }
                }
            }
        }

        // Key delivery_id ở bảng order ko dùng, mà dùng ở DeliveryOrder

        $orderPackageIds = [];
        OrderPackage::query()->findMany($data['packages'])->each(
            function ($item, $key) use ($delivery, &$orderPackageIds) {
                $dataUpdate = ['delivery_id' => $delivery->id, 'is_delivery' => true];

                if (!isset($orderPackageIds[$item->order_id])) {
                    $dataUpdate['is_pack_first'] = 1;
                }
                $item->update(
                    $dataUpdate
                );

                $orderPackageIds[$item->order_id] = 1;
            }
        );

        if (isset($data['note']) && $data['note']) {
            $this->storeNote($delivery->id, $data['note']);
        }

        // $this->setLogs($delivery, $packages);
        return $delivery;
    }

    public function detail(string $id): JsonResponse
    {
        $deliveryDetail = Delivery::query()->with([
            'transporter', 'transporterDetail', 'packages',
        ])->findOrFail($id);
        return resSuccessWithinData(new ListDetailResource($deliveryDetail));
    }

    public function checkOrderPackFirst($deliveryId)
    {
        return !OrderPackage::query()
                    ->where('order_id', $deliveryId)
                    ->where('is_pack_first', 1)
                    ->exists();
    }

    /**
     * @param array $params
     * @param array $packages
     * @param string $customerDeliveryId
     * @return bool
     */
    private function checkWallet(array &$params, array $packages, string $customerDeliveryId): bool
    {
        $packages         = OrderPackage::query()->findMany($packages);
        $customerDelivery = CustomerDelivery::query()->findOrFail($customerDeliveryId);
        $delivery         = optional($customerDelivery)->delivery_cost ?? 0;
        $debt             = $this->getDebtCostByOrder($packages);
        $shipping         = $packages->sum('shipping_cost');
        $params           = ['delivery_cost' => $delivery, 'debt_cost' => roundMoney($debt), 'shipping_cost' => $shipping];
        return (new CustomerService())->getBalanceAmount(
                $customerDelivery->customer_id
            ) < ($delivery + $debt + $shipping);
    }

    /**
     * @param Collection $packages
     * @return float
     */
    public function getDebtCostByOrder(Collection $packages): float
    {
        // Logic golive
        $ordersPackage = $packages->filter(function ($item) {
            return $item->order_type == Order::class;
        });

        $orderModel = \App::make('App\Models\Order');
        $collection = collect($ordersPackage->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $total_debt_cost = 0;
        foreach ($grouped as $order_id => $packs) {
            $orderDB = $orderModel->where('id', $order_id)->first();
            // Nếu chưa ghi nhận lấy dc tiền hàng còn thiếu
            if ($orderDB && $orderDB->delivery_pay_id == 0) {
                $total_debt_cost += $orderDB->debt_cost;
            }

            // Nếu đã nhận 1 phần
            if ($orderDB && $orderDB->delivery_pay_id > 0) {
                if ($orderDB->remaining_amount > 0) {
                    $debt_cost_more = $orderDB->debt_cost - $orderDB->delivery_pay_debt_cost;
                    $total_debt_cost += $debt_cost_more;
                }
            }
        }

        return $total_debt_cost;

        // Logic mới
        $debt     = 0;
        $orderIds = $packages->pluck('order_id')->all();
        $packages = $packages->pluck('id')->all();
        foreach (array_unique($orderIds) as $orderId) {
            $check = OrderPackage::query()->where(['order_id' => $orderId])
                                ->whereNotIn('id', $packages)
                                ->whereNotNull('delivery_id')
                                ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                                ->count();
            // Nếu chưa có giao hàng nào thì mới tính tiền
            if ($check == 0) {
                $debt += (new OrderService())->getDebtCost([$orderId]);
            }
        }
        return $debt;

        // Logic cũ:

        $debt     = 0;
        $orderIds = $packages->pluck('order_id')->all();
        $packages = $packages->pluck('id')->all();
        foreach (array_unique($orderIds) as $orderId) {
            if (!OrderPackage::query()->where(['order_id' => $orderId])->whereNotIn('id', $packages)->whereNull('delivery_id')->exists()) {
                $debt += (new OrderService())->getDebtCost([$orderId]);
            }
        }
        return $debt;
    }

    /**
     * @param Delivery $delivery
     * @param $packages
     */
    private function setLogs(Delivery $delivery, $packages)
    {
        foreach ($packages as $package) {
            $order = $package->order;
            if($order) {
                $order->update(['delivery_id' => $delivery->id]);
                if ($order instanceof Order) {
                    (new ActivityService())->setOrderLog($delivery, trans("activity.order_delivery"), $order->id);
                    (new OrderService())->incrementByColumn($order->id, 'deliveries_number');
                    continue;
                }

                (new ActivityService())->setConsignmentLog($delivery, trans("activity.order_delivery"), $order->id);
                // (new ConsignmentService())->incrementByColumn($order->id, 'deliveries_number');

                //Đoạn code phía trên không chạy dc phải thay bằng cách lấy này
                $consignmentService = \App::make('App\Services\ConsignmentService');
                $consignmentService->incrementByColumn($order->id, 'deliveries_number');
            }
        }
    }

    public function update(string $id, array $data)
    {
        $package = new OrderPackageService();

        $delivery = Delivery::query()->find($id);
        $dataRefund = json_decode($delivery->refund, true) ?? ['amount' => 0, 'bill_code' => []];

        // Check nó là phương thức cod và không thay đổi sang ví
        $isPaymentCod = isset($data['payment'])
            && $data['payment'] === $delivery->payment
            && $delivery->payment !== DeliveryConstant::PAYMENT_E_WALLET;
        $isCodToWallet = isset($data['payment'])
            && $data['payment'] !== $delivery->payment
            && $delivery->payment !== DeliveryConstant::PAYMENT_E_WALLET; // Cod sang ví
        $isWalletToCod = isset($data['payment'])
            && $data['payment'] !== $delivery->payment
            && $delivery->payment === DeliveryConstant::PAYMENT_E_WALLET; // Ví sang cod

        $package->updateDeliveryIdByIds($data['packages']['add'] ?? [], $id, $isCodToWallet, $isPaymentCod);
        $package->updateDeliveryIdByIds($data['packages']['remove'] ?? [], null, $isCodToWallet, $isPaymentCod);

        // Nếu thêm kiện thì thêm logic (18/05/2024)
        $package_add = isset($data['packages']['add']) ? $data['packages']['add'] : null;
        if ($package_add) {
            $this->updateDebtCostAndShipping($delivery, $package_add);
        }

        if ($isPaymentCod) {
            return resSuccessWithinData(new DeliveryResource(parent::update($id, $data)));
        }
        $deliveryOldCost = $delivery->delivery_cost ?? 0;
        // bỏ phí vc tạm tính theo địa chỉ giao hàng mới, giữ nguyên phí vc cũ
        $deliveryNewCost = $deliveryOldCost; // Tạm thời
        //$deliveryNewCost = optional($delivery->customerDelivery)->delivery_cost ?? 0;
        $amount          = $deliveryOldCost - $deliveryNewCost;
        // trường hợp này là update kiện. Phương thức vẫn giữ nguyên là ví
        if (
            isset($data['payment']) && $data['payment'] === $delivery->payment
            && $delivery->payment === DeliveryConstant::PAYMENT_E_WALLET
        ) {
            if ($amount >= 0) {
                $dataRefund['amount'] += $amount;
            } else {
                $this->purchaseDelivery($delivery, $deliveryOldCost, $deliveryNewCost);
            }
        }
        // Chuyển từ cod sang ví
//        if ($isCodToWallet) {
//            // Thanh toán
//            $this->purchaseDeliveryChangePaymentMethod($delivery);
//        }
//        // Chuyển từ ví sang cod
//        if ($isWalletToCod) {
//            $dataRefund['amount'] += $delivery->amount - $amount;
//            // Hoàn tiền
//            $this->purchaseDeliveryRefund($delivery);
//        }
        $dataRefund['bill_code'] = $delivery->packages->pluck('bill_code')->all();
        if (isset($data['payment']) && $data['payment'] === DeliveryConstant::PAYMENT_E_WALLET) {
            $dataRefund['amount'] = 0;
        }
        $data['refund']        = json_encode($dataRefund);
        $data['delivery_cost'] = $deliveryNewCost;

        return resSuccessWithinData(new DeliveryResource(parent::update($id, $data)));
    }

    public function updateDebtCostAndShipping($delivery, $package_add)
    {
        $packageModel       = \App::make('App\Models\OrderPackage');
        $transactionService = \App::make('App\Services\TransactionService');

        $orderPackages = $packageModel->whereIn('id', $package_add)->get();

        // Thêm giao dịch tiền hàng còn thiếu
        $sum_debt_cost_add = $transactionService->createTransationFirstPack($orderPackages, $delivery, 'delivery_debt_cost');

        // Thêm phần phí vận chuyển của các kiện mới thêm
        $sum_international_shipping_cost_add = $orderPackages->sum('international_shipping_cost');

        // Cập nhật tiền hàng còn thiếu và phí ship TQ vào giao hàng
        $delivery->debt_cost += $sum_debt_cost_add;
        $delivery->international_shipping_cost += $sum_international_shipping_cost_add;
        $delivery->save();

        // Thêm vào bảng phụ quan hệ
        foreach ($orderPackages as $package) {
            $deliveryOrder = DeliveryOrder::firstOrCreate([
                'delivery_id' => $delivery->id,
                'order_type'  => $package->order_type,
                'order_id'    => $package->order_id,
            ]);
        }
    }

    /**
     * @param Delivery $delivery
     */
    public function purchaseDeliveryRefund(Delivery $delivery)
    {
        $orderPackages = $delivery->packages;
        $orderCodes = [];
        $shippingCost = 0;
        //$debtCost = 0;
        //$orderIdPackFirst  = [];
        $transactionOrders = [];

        foreach($orderPackages as $package) {
            $orderCodes[] = $package->order_code;

            $packShippingCost = $package->shipping_cost ? $package->shipping_cost : 0;
            $shippingCost    += $packShippingCost;

            $order   = $package->is_order ? $package->order : $package->consignment;
            $orderId = optional($order)->id;
            $transactionOrders[$orderId] = [
                'shipping_cost' => $packShippingCost,
                'debt_cost'     => $package->is_pack_first == 1 && $package->is_order && $order ?
                    optional($order)->debt_cost : 0, // Kiện hàng của đơn nào về VN đầu tiên thì mới hoàn. Còn không thì không hoàn
                'order'         => $order,
                'code'          => $package->order_code
            ];


//            if($package->is_pack_first == 1) {
//                $orderIdPackFirst[] = $orderId;
//            }
        }

        // Kiện hàng của đơn nào về VN đầu tiên thì mới hoàn. Còn không thì không hoàn
//        if(!empty($orderIdPackFirst)) {
//            foreach ($orderIdPackFirst as $orderId) {
//                $debtCostOrder = (new OrderService())->getDebtCost([$orderId]);
//                $debtCost     += $debtCostOrder;
//
//                $transactionOrders[$orderId]['debt_cost'] = $debtCostOrder;
//            }
//        }
        // Tiền hàng còn thiếu + tổng phí ship
//        $amount = $shippingCost + $debtCost;
//        $content = __(
//            'transaction.delivery_change_payment_method',
//            [
//                'name'      => getCurrentUser()->name,
//                'code'      => implode(', ', $orderCodes),
//                'amountOld' => number_format(AccountingHelper::getCosts($amount)),
//            ]
//        );
//        (new TransactionService())->setTransactionIncrement(
//            AccountingHelper::getCosts($amount),
//            TransactionConstant::STATUS_REFUND,
//            $content,
//            $delivery->customer_id,
//            $delivery
//        );

        // set transactions by order in delivery
        foreach ($transactionOrders as $orderId => $order) {
            $amountOrder = $order['shipping_cost'] + $order['debt_cost'];
            $amountOrder = AccountingHelper::getCosts($amountOrder);

            $content = __(
                'transaction.delivery_change_payment_method',
                [
                    'name'      => getCurrentUser()->name,
                    'code'      => $order['code'],
                    'amountOld' => number_format($amountOrder),
                ]
            );

            (new TransactionService())->setTransactionIncrement(
                $amountOrder,
                TransactionConstant::STATUS_REFUND,
                $content,
                $delivery->customer_id,
                $delivery,
                $order['order']
            );
        }

        $this->updateNoti($delivery->customer_id, $content);
    }

    /**
     * @param Delivery $delivery
     * @param float $amountOld
     * @param float $amountNew
     */
    public function purchaseDelivery(Delivery $delivery, float $amountOld, $amountNew)
    {
        $orderPackages = $delivery->packages;
        $orderCodes = [];
        foreach($orderPackages as $package) {
            $orderCodes[] = $package->order_code;
        }
        $content = __(
            'transaction.delivery_change_payment_method',
            [
                'name'      => getCurrentUser()->name,
                'code'      => implode(', ', $orderCodes),
                'amountOld' => number_format(AccountingHelper::getCosts($amountNew - $amountOld)),
            ]
        );
        (new TransactionService())->setTransactionIncrement(
            AccountingHelper::getCosts($amountNew - $amountOld),
            TransactionConstant::STATUS_REFUND,
            $content,
            $delivery->customer_id,
            $delivery
        );
        $this->updateNoti($delivery->customer_id, $content);
    }

    /**
     * @param Delivery $delivery
     */
    public function purchaseDeliveryChangePaymentMethod(Delivery $delivery)
    {
        $orderPackages = $delivery->packages;
        $orderCodes = [];
        $billCodes = [];
        $shippingCost = 0;
        //$debtCost = 0;
        //$orderIds = [];
        $transactionOrders = [];

        foreach($orderPackages as $package) {
            $billCodes[] = $package->bill_code;
            $orderCodes[] = $package->order_code;
            $shippingCost += $package->shipping_cost;
            //$orderIds[] = optional($package->order)->id;

            $order                       = $package->is_order ? $package->order : $package->consignment;
            $orderId                     = optional($order)->id;
            $packShippingCost            = $package->shipping_cost ? $package->shipping_cost : 0;
            $transactionOrders[$orderId] = [
                'shipping_cost' => $packShippingCost,
                'debt_cost'     => $package->is_order && $order ? optional($order)->debt_cost : 0,
                'order'         => $order,
                'code'          => $package->order_code,
                'bill_code'     => $package->bill_code
            ];
        }

//        if(!empty($orderIds)) {
//            foreach (array_unique($orderIds) as $orderId) {
//                $debtCost += (new OrderService())->getDebtCost([$orderId]);
//            }
//        }
//        // Tiền hàng còn thiếu + tổng phí ship
//        $amount = $shippingCost + $debtCost;
//        $content = __(
//            'transaction.delivery_shipping_cost',
//            [
//                'amount'        => ConvertHelper::numericToVND($amount),
//                'bill'          => implode(', ', array_unique($billCodes)),
//                'code'          => implode(', ', array_unique($orderCodes))
//            ]
//        );
//        (new TransactionService())->setTransactionDecrement(
//            $amount,
//            TransactionConstant::STATUS_PURCHASE,
//            $content,
//            $delivery->customer_id,
//            $delivery
//        );

        // set transactions by order in delivery
        foreach ($transactionOrders as $orderId => $order) {
            $amountOrder = $order['shipping_cost'] + $order['debt_cost'];

            $content = __(
                'transaction.delivery_shipping_cost',
                [
                    'amount' => ConvertHelper::numericToVND($amountOrder),
                    'bill'   => $order['bill_code'],
                    'code'   => $order['code']
                ]
            );

            (new TransactionService())->setTransactionDecrement(
                $amountOrder,
                TransactionConstant::STATUS_PURCHASE,
                $content,
                $delivery->customer_id,
                $delivery,
                $order['order']
            );
        }

        $this->updateNoti($delivery->customer_id, $content);
    }

    public function updateNoti($customerId, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content'  => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8010,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customerId,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    /**
     * @param Delivery $delivery
     * @param float $amount
     * @param string $content
     */
    public function refundDelivery(Delivery $delivery, float $amount, string $content)
    {
        (new TransactionService())->setTransactionIncrement(
            $amount,
            TransactionConstant::STATUS_REFUND,
            $content,
            $delivery->customer_id,
            $delivery
        );
    }

    /**
     * @param Delivery $delivery
     */
    public function setTransactionsByOrderInDelivery(Delivery $delivery)
    {
        return;
        $packages = $delivery->packages;

        foreach ($packages as $package) {
            $order        = $package->is_order ? $package->order : $package->consignment;

            $shippingCost = $package->shipping_cost ? $package->shipping_cost : 0;
            $debtCost     = $package->is_order && $order ? optional($order)->debt_cost : 0;
            $amount       = ($shippingCost - $package->discount_cost) + $debtCost;

            $content = __('transaction.delivery_destroy',
                [
                    'amount' => ConvertHelper::numericToVND(floatval($amount)),
                    'bill'   => $package->bill_code,
                    'name'   => getCurrentUser()->name
                ]
            );

            (new TransactionService())->setTransactionIncrement(
                $amount,
                TransactionConstant::STATUS_REFUND,
                $content,
                $delivery->customer_id,
                $delivery,
                $order
            );
        }
    }

    // Sửa lại lỗi trừ tiền 2 lần ở giao dịch setTransactionsByOrderInDelivery
    public function setTransactionsByOrderInDeliveryFix(Delivery $delivery)
    {
        // Logic ngày 22/10/2024: ko hoàn tiền nữa
        return;
        $ordersPackage = $delivery->packages;

        // Hoàn tiền hàng còn thiếu
        $collection = collect($ordersPackage->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $orderModel = \App::make('App\Models\Order');
        foreach ($grouped as $order_id => $item_packages) {
            $orderDB = $orderModel->where('id', $order_id)->first();
            // Nếu tồn tại orderDB thì nghĩa là nó là đơn order bth
            if (!is_null($orderDB)) {
                $package_ids = $item_packages->pluck('id')->all();
                $check = OrderPackage::query()->where(['order_id' => $order_id])
                                    ->whereNotIn('id', $package_ids)
                                    ->whereNotNull('delivery_id')
                                    ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                                    ->count();

                // Nếu chưa có giao hàng nào thì mới tính tiền
                if ($check == 0) {
                    $amount = $orderDB->debt_cost;
                    if ($amount <= 0) {
                        continue;
                    }

                    // Làm tròn lại tiền
                    $amount = roundMoney($amount);

                    $content = __('transaction.delivery_destroy',
                        [
                            'amount' => ConvertHelper::numericToVND(floatval($amount)),
                            'bill'   => $orderDB->code,
                            'name'   => getCurrentUser()->name
                        ]
                    );

                    (new TransactionService())->setTransactionIncrement(
                        $amount,
                        TransactionConstant::STATUS_REFUND,
                        $content,
                        $delivery->customer_id,
                        $delivery,
                        $orderDB
                    );
                }
            }
        }


        // Hoàn phần phí ship giao hàng
        $countNextTime = 0;
        foreach ($ordersPackage as $package) {
            $order        = $package->is_order ? $package->order : $package->consignment;
            $shippingCost = $package->shipping_cost ? $package->shipping_cost : 0;
            $amount       = ($shippingCost - $package->discount_cost);

            $content = __('transaction.delivery_destroy_2',
                [
                    'amount' => ConvertHelper::numericToVND(floatval($amount)),
                    'bill'   => $package->bill_code,
                    'order_code'   => $package->order_code,
                    'name'   => getCurrentUser()->name
                ]
            );

            $countNextTime = $countNextTime +1;
            $_POST['postion_6308270688'] = $countNextTime;

            (new TransactionService())->setTransactionIncrement(
                $amount,
                TransactionConstant::STATUS_REFUND,
                $content,
                $delivery->customer_id,
                $delivery,
                $order
            );
        }
    }


    public function destroy(string $id): JsonResponse
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->findOrFail($id);
        // Logic ngày 23/01/2024 thì kể cả là COD cũng hoàn tiền
        // Nếu khác cod thì mới tạo giao dịch
        // if($delivery->payment != 'cod') {
            $content = __('transaction.delivery_destroy',
                [
                'amount' => ConvertHelper::numericToVND($delivery->amount),
                'bill' => implode(',', $delivery->orderPackages->pluck('bill_code')->all()),
                'name' => getCurrentUser()->name
                ]
            );
//            $this->refundDelivery(
//                $delivery,
//                $delivery->amount,
//                $content
//            );
            // $this->setTransactionsByOrderInDelivery($delivery);
            // $this->setTransactionsByOrderInDeliveryFix($delivery);
            // push noti for client
            $this->updateNotification($delivery, $content);
        // }

        // Logic ngày 27/01/2026 cho hoàn lại tiền
        $this->backMoneyWhenDestroyOrCancelDelivery($delivery);

        $delivery->orderPackages()->update(['delivery_id' => null, 'is_delivery' => false]);
        (new OrderPackageService())->packageRemoveDelivery($id);

        return parent::destroy($id);
    }

    public function dateCheck($delivery)
    {
        $dateFrom = '2026-01-30 08:20:00';
        $dateEnd  = '2026-01-30 11:02:00';

        $createAtDB = $delivery->created_at->format('Y-m-d H:i:s');
        if($createAtDB >= $dateFrom && $createAtDB <= $dateEnd) {
            return true;
        }
        return false;
        $flag = false; // Mặc định là logic cũ

        $dateCheck = '2026-01-28 10:00:00';
        if (env('APP_URL') == 'https://api-admin.hangve.com') {
            $dateCheck = '2026-01-30 08:20:00';
        }

        $createAtDB = $delivery->created_at->format('Y-m-d H:i:s');
        if($createAtDB >= $dateCheck) {
            return true;
        }

        return $flag;
    }

    public function backMoneyWhenDestroyOrCancelDelivery($delivery, $type = 'destroy')
    {
        return;
        $check = $this->dateCheck($delivery);
        if ($check == false) {
            return;
        }
        // Hoàn lại tiền đặt cọc
        $ordersPackages = OrderPackage::query()->where('delivery_id', $delivery->id)
                        ->where('order_type', 'App\Models\Order')
                        ->get();

        if (count($ordersPackages) > 0) {
            foreach ($ordersPackages as $key => $ordersPackage) {
                $orderDB = Order::query()->where('id', $ordersPackage->order_id)->first();
                if (!is_null($orderDB)) {
                    if ($orderDB->delivery_pay_id > 0) {
                        // Check xem còn kiện đã nhận hàng thì cũng k hoàn
                        $packRevices = OrderPackage::query()->where('order_id', $orderDB->id)
                                                            ->where('order_type', 'App\Models\Order')
                                                            ->where('status', PackageConstant::STATUS_RECEIVED_GOODS)
                                                            ->whereNull('delivery_id')
                                                            ->count();
                        if ($packRevices > 0) {
                            continue;
                        }

                        // Nếu còn giao hàng cũng k hoàn
                        $deliveryRevices = OrderPackage::query()->where('order_id', $orderDB->id)
                                                            ->where('order_type', 'App\Models\Order')
                                                            ->whereNotNull('delivery_id')
                                                            ->where('delivery_id', '<>', $delivery->id)
                                                            ->count();
                        if ($deliveryRevices > 0) {
                            continue;
                        }

                        $amount = $orderDB->delivery_pay_debt_cost;
                        if ($amount <= 0) {
                            continue;
                        }

                        // Làm tròn lại tiền
                        $amount = roundMoney($amount);

                        $textLog = 'transaction.delivery_destroy';
                        if ($type == 'cancel') {
                            $textLog = 'transaction.delivery_cancel';
                        }

                        $content = __($textLog,
                            [
                                'amount' => ConvertHelper::numericToVND(floatval($amount)),
                                'bill'   => $orderDB->code,
                                'name'   => getCurrentUser()->name
                            ]
                        );

                        (new TransactionService())->setTransactionIncrement(
                            $amount,
                            TransactionConstant::STATUS_REFUND,
                            $content,
                            $delivery->customer_id,
                            $delivery,
                            $orderDB
                        );

                        // Set lại là chưa trả tiền
                        $orderDB->delivery_pay_id = 0;
                        $orderDB->delivery_pay_debt_cost = 0;
                        Order::withoutEvents(function () use ($orderDB) {
                            $orderDB->save();
                        });
                    }
                }
            }
        }

        // Hoàn lại phí ship
        $ordersPackage = $delivery->packages;
        foreach ($ordersPackage as $package) {
            $order        = $package->is_order ? $package->order : $package->consignment;
            $shippingCost = $package->shipping_cost ? $package->shipping_cost : 0;
            $amount       = ($shippingCost - $package->discount_cost);

            $textLog = 'transaction.delivery_destroy_2';
            if ($type == 'cancel') {
                $textLog = 'transaction.delivery_cancel_2';
            }

            $content = __($textLog,
                [
                    'amount' => ConvertHelper::numericToVND(floatval($amount)),
                    'bill'   => $package->bill_code,
                    'order_code'   => $package->order_code,
                    'name'   => getCurrentUser()->name
                ]
            );

            (new TransactionService())->setTransactionIncrement(
                $amount,
                TransactionConstant::STATUS_REFUND,
                $content,
                $delivery->customer_id,
                $delivery,
                $order
            );
        }
    }

    private function updateNotification($delivery, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content'  => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8011,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $delivery->customer_id,
            'status'          => 1
        ];

        $notificationService->store($data);
    }


    public function storeNote(string $deliveryId, string $content, array $param = [])
    {
        DeliveryNote::query()->create(
            [
                'delivery_id' => $deliveryId,
                'content'     => $content,
                'cause_id'    => getCurrentUserId(),
                'cause_type'  => Staff::class,
                'type'        => NoteConstant::TYPE_PRIVATE,
                'dongdong_id' => data_get($param, 'user_dongdong_id',null),
            ]
        );
    }
    public function storeDeliveryGHTK(string $deliveryId, array $response, string $shopId)
    {
        DeliveryOrderStatus::query()->create(
            [
                'delivery_id' => $deliveryId,
                'carrier' => CarrierConstant::GHTK,
                'desc' => CarrierConstant::GHTK_ORDER_STATUS[$response['order']['status_id']],
                'status' => $response['order']['status_id'] ?? null,
                'response' => json_encode($response),
                'shop_id' => $shopId,
            ]
        );
    }

    public function storeDeliveryGHN(string $deliveryId, array $response, string $shopId)
    {
        DeliveryOrderStatus::query()->create(
            [
                'delivery_id' => $deliveryId,
                'carrier' => CarrierConstant::GHN,
                'desc' => CarrierConstant::GHN_STATUS[CarrierConstant::GHN_ORDER_STATUS_CREATED] ?? null,
                'status' => CarrierConstant::GHN_ORDER_STATUS_CREATED,
                'response' => json_encode($response),
                'shop_id' => $shopId,
            ]
        );
    }

    public function deleteDeliveryOrderStatus($deliveryID)
    {
        $this->removeCarrierCode($deliveryID);
        return DeliveryOrderStatus::where('delivery_id', $deliveryID)->delete();
    }

    /**
     * @param  string  $id
     * @param  string  $carrierCode
     * @param  float  $deliveryCost
     */
    public function updateCarrierInfo(string $id, string $carrierCode, float $deliveryCost)
    {
        Delivery::query()->find($id)->update(['carrier_code' => $carrierCode, 'delivery_cost' => $deliveryCost, 'status' => DeliveryConstant::KEY_STATUS_PROCESS]);
    }

    /**
     * Cập nhật lại carrier code khi cancel order ở bên thứ 3
     * @param  string  $deliveryId
     */
    public function removeCarrierCode(string $deliveryId)
    {
        $delivery = Delivery::query()->withoutGlobalScopes()->find($deliveryId);
        if($delivery) {
            $delivery->carrier_code = null;
            $delivery->status = DeliveryConstant::KEY_STATUS_CANCEL;
            $delivery->save();
        }
    }

    // Thanh toán khi thay đổi delivery_cost
    public function deductionDeliveryCost($delivery, $amount)
    {
        $content = __(
            'transaction.delivery_cost',
            [
                'amount'      => ConvertHelper::numericToVND($amount),
                'carrierCode' => $delivery->carrier_code
            ]
        );
        // dd($content);
        (new TransactionService())->setTransactionDecrement(
            $amount,
            TransactionConstant::STATUS_PURCHASE,
            $content,
            $delivery->customer_id,
        );
        $this->updateNoti($delivery->customer_id, $content);
    }

    // Hoàn tiền khi thay đổi delivery_cost
    public function refundDeliveryCost($delivery, $amount)
    {
        $content = __(
            'transaction.refund_delivery_cost',
            [
                'amount'      => ConvertHelper::numericToVND($amount),
                'carrierCode' => $delivery->carrier_code
            ]
        );
        (new TransactionService())->setTransactionIncrement(
            $amount,
            TransactionConstant::STATUS_REFUND,
            $content,
            $delivery->customer_id,
        );
        $this->updateNoti($delivery->customer_id, $content);
    }

    public function checkEcode($packages, $customerDeliveryId)
    {
        $packages         = OrderPackage::query()->findMany($packages);
        $customerDelivery = CustomerDelivery::query()->findOrFail($customerDeliveryId);
        $delivery         = optional($customerDelivery)->delivery_cost ?? 0;
        $debt             = $this->getDebtCostByOrder($packages);
        $shipping         = $packages->sum('amount');
        $blanceCustomer   = (new CustomerService())->getBalanceAmount($customerDelivery->customer_id);
        $params           = [
            'delivery_cost'  => $delivery,
            'debt_cost'      => roundMoney($debt),
            'shipping_cost'  => $shipping,
            'blanceCustomer' => $blanceCustomer
        ];
        $totalPay = $params['debt_cost'] + $params['shipping_cost'];
        $remaining = $params['blanceCustomer'] - $totalPay;

        $err = $remaining < -20000 ? true : false;
        return $err; // Nếu số dư còn lại < 20K thì báo lỗi
    }


    public function checkFullEcode($deliveryDB)
    {
        $packages         = $deliveryDB->packages;
        $customerDelivery = CustomerDelivery::query()->findOrFail($deliveryDB->customer_delivery_id);
        $delivery         = optional($customerDelivery)->delivery_cost ?? 0;
        $debt             = $this->getDebtCostByOrder($packages);
        $shipping         = $packages->sum('amount');
        $blanceCustomer   = (new CustomerService())->getBalanceAmount($deliveryDB->customer_id);
        $customerOffer = CustomerOffer::query()->where('customer_id', $deliveryDB->customer_id)->first();

        $params           = [
            'delivery_cost'  => $delivery,
            'debt_cost'      => roundMoney($debt),
            'shipping_cost'  => $shipping,
            'blanceCustomer' => $blanceCustomer
        ];
        $totalPay = $params['debt_cost'] + $params['shipping_cost'];
        $remaining = $params['blanceCustomer'] - $totalPay;

        $isDebt = 0;
        if ($customerOffer) {
            $isDebt = $customerOffer->debt;
        }

        $moneyCheck = 0;
        if ($isDebt) {
            $moneyCheck = -100000;
        }

        $err = $remaining < $moneyCheck ? true : false;
        return [
            'err'        => $err,
            'moneyCheck' => $moneyCheck
        ];
    }

    /*public function export()
    {
        $params = request()->all();
        $perService = \App::make('App\Services\PerService');
        $query = $this->_model->newQuery()->select('deliveries.*');
        $query->with([
            'notesStaff',
            'deliveryOrderStatus',
            'transporter',
            'transporterDetail',
            'customerDelivery',
            'customer.warehouse',
            'customer.report',
            'order:id,code',
            'customer' => function ($q) {
                return $q->select(
                    'id',
                    'name',
                    'phone_number',
                    'warehouse_id',
                    'source'
                );
            },
            'orderPackages' => function ($q) {
                return $q->select(
                    'id',
                    'delivery_id',
                    'bill_code'
                );
            },
        ])
            ->when(data_get($params, 'is_received') != null, function ($q) use ($params) {
                $q->where(
                    'deliveries.status',
                    data_get($params, 'is_received') == 1 ? '=' : '!=',
                    DeliveryConstant::KEY_STATUS_DONE
                );
            });
        $query = $perService->getConditionByUserLogin($query);
        $phone_number = data_get($params, 'phone_number');
        if ($phone_number) {
            $customerDB = Customer::where('phone_number', $phone_number)->first();
            $query->where(function ($q) use ($phone_number, $customerDB) {
                $q->where('deliveries.phone_number', $phone_number);

                if ($customerDB) {
                    $q->orWhere('deliveries.customer_id', $customerDB->id);
                }
            });
        }
        $page = request('page', 1);
        $perPage = request('per_page', 25);

        $data = $query
            ->orderBy('created_at', 'desc')
            ->forPage($page, $perPage)
            ->get();
        return Excel::download(
            new DeliveryExport($data),
            'delivery_' . now()->format('Ymd_His') . '.xlsx'
        );
    }*/

    public function export(Request $request)
    {
        $params = $request->all();

        try {
            $time = now()->format('YmdHis');
            $fileName = getPathFileExport('delivery') . '/delivery_' . $time . '.xlsx';

            dispatch(
                new ExportDeliveryJob(
                    $params,
                    auth()->user(),
                    $fileName
                )
            )->onQueue('export-1800');

            return resSuccess();
        } catch (\Exception $e) {
            \Log::error($e);
            return resError();
        }
    }

}
