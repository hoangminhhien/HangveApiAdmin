<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

use App\Helpers\AccountingHelper;
use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

class SyncOrderV5 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $page;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($page)
    {
        $this->page = $page;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $page = $this->page;

        \Log::info('SyncOrder V10 Update - 20042024-7616: ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/orders-fulfillment';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $page,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        $pageCron = $page;

        $showLog = false;

        if ($result && count($result)) {
            $organizationModel = \App::make('App\Models\Organization');
            $orderModel = \App::make('App\Models\Order');
            $customerModel = \App::make('App\Models\Customer');
            $customerDeliveryModel = \App::make('App\Models\CustomerDelivery');
            $userModel = \App::make('App\Models\User');
            $warehouseModel = \App::make('App\Models\Warehouse');
            $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
            $orderPackageStatusTimeModel = \App::make('App\Models\OrderPackageStatusTime');
            $orderDetailPackageModel = \App::make('App\Models\OrderDetailPackage');
            $orderSupplierModel = \App::make('App\Models\OrderSupplier');
            $orderDetailModel = \App::make('App\Models\OrderDetail');
            $supplierModel = \App::make('App\Models\Supplier');
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $categoryModel = \App::make('App\Models\Category');
            $activitiModel = \App::make('App\Models\Activity');

            $deliveryModel = \App::make('App\Models\Delivery');
            $transporterModel = \App::make('App\Models\Transporter');
            $deliveryOrderModel = \App::make('App\Models\DeliveryOrder');

            $complainTypeModel = \App::make('App\Models\ComplainType');
            $solutionModel = \App::make('App\Models\Solution');
            $complainModel = \App::make('App\Models\Complain');
            $complainDetailModel = \App::make('App\Models\ComplainDetail');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            // Phần khiếu nại
            $complainTypeFullDB = $complainTypeModel->where('thuongdo_id', '>', 0)->get();
            $complainTypeFullDB = \Arr::pluck($complainTypeFullDB, 'id', 'thuongdo_id');

            $solutionFullDB = $solutionModel->where('thuongdo_id', '>', 0)->get();
            $solutionFullDB = \Arr::pluck($solutionFullDB, 'id', 'thuongdo_id');


            foreach ($result as $key => $item) {
                if ($showLog) {
                    \Log::info('ID:' . $key . ':code:' . $item['orders']['order_code']);
                }
                // dump($item);

                $order = $item['orders'];
                $orders_supplier = $item['orders_supplier'];

                // if (count($orders_supplier) > 1) {
                //     dd($item);
                // }

                if (count($orders_supplier) <= 1) {
                    // continue;
                } else {
                    // dump(count($orders_supplier));
                    // dump($item);
                }

                if ($order['order_code'] != '0728433042') {
                   // continue;
                } else {
                    // dump(1);
                    // dump($item);
                    // dd($item);
                }

                // if (count($item['delivery']) > 1) {
                //     dd($item);
                // }

                $truso = $order['truso_id'];

                if (!in_array($truso, [null,1,2,3,7,10,11])) {
                    \Log::info('truso_id:' . $order['truso_id']);
                }
                if ($order['order_status'] == 3) {
                    // dd($item);
                }

                // Time
                $date_purchased = $order['dat_coc_time'] ? date('Y-m-d H:i:s', $order['dat_coc_time']) : null;
                $date_ordered   = $order['dat_hang_time'] ? date('Y-m-d H:i:s', $order['dat_hang_time']) : null;
                $date_done      = $order['hoan_thanh_time'] ? date('Y-m-d H:i:s', $order['hoan_thanh_time']) : null;

                $pham_tram_dat_coc    = $order['pham_tram_dat_coc'] == null ? 0 : $order['pham_tram_dat_coc'];
                $phan_tram_chiet_khau = $order['phan_tram_chiet_khau'] == null ? 0 : $order['phan_tram_chiet_khau'];
                $phi_chiet_khau       = $order['phi_chiet_khau'] == null ? 0 : $order['phi_chiet_khau'];
                $phi_giaodich         = $order['phi_giaodich'] == null ? 0 : $order['phi_giaodich'];
                $phi_dich_vu          = $order['phi_dich_vu'] == null ? 0 : $order['phi_dich_vu'];
                $total_phi_kiem_hang  = $order['total_phi_kiem_hang'] == null ? 0 : $order['total_phi_kiem_hang'];

                $dong_go   = $order['dong_go'] == null ? 0 : $order['dong_go'];
                $kiem_diem = $order['kiem_diem'] == null ? 0 : $order['kiem_diem'];
                $chong_soc = $order['chong_soc'] == null ? 0 : $order['chong_soc'];

                $order_cost = $order['total_dat_hang'] == null ? 0 : $order['total_dat_hang'];
                $order_total_tam_ungTD = $order['tong_tam_ung'] == null ? 0 : $order['tong_tam_ung'];
                $order_total_don_hangTD = $order['total'] == null ? 0 : $order['total'];

                $data_order = [
                    'thuongdo_page'    => $pageCron,
                    'organization_id'  => $organizationTD->id,
                    'thuongdo_id'      => $order['id'],
                    'thuongdo_created' => $order['created'],

                    'code'             => $order['order_code'],
                    'code_po'          => null,
                    'code_parent'      => $order['order_code_parent'],
                    'order_cost'       => $order_cost,
                    'exchange_rate'    => $order['ti_gia'],

                    'inspection_cost'  => $total_phi_kiem_hang, // Chuẩn rồi

                    'is_woodworking'   => $dong_go,
                    'is_inspection'    => $kiem_diem,
                    'is_shock_proof'   => $chong_soc,

                    'is_deposit'       => $date_purchased == null ? 0 : 1, //  Đặt cọc
                    'deposit_percent'  => $pham_tram_dat_coc, //  % Đặt cọc
                    'deposit_cost'     => $pham_tram_dat_coc * $order_cost / 100, // Dựa theo công thức

                    'discount_percent' => $phan_tram_chiet_khau, // % Chiết Khấu
                    'discount_cost'    => $phi_chiet_khau, // Phí Chiết Khấu

                    'order_fee'        => $phi_giaodich, // Phí Đặt hàng
                    'order_percent'    => $phi_dich_vu, // % đặt hàng

                    'date_purchased'   => $date_purchased, // Thời gian thanh toán (đặt cọc)
                    'date_ordered'     => $date_ordered, // Thời gian đặt hàng
                    'date_done'        => $date_done, // Thời gian hoàn thành

                    'thuongdo_tong_tien_tam_ung' => $order_total_tam_ungTD,
                    'thuongdo_tong_tien_don_hang' => $order_total_don_hangTD,

                    'status'           => $this->getStatusOder($order['order_status']),
                ];

                $arr_website = [
                    null => null,
                    '1'  => 'taobao.com',
                    '2'  => 'tmall.com',
                    '3'  => '1688.com',
                ];

                $data_order['ecommerce'] = $arr_website[$order['website_id']];
                $data_order['is_website'] = $data_order['ecommerce'] == null ? 0 : 1;

                $type_delivery = [
                    null => "no",
                    '1'  => 'normal',
                    '2'  => 'fast',
                ];
                if (count($orders_supplier)) {
                    $data_order['delivery_type'] = $type_delivery[$orders_supplier[key($orders_supplier)]['loai_van_chuyen']];
                } else {
                    if ($showLog) {
                        \Log::info('Không có orders_supplier');
                    }
                }

                $customerDB = $customerModel->where('thuongdo_uuid', $order['uid'])->first();

                if (is_null($customerDB)) {
                    if ($showLog) {
                        \Log::info('Không có khách hàng, bỏ qua');
                        continue;
                    }
                }

                $orderDB = $orderModel->where('thuongdo_id', $data_order['thuongdo_id'])->first();

                // if ($orderDB) {
                //     if ($showLog) {
                //         \Log::info('Đơn này đã đồng bộ, bỏ qua');
                //         continue;
                //     }
                // }

                if (is_null($orderDB)) {
                    // Thêm customer_id
                    // $customerDB = $customerModel->where('thuongdo_uuid', $order['uid'])->first();
                    if (!is_null($customerDB)) {
                        $data_order['customer_id'] = $customerDB->id;
                    }

                    // Thêm phần kho
                    $warehouseDB = $warehouseModel->where('thuongdo_id', $order['truso_id'])->first();
                    if (!is_null($warehouseDB)) {
                        $data_order['warehouse_id'] = $warehouseDB->id;
                    }

                    // Thêm phần địa chỉ nhận hàng
                    $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $order['diachi_id'])->first();
                    if (!is_null($customerDeliveryDB)) {
                        $data_order['customer_delivery_id'] = $customerDeliveryDB->id;
                    }

                    // Thêm các nhân viên tư vân
                    // uid_xu_ly => Hoa bảo là nhân viên đặt hàng
                    // nhan_vien_cs_uid
                    // nhan_vien_kinh_doanh_uid
                    // nhan_vien_tu_van_uid
                    // uid_bao_gia

                    $staffCareDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_cs_uid'])->first();
                    if (!is_null($staffCareDB)) {
                        $data_order['staff_care_id'] = $staffCareDB->id;
                    }

                    $staffCounselorDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_tu_van_uid'])->first();
                    if (!is_null($staffCounselorDB)) {
                        $data_order['staff_counselor_id'] = $staffCounselorDB->id;
                    }

                    $staffQuotationDB = $userModel->where('thuongdo_uuid', $order['uid_bao_gia'])->first();
                    if (!is_null($staffQuotationDB)) {
                        $data_order['staff_quotation_id'] = $staffQuotationDB->id;
                    }

                    $staffOrderDB = $userModel->where('thuongdo_uuid', $order['uid_xu_ly'])->first();
                    if (!is_null($staffOrderDB)) {
                        $data_order['staff_order_id'] = $staffOrderDB->id;
                    }

                    // Phí vận chuyển nội địa china_shipping_cost
                    $china_shipping_cost_sum = 0;

                    foreach ($orders_supplier as $key_11 => $item_11) {
                        $china_shipping_cost_sum += ($item_11['phi_ship'] == null) ? 0 : $item_11['phi_ship'];
                    }

                    $data_order['china_shipping_cost'] = $china_shipping_cost_sum;

                    // Thời gian tạo
                    $data_order['created_at'] =  date('Y-m-d H:i:s', $order['created']);

                    if (isset($data_order['customer_id'])) {
                        $orderDB = $orderModel->create($data_order);
                    } else {
                        if ($showLog) {
                            \Log::info('Không tìm được khách hàng: ' . $order['uid'] . ' nên không tạo đơn');
                        }
                    }
                } else {
                    $orderDB->created_at = date('Y-m-d H:i:s', $order['created']);
                    $orderDB->thuongdo_tong_tien_tam_ung = $order_total_tam_ungTD;
                    $orderDB->thuongdo_tong_tien_don_hang = $order_total_don_hangTD;
                    $orderDB->order_fee = $phi_giaodich;
                    $orderDB->status = $this->getStatusOder($order['order_status']);
                    $orderDB->save();
                }

                // Tạo order_status_time
                if ($orderDB) {
                    $orderStatusTimeDB = $orderStatusTimeModel->where('order_id', $orderDB->id)->first();
                    if (is_null($orderStatusTimeDB)) {
                        $histories = $item['lich_su_don_hang'];

                        $data_order_status_time = [
                            'thuongdo_sync' => 1
                        ];
                        foreach ($histories as $key => $history) {
                            $status = $this->getStatusOder($history['status_id']);
                            $data_order_status_time[$status] = date('Y-m-d H:i:s', $history['created']);
                        }
                        $data_order_status_time['order_id'] = $orderDB->id;

                        $orderStatusTimeDB = $orderStatusTimeModel->create($data_order_status_time);
                    }
                }


                // Tạo order history
                if ($orderDB) {
                    $histories = $item['lich_su_don_hang'];
                    foreach ($histories as $key => $history) {
                        $activitiOrderStatusDB = $activitiModel
                                                    ->where('log_name', 'order_log')
                                                    ->where('subject_type', 'App\Models\Order')
                                                    ->where('subject_id', $orderDB->id)
                                                    ->where('thuongdo_id', $history['id'])
                                                    ->first();

                        if (is_null($activitiOrderStatusDB)) {
                            $status = $this->getStatusOder($history['status_id']);
                            $user_actionDB = $userModel->where('thuongdo_uuid', $history['uid_xu_ly'])->first();
                            if (!is_null($user_actionDB)) {
                                $status_x86TD = $this->getStatusOder($history['status_id']);
                                $data_x86TD = new ReportStatusResource($status_x86TD, OrderConstant::STATUSES, OrderConstant::STATUSES_COLOR);

                                $data_user_activity = [
                                    'log_name'        => 'order_log',
                                    'subject_type'    => 'App\Models\Order',
                                    'subject_id'      => $orderDB->id,
                                    'causer_type'     => 'App\Models\User',
                                    'causer_id'       => $user_actionDB->id,
                                    'object_id'       => $orderDB->id,
                                    'content'         => trans("activity.order_$status_x86TD"),
                                    'properties'      => json_encode($data_x86TD),
                                    'organization_id' => $organizationTD->id,
                                    'thuongdo_id'     => $history['id'],
                                    'created_at'      => date('Y-m-d H:i:s', $history['created'])
                                ];

                                $activitiOrderStatusDB = $activitiModel->create($data_user_activity);
                            }
                        }
                    }
                }

                $orderSupplierFirst = null;
                // Tạo order Supplier
                if ($orderDB) {
                    $orders_suppliers = $item['orders_supplier'];
                    $orderSupplierFirst = null;

                    // Chỗ tồng tiền thanh toán thực tế thì lưu thêm vào orderSup vì hệ thống cũ không tách

                    if (count($orders_suppliers)) {

                        $listOrderDetailTemp = []; // Dữ liệu tạm chi tiết đơn
                        $listOrderSupplierTemp = []; // Dữ liệu tạm nhà cung cấp

                        foreach ($orders_suppliers as $key => $orders_supplier) {
                            $supplierDB = $supplierModel->where('thuongdo_id', $orders_supplier['shop_id'])->first();

                            if (is_null($supplierDB)) {
                                if ($showLog) {
                                    \Log::info('Không tìm thấy ShopID: ' . $orders_supplier['shop_id'] . ' nên không tạo Order Supplier');
                                }
                            }

                            if ($supplierDB) {
                                // Lấy ra Supplier theo khách hàng
                                $supplierOfCusDB = $supplierModel->where('thuongdo_id', $orders_supplier['shop_id'])
                                                                ->where('customer_id', $orderDB->customer_id)
                                                                ->first();
                                if (is_null($supplierOfCusDB)) {
                                    $data_supplier = [
                                        'name'                => $supplierDB->name,
                                        'code'                => 'CC_' . \Str::random(12),
                                        'address'             => $supplierDB->address,
                                        'customer_id'         => $orderDB->customer_id,
                                        'organization_id'     => $organizationTD->id,
                                        'website'             => $supplierDB->website,
                                        'logo'                => $supplierDB->logo,
                                        'type'                => $supplierDB->type,
                                        'industry'            => $supplierDB->industry,
                                        'thuongdo_id'         => $supplierDB->thuongdo_id,
                                        'thuongdo_shop_id'    => $supplierDB->thuongdo_shop_id,
                                        'thuongdo_name_en'    => $supplierDB->thuongdo_name_en,
                                        'thuongdo_address_en' => $supplierDB->thuongdo_address_en,
                                    ];
                                    $supplierOfCusDB = $supplierModel->create($data_supplier);
                                }

                                // Tạo Order Supplier
                                $orderSupplierDB = $orderSupplierModel->where('thuongdo_id', $orders_supplier['sid'])->first();

                                // Dữ liệu cần thiết
                                $total_dat_hang_xTD = $orders_supplier['tien_hang_vn'] == null ? 0 : $orders_supplier['tien_hang_vn']; // Tổng tiền hàng

                                $total_phi_van_chuyen_quoc_te_xTD = $orders_supplier['phi_van_chuyen_qt'] == null ? 0 : $orders_supplier['phi_van_chuyen_qt']; // Tổng tiền vận chuyển quốc tế

                                $total_tong_tien_kiem_hang_xTD = $orders_supplier['phi_kiem_dem'] == null ? 0 : $orders_supplier['phi_kiem_dem']; // Tổng tiền kiểm định
                                $total_tong_tien_thanh_toan_xTD = $orders_supplier['tong_tien_thanh_toan'] == null ? 0 : $orders_supplier['tong_tien_thanh_toan']; // Tổng tiền thanh toans

                                if (is_null($orderSupplierDB)) {
                                    $dong_go   = $orders_supplier['dong_go'] == null ? 0 : $orders_supplier['dong_go'];
                                    $kiem_hang = $orders_supplier['kiem_hang'] == null ? 0 : $orders_supplier['kiem_hang'];
                                    $chong_soc = $orders_supplier['chong_soc'] == null ? 0 : $orders_supplier['chong_soc'];

                                    // $orderFeeTmpX1 = $this->getOrderFeeForOrderSup($total_dat_hang_xTD, $orderDB->order_percent);
                                    // $orderDiscountTmpX1 = $this->getOrderDiscountForOrderSup($orderFeeTmpX1, $orderDB->discount_percent);

                                    $orderFeeTmpX1 = $orders_supplier['phi_dat_hang'] == null ? 0 : $orders_supplier['phi_dat_hang'];
                                    $orderDiscountTmpX1 = 0; // Mặc định cho = 0
                                    $orderDiscountTmpX1 = $this->getOrderDiscountForOrderSup($orderFeeTmpX1, $orderDB->discount_percent); // Logic mới

                                    $data_order_supplier = [
                                        'order_id'                    => $orderDB->id,
                                        'supplier_id'                 => $supplierOfCusDB->id,
                                        'order_cost'                  => $total_dat_hang_xTD,
                                        'order_fee'                   => $orderFeeTmpX1,
                                        'discount_cost'               => $orderDiscountTmpX1,
                                        'inspection_cost'             => $total_tong_tien_kiem_hang_xTD,
                                        'international_shipping_cost' => $total_phi_van_chuyen_quoc_te_xTD,

                                        'is_woodworking'              => $dong_go,
                                        'is_inspection'               => $kiem_hang,
                                        'is_shock_proof'              => $chong_soc,

                                        'china_shipping_cost'         => $orders_supplier['phi_ship'] == null ? 0 : $orders_supplier['phi_ship'] ,
                                        'thuongdo_id'                 => $orders_supplier['sid'],
                                        'thuongdo_sync'               => $pageCron,
                                        'thuongdo_tong_tien_thanh_toan'   => $total_tong_tien_thanh_toan_xTD,
                                    ];

                                    $orderSupplierDB = $orderSupplierModel->create($data_order_supplier);
                                } else {
                                    if ($showLog) {
                                        \Log::info('Cập nhật giá cho: ' . $pageCron . ' orderSup: ' . $orders_supplier['sid']);
                                    }
                                    $orderFeeTmpX1 = $orders_supplier['phi_dat_hang'] == null ? 0 : $orders_supplier['phi_dat_hang'];
                                    $orderDiscountTmpX1 = $this->getOrderDiscountForOrderSup($orderFeeTmpX1, $orderDB->discount_percent); // Logic mới

                                    // Cập nhật lại giá cho nhà cung cấp
                                    $orderSupplierDB->order_cost                  = $total_dat_hang_xTD;
                                    $orderSupplierDB->order_fee                   = $orderFeeTmpX1;
                                    $orderSupplierDB->discount_cost               = $orderDiscountTmpX1;
                                    $orderSupplierDB->inspection_cost             = $total_tong_tien_kiem_hang_xTD;
                                    $orderSupplierDB->international_shipping_cost = $total_phi_van_chuyen_quoc_te_xTD;
                                    $orderSupplierDB->thuongdo_sync               = $pageCron;
                                    $orderSupplierDB->thuongdo_tong_tien_thanh_toan  = $total_tong_tien_thanh_toan_xTD;
                                    $orderSupplierDB->save();
                                }

                                if ($orderSupplierFirst == null) {
                                    $orderSupplierFirst = $orderSupplierDB;
                                }

                                // Đẩy mảng tạm nhà cung cấp
                                // array_push($listOrderSupplierTemp, $orderSupplierDB);

                                // Tạo Order Product
                                // Nếu mà trùng shop_id thì là đính sản phẩm theo nhà cũng cấp đó của khách
                                $sanphams = $item['san_pham'];
                                if (count($sanphams)) {
                                    foreach ($sanphams as $key_sanpham => $sanpham) {
                                        if ($sanpham['shop_id'] == $orders_supplier['shop_id']) {
                                           $orderDetailDB = $orderDetailModel->where('thuongdo_id', $sanpham['pid'])->first();

                                           if (is_null($orderDetailDB)) {
                                                $quantity = $sanpham['qty'] == null ? 0 : $sanpham['qty'];
                                                $priceS = $sanpham['price'] == null ? 0 : $sanpham['price'];
                                                $nameS = $sanpham['name'] == null ? 'no-name' : $sanpham['name'];

                                                // Page 3104 nó lại trả giá ra 168.00168.00;
                                                if ($priceS == '168.00168.00') {
                                                    $priceS = "168";
                                                }
                                                // Page 3104 nó lại trả giá ra 1.09 ;
                                                $priceS = str_replace(" ", "", $priceS);

                                                // Page 4331 nó lại trả giá ra 1700-2125;
                                                if ($priceS == '1700-2125') {
                                                    $priceS = "1700";
                                                }

                                                // Page 4358 nó lại trả giá ra 88-145;
                                                if ($priceS == '88-145') {
                                                    $priceS = "88";
                                                }

                                                // Page 4367 nó lại trả giá ra 4.8-58.8;
                                                if ($priceS == '4.8-58.8') {
                                                    $priceS = "58";
                                                }

                                                // Page 4461 nó lại trả giá ra 133-600;
                                                if ($priceS == '133-600') {
                                                    $priceS = "133.6";
                                                }

                                                // Page 4493 nó lại trả giá ra 599-609 ;
                                                $priceS = str_replace("-", ".", $priceS);

                                                // Page 4597 nó lại trả giá ra 13.0013.00;
                                                if ($priceS == '13.0013.00') {
                                                    $priceS = "13";
                                                }

                                                // Page 4649 nó lại trả giá ra 5.005.00;
                                                if ($priceS == '5.005.00') {
                                                    $priceS = "5";
                                                }

                                                // Page 4696 nó lại trả giá ra 17.5.44;
                                                if ($priceS == '17.5.44') {
                                                    $priceS = "17";
                                                }

                                                // Page 5057 nó lại trả giá ra 62.82.98.82;
                                                if ($priceS == '62.82.98.82') {
                                                    $priceS = "62";
                                                }

                                                // Page 5062 nó lại trả giá ra 389.00549.00;
                                                if ($priceS == '389.00549.00') {
                                                    $priceS = "389";
                                                }

                                                // Page 5129 nó lại trả giá ra 280.00280.00;
                                                if ($priceS == '280.00280.00') {
                                                    $priceS = "280";
                                                }

                                                // Page 5215 nó lại trả giá ra 280.00280.00;
                                                if ($priceS == '51.8851.88') {
                                                    $priceS = "51";
                                                }

                                                // Page 5238 nó lại trả giá ra 54.8854.88;
                                                if ($priceS == '54.8854.88') {
                                                    $priceS = "54";
                                                }

                                                // Page 5282 nó lại trả giá ra 54.8854.88;
                                                if ($priceS == '5.505.50') {
                                                    $priceS = "5";
                                                }
                                                if ($priceS == '6.006.00') {
                                                    $priceS = "6";
                                                }

                                                // Page 5377 nó lại trả giá ra 55.8855.888;
                                                if ($priceS == '55.8855.88') {
                                                    $priceS = "55";
                                                }
                                                if ($priceS == '89.8889.88') {
                                                    $priceS = "89";
                                                }

                                                // $priceS = "600000";
                                                // // Xử lý triệt luôn 5411
                                                $arrPriceS = explode(".",$priceS);

                                                if (count($arrPriceS) > 2) {
                                                    $priceS = $arrPriceS[0];
                                                }

                                                // Page 8774 nó lại trả giá ra <fontstyle="vertical;
                                                if ($priceS == '<fontstyle="vertical') {
                                                    $priceS = "0";
                                                }

                                                // Page 7396 nó lại trả giá ra 7giờ80
                                                if ($priceS == '7giờ80') {
                                                    $priceS = "0";
                                                }
                                                // Page 7616 nó lại trả giá ra $1.80
                                                if ($priceS == '$1.80') {
                                                    $priceS = "0";
                                                }

                                                $data_order_detail = [
                                                    'organization_id'       => $organizationTD->id,
                                                    'order_id'              => $orderDB->id,
                                                    'name'                  => $nameS,
                                                    'link'                  => $sanpham['link'],
                                                    'image'                 => $sanpham['image'],
                                                    'note'                  => $sanpham['ghi_chu_cho_khach_hang'],
                                                    'classification'        => $sanpham['ghi_chu'],
                                                    'unit_price_cny'        => $priceS,
                                                    'quantity'              => $quantity,
                                                    'packages_number'       => 1,
                                                    'amount_cny'            => $priceS * $quantity,
                                                    'supplier_id'           => $supplierOfCusDB->id,
                                                    'thuongdo_id'           => $sanpham['pid'],
                                                    'thuongdo_kien_hang_id' => $sanpham['kien_hang_id'],
                                                ];
                                               $orderDetailDB = $orderDetailModel->create($data_order_detail);
                                           }

                                           // Đẩy mảng tạm chi tiết đơn
                                           // array_push($listOrderDetailTemp, $orderDetailDB);
                                        }
                                    }
                                }
                            }
                        }

                        // Xử lý tính toán lại order_cost, order_fee, discount_cost, inspection_cost
                        // foreach ($listOrderSupplierTemp as $key_x272 => $item_x272) {
                        //     $ab18x_order_cost      = 0;
                        //     $ab18x_order_fee       = 0;
                        //     $ab18x_discount_cost   = 0;
                        //     $ab18x_inspection_cost = 0;

                        //     foreach ($listOrderDetailTemp as $key_a161 => $item_a161) {
                        //         $ab18x_order_cost += $item_a161->unit_price_cny * $item_a161->quantity;
                        //         $ab18x_order_fee += $orderDB->order_percent * ($item_a161->unit_price_cny * $item_a161->quantity);
                        //         $ab18x_discount_cost += $orderDB->discount_percent * $orderDB->order_percent * ($item_a161->unit_price_cny * $item_a161->quantity);
                        //     }
                        // }
                    }
                }


                // Tạo kiện
                $sum_international_shipping_cost = 0;
                if ($orderDB) {
                    $packs = $item['kien_hang'];
                    foreach ($packs as $key_pack => $pack) {
                        // continue;
                        $orderPackageDB = $orderPackageModel->where('thuongdo_id', $pack['id'])->first();

                        if (is_null($orderPackageDB)) {
                            $data_order_package = [
                                'order_id'   => $orderDB->id,
                                'order_code' => $orderDB->code,
                                'code'       => null,
                                'code_po'    => $pack['ma_dat_hang'],
                                'bill_code'  => $pack['ma_van_don']
                            ];

                            $data_order_package['delivery_id'] = null; // Chưa có

                            $arr_type_order_pack = [
                                null => null,
                                '1'  => 'App\Models\Order',
                                '2'  => 'App\Models\Consignment',
                                '3'  => null
                            ];
                            $data_order_package['order_type'] = $arr_type_order_pack[$pack['type']];

                            // Phần kho xem bị trung với cái truso_id ko
                            // Lấy kho VN
                            $warehouseVNDB = $warehouseModel->where('thuongdo_id', $pack['truso_id'])->first();
                            if (!is_null($warehouseVNDB)) {
                                $data_order_package['warehouse_id'] = $warehouseVNDB->id; // CHeck xem nó có không
                            }

                            // Lấy kho TQ
                            $warehouseCNDB = $warehouseModel->where('thuongdo_id', $pack['kho_tq_id'])->first();
                            if (!is_null($warehouseCNDB)) {
                                $data_order_package['warehouse_cn'] = $warehouseCNDB->id; // CHeck xem nó có không
                            }

                            $data_order_package['product_name'] = $pack['ten_hang_hoa'];

                            $so_kienTD = $pack['so_kien'] == null ? 0 : $pack['so_kien'];
                            $data_order_package['package_number'] = $so_kienTD;

                            $data_order_package['order_cost'] = $pack['gia_tri_bao_hiem'] == null ? 0 : $pack['gia_tri_bao_hiem'];

                            $kiem_demTD =  $pack['kiem_dem'] == null ? 0 : $pack['kiem_dem'];
                            $phi_kiem_demTD =  $pack['phi_kiem_dem'] == null ? 0 : $pack['phi_kiem_dem'];
                            $data_order_package['inspection_cost'] = $phi_kiem_demTD; // Phí kiểm đếm
                            $data_order_package['is_inspection'] = $kiem_demTD; // Có kiểm đếm hay không


                            $bao_hiemTD =  $pack['bao_hiem'] == null ? 0 : $pack['bao_hiem'];
                            $phi_bao_hiemTD =  $pack['phi_bao_hiem'] == null ? 0 : $pack['phi_bao_hiem'];
                            $data_order_package['insurance_cost'] = $phi_bao_hiemTD; // Phí bảo hiểm
                            $data_order_package['is_insurance'] = $bao_hiemTD; // Có bảo hiểm hay không


                            $dong_goTD =  $pack['dong_go'] == null ? 0 : $pack['dong_go'];
                            $phi_dong_goTD =  $pack['phi_dong_go'] == null ? 0 : $pack['phi_dong_go'];
                            $data_order_package['woodworking_cost'] = $phi_dong_goTD; // Phí đóng gỗ
                            $data_order_package['is_woodworking'] = $dong_goTD; // Có đóng gỗ hay không


                            $chieu_caoTD  =  $pack['chieu_cao'] == null ? 0 : $pack['chieu_cao'];
                            $chieu_daiTD  =  $pack['chieu_dai'] == null ? 0 : $pack['chieu_dai'];
                            $chieu_rongTD =  $pack['chieu_rong'] == null ? 0 : $pack['chieu_rong'];
                            $data_order_package['height'] = $chieu_caoTD;
                            $data_order_package['length'] = $chieu_daiTD;
                            $data_order_package['width'] = $chieu_rongTD;

                            if ($pack['tinh_theo'] == 1) {
                                $data_order_package['weight'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
                                $data_order_package['weight_real'] = $pack['total_kg_round'];
                            }

                            // tinh_theo:
                            // 1 - Trọng lượng
                            // 2 - Thể tích

                            $data_order_package['type_quantitative'] = 'weight'; // Tính theo cái nào
                            if ($pack['tinh_theo'] == 2) {
                                $data_order_package['type_quantitative'] = 'volumn';
                                $data_order_package['thuongdo_volumn'] = $pack['total_kg'] == null ? 0 : $pack['total_kg'];
                            }

                            $is_deliveryTD = $pack['send_yc_ship'] == null ? 0 : $pack['send_yc_ship'];
                            $data_order_package['is_delivery'] = $is_deliveryTD;
                            $data_order_package['delivery_cost'] = 0;
                            $data_order_package['delivery_type'] = 'normal';
                            $data_order_package['in_transit'] = $pack['hang_van_chuyen'];
                            $data_order_package['transporter_id'] = null;

                            $exchange_rateTD = $pack['ti_gia'] == null ? 0 : $pack['ti_gia'];
                            $exchange_rateTD = $orderDB->exchange_rate;
                            $data_order_package['exchange_rate'] = $exchange_rateTD;

                            $data_order_package['international_shipping_cost'] = $pack['phi_can_nang'] == null ? 0 : $pack['phi_can_nang'];
                            $data_order_package['china_shipping_cost'] = $pack['phi_china'];

                            $chong_socTD = $pack['chong_soc'] == null ? 0 : $pack['chong_soc'];
                            $phi_chong_socTD = $pack['phi_chong_soc'] == null ? 0 : $pack['phi_chong_soc'];
                            $data_order_package['is_shock_proof'] = $chong_socTD;
                            $data_order_package['shock_proof_cost'] = $phi_chong_socTD;

                            $data_order_package['storage_cost'] = 0; // Phí lưu kho, chưa rõ

                            $phi_chiet_khauTD =  $pack['phi_chiet_khau'] == null ? 0 : $pack['phi_chiet_khau'];
                            $data_order_package['discount_cost'] = $phi_chiet_khauTD; // Giá chiết khẩu (giảm cái phí vận chuyển QT)
                            $phan_tram_chiet_khauTD =  $pack['phan_tram_chiet_khau'] == null ? 0 : $pack['phan_tram_chiet_khau'];
                            $data_order_package['discount_percent'] = $phan_tram_chiet_khauTD; // % chiết khẩu

                            $data_order_package['amount'] = 0; // đang chưa rõ, Tổng phí vận chuyển


                            $data_order_package['note'] = $pack['ghichu'];
                            $data_order_package['note_ordered'] = $pack['ghichu_kh'];
                            $data_order_package['description'] = null; // Check lại coi


                            if (!is_null($customerDB)) {
                                $data_order_package['customer_id'] = $customerDB->id;
                            } else {
                                \Log::info('Không tim khách');
                            }

                            $data_order_package['customer_delivery_id'] = $orderDB->customer_delivery_id; // Xem lúc lưu tại app/Http/Controllers/PackageController.php


                            // Danh mục
                            $categoryDB = $categoryModel->where('thuongdo_id', $pack['cate_id'])->first();
                            if (!is_null($categoryDB)) {
                                $data_order_package['category_id'] = $categoryDB->id;
                                $data_order_package['category'] = $categoryDB->name;
                            }

                            // Nhân viên
                            // nhan_vien_cs_uid
                            // nhan_vien_tu_van_uid
                            // nhan_vien_kinh_doanh_uid

                            $staffCareDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_cs_uid'])->first();
                            if (!is_null($staffCareDB)) {
                                $data_order_package['staff_care_id'] = $staffCareDB->id;
                            }

                            $staffCounselorDB = $userModel->where('thuongdo_uuid', $order['nhan_vien_tu_van_uid'])->first();
                            if (!is_null($staffCounselorDB)) {
                                $data_order_package['staff_counselor_id'] = $staffCounselorDB->id;
                            }

                            // Chưa tìm dc đối tượng này
                            $staffOrderDB = $userModel->where('thuongdo_uuid', $order['uid_xu_ly'])->first();
                            if (!is_null($staffOrderDB)) {
                                $data_order_package['staff_order_id'] = $staffOrderDB->id;
                            }

                            // Status

                            $data_order_package['status'] = $this->getStatusPack($pack['status']);

                            // ID
                            $data_order_package['thuongdo_id'] = $pack['id'];

                            // Thời gian tạo
                            $data_order_package['created_at'] =  date('Y-m-d H:i:s', $pack['created']);

                            // Tổng phí vận chuyển quốc tế lưu vào phần đơn hàng + order_supplier
                            $sum_international_shipping_cost += $data_order_package['international_shipping_cost'];

                            $orderPackageDB = $orderPackageModel->create($data_order_package);

                            // Tạo lịch sử kiện
                            $history_listTD = $pack['history_list'];

                            if (count($history_listTD)) {
                                foreach ($history_listTD as $key => $history) {
                                    $orderPackageStatusTimeDB = $orderPackageStatusTimeModel->where('thuongdo_id', $history['id'])->first();
                                    if (is_null($orderPackageStatusTimeDB)) {
                                        $data_order_pack_status_time = [
                                            'order_package_id' => $orderPackageDB->id,
                                            'key'              => $this->getStatusPack($history['status']),
                                            'thuongdo_id'      => $history['id'],
                                            'thuongdo_uid'     => $history['uid'],
                                            'created_at'       => date('Y-m-d H:i:s', $history['created'])
                                        ];
                                        $orderPackageStatusTimeDB = $orderPackageStatusTimeModel->create($data_order_pack_status_time);
                                    }

                                    $activitiPackStatusDB = $activitiModel
                                                                ->where('log_name', 'order_package_status')
                                                                ->where('subject_type', 'App\Models\OrderPackage')
                                                                ->where('subject_id', $orderPackageDB->id)
                                                                ->where('thuongdo_id', $history['id'])
                                                                ->first();

                                    if (is_null($activitiPackStatusDB)) {
                                        $user_actionDB = $userModel->where('thuongdo_uuid', $history['uid'])->first();
                                        if ($user_actionDB) {
                                            $data_user_activity = [
                                                'log_name'        => 'order_package_status',
                                                'subject_type'    => 'App\Models\OrderPackage',
                                                'subject_id'      => $orderPackageDB->id,
                                                'causer_type'     => 'App\Models\User',
                                                'causer_id'       => $user_actionDB->id,
                                                'content'         => $this->getStatusPack($history['status']),
                                                'organization_id' => $organizationTD->id,
                                                'thuongdo_id'     => $history['id'],
                                                'created_at'       => date('Y-m-d H:i:s', $history['created'])
                                            ];
                                            $activitiPackStatusDB = $activitiModel->create($data_user_activity);
                                        }
                                    }
                                }

                                // Xử lý với cái report ban đầu
                                // $orderPackageStatusTimeStart = $orderPackageStatusTimeModel
                                //                             ->where('order_package_id', $orderPackageDB->id)
                                //                             ->where('key', 'status_0')
                                //                             ->where('thuongdo_id', null)
                                //                             ->first();
                                // if ($orderPackageStatusTimeStart) {
                                //     $orderPackageStatusTimeStart->created_at = date('Y-m-d H:i:s', $pack['created']);
                                //     $orderPackageStatusTimeStart->thuongdo_id = '999999999';
                                //     $orderPackageStatusTimeStart->save();
                                // }
                                // // Xử lý cái log đầu tiền
                                // $activitiPackStatusStart = $activitiModel
                                //                                 ->where('log_name', 'order_package_status')
                                //                                 ->where('subject_type', 'App\Models\OrderPackage')
                                //                                 ->where('subject_id', $orderPackageDB->id)
                                //                                 ->where('content', 'status_0')
                                //                                 ->where('thuongdo_id', null)
                                //                                 ->first();
                                // if ($activitiPackStatusStart) {
                                //     $activitiPackStatusStart->created_at = date('Y-m-d H:i:s', $pack['created']);
                                //     $activitiPackStatusStart->thuongdo_id = '999999999';
                                //     $activitiPackStatusStart->save();
                                // }
                            }

                            // Tạo sản phẩm của kiện
                            $product_listTD = $pack['product_list'];
                            if (count($product_listTD)) {
                                foreach ($product_listTD as $key_product => $product) {
                                    $orderDetail1809 = $orderDetailModel->where('thuongdo_id', $product['pid'])->first();
                                    if ($orderDetail1809) {
                                        $data_order_detail_pack = [
                                            'order_detail_id'  => $orderDetail1809->id,
                                            'order_package_id' => $orderPackageDB->id,
                                            'quantity'         => $product['qty'] == null ? 0 : $product['qty'],
                                            'thuongdo_sync'    => 1
                                        ];
                                        $data_order_detail_packDB = $orderDetailPackageModel->create($data_order_detail_pack);
                                    }
                                }
                            }
                        } else {
                            $orderPackageDB->exchange_rate = $orderDB->exchange_rate;
                            $orderPackageDB->save();
                        }
                    }
                }

                // Lưu thêm phí vận chuyển quốc tế vào đơn
                if ($orderDB) {
                    if ($showLog) {
                        \Log::info('Lưu thêm phí vận chuyển quốc tế vào đơn');
                        \Log::info($sum_international_shipping_cost);
                    }
                    $orderDB->international_shipping_cost = $sum_international_shipping_cost;
                    $orderDB->save();
                }
                // // Lưu thêm phí vận chuyển quốc tế vào orderSupplierFirst
                // if ($orderSupplierFirst) {
                //     if ($showLog) {
                //         \Log::info('orderSupplierFirst');
                //     }
                //     $orderSupplierFirst->international_shipping_cost = $sum_international_shipping_cost;
                //     $orderSupplierFirst->save();
                // }

                // // Lưu order_fee, discount_cost, inspection_cost từ order vào thằng đầu tiên
                // if ($orderDB && $orderSupplierFirst) {
                //     if ($showLog) {
                //         \Log::info('Lưu 4 key mới');
                //     }
                //     $orderSupplierFirst->order_cost = $orderDB->order_cost;
                //     $orderSupplierFirst->order_fee = $orderDB->order_fee;
                //     $orderSupplierFirst->discount_cost = $orderDB->discount_cost;
                //     $orderSupplierFirst->inspection_cost = $orderDB->inspection_cost;
                //     $orderSupplierFirst->save();
                // }

                // Tạo giao hàng
                if ($orderDB) {
                    if (count($item['delivery']) > 0) {
                        $deliveryTD = $item['delivery'];

                        if ($showLog) {
                            \Log::info('Lưu phần delivery đê');
                        }

                        foreach ($deliveryTD as $key_delivery => $delivery) {
                            $deliveryDB = $deliveryModel->where('thuongdo_id', $delivery['id'])->first();
                            if (is_null($deliveryDB)) {
                                // ID
                                $data_delivery['thuongdo_id']     = $delivery['id'];
                                $data_delivery['code']            = $delivery['id'];
                                $data_delivery['organization_id'] = $organizationTD->id;
                                if ($showLog) {
                                    \Log::info('created-delivey: ' . $delivery['created']);
                                }
                                // Thời gian tạo
                                $data_delivery['created_at'] =  date('Y-m-d H:i:s', $delivery['created']);
                                // Khách hàng

                                $data_delivery['customer_id'] = $customerDB->id;

                                // Địa chỉ giao hàng shipping_address_id
                                $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $delivery['shipping_address_id'])->first();
                                if (is_null($customerDeliveryDB)) {
                                    if ($showLog) {
                                        \Log::info('Không địa chỉ giao hàng của khách không lưu giao hàng luôn: ' .  $delivery['id']);
                                    }
                                    continue; // Chạy vòng lặp mới
                                }
                                $data_delivery['customer_delivery_id'] = $customerDeliveryDB->id;
                                $data_delivery['receiver']             = $customerDeliveryDB->receiver;
                                $data_delivery['address']              = $customerDeliveryDB->address;
                                $data_delivery['phone_number']         = $customerDeliveryDB->phone_number;

                                // Hình thức vận chuyển
                                $transporterDB = $transporterModel->where('thuongdo_id', $delivery['shipping_type'])->first();
                                if (!is_null($transporterDB)) {
                                    $data_delivery['transporter_id'] = $transporterDB->id;
                                }

                                // Thời gian giao

                                // Trang 44 nó trả ra time 18-01-2021 17:30 đến 18h00
                                if (strlen($delivery['time_delivery']) > 11) {
                                    $delivery['time_delivery'] = substr($delivery['time_delivery'], 0, 10);
                                }
                                // (Trang 31 dính lỗi này)
                                $data_delivery['date'] = str_replace("/", "-", $delivery['time_delivery']);

                                // Kiểm tra đúng định dạng thời gian hay không
                                // Trang 94 nó trả ra time 04 - 05-02
                                $checkTime1 = \DateTime::createFromFormat('Y-m-d', $data_delivery['date']);
                                $checkTime2 = \DateTime::createFromFormat('d-m-Y', $data_delivery['date']);
                                if ($checkTime1 == false && $checkTime2 == false) {
                                    $data_delivery['date'] = null;
                                }

                                // Trang 1303 nó trả ra time 17-18-7
                                if ($data_delivery['date'] && strlen($data_delivery['date']) < 10) {
                                    $data_delivery['date'] = null;
                                }

                                // Trang 2141 nó trả ra time 06-13-2021
                                // Trang 2149 nó trả ra time null

                                if (!is_null($data_delivery['date'])) {
                                    $arr_date_829 = explode("-", $data_delivery['date']);
                                    if ($arr_date_829[1] > 12) {
                                        $data_delivery['date'] = $arr_date_829[0] . "-12-" .  $arr_date_829[2];
                                    }
                                }

                                // Loại giao nhanh hay chậm type
                                $data_delivery['type'] = 'normal';
                                $data_delivery['payment'] = $this->getDeliveryPayment($delivery['shipping_code']);

                                // Phí
                                $data_delivery['international_shipping_cost'] = $delivery['phi_vc_quoc_te'];
                                $data_delivery['china_shipping_cost'] = $delivery['phi_ship_tq'];

                                $staffOrderDB = $userModel->where('thuongdo_uuid', $delivery['uid_xu_ly'])->first();
                                if (!is_null($staffOrderDB)) {
                                    $data_delivery['staff_order_id'] = $staffOrderDB->id;
                                }

                                // Status
                                // "1" => "Chờ xử lý",
                                // "4" => "Đang xử lý",
                                // "2" => "Đã hoàn thành"
                                // "3" => "Hủy"
                                $arr_status = [
                                    "1" => "status_0",
                                    "2" => "status_2",
                                    "3" => "status_3",
                                    "4" => "status_1",
                                ];
                                $data_delivery['status'] = array_key_exists($delivery['status'], $arr_status) ? $arr_status[$delivery['status']] : 'Không xác định';


                                // Status_payment
                                // 1. Chưa thanh toán hết
                                // 2. Đã thanh toán hết
                                $arr_status_payment = [
                                    '1' => 0,
                                    '2' => 1,
                                ];

                                $data_delivery['is_delivery_cost_paid'] = array_key_exists($delivery['status_thanhtoan'], $arr_status_payment) ? $arr_status_payment[$delivery['status_thanhtoan']] : null;

                                // Mã bưu kiện
                                $data_delivery['postcode'] = $delivery['post_code'];
                                $data_delivery['carrier_code'] = $delivery['post_code'];

                                if ($showLog) {
                                    \Log::info('post_code: ' . $delivery['post_code']);
                                    \Log::info('thanh_toan: ' . $delivery['thanh_toan']);
                                }

                                // Thanh toán
                                $thanh_toanTD = $delivery['thanh_toan'] == null ? 0 : $delivery['thanh_toan'];
                                $data_delivery['thuongdo_total_money'] = $thanh_toanTD;
                                $data_delivery['delivery_cost'] = $thanh_toanTD;

                                // Ghi chú
                                $data_delivery['note'] = $delivery['ghi_chu'];

                                // Tạo giao hàng
                                $deliveryDB = $deliveryModel->create($data_delivery);
                                if ($showLog) {
                                    \Log::info('Tạo giao hàng nè');
                                }

                                // Kiện hàng
                                $kienhang_ids = $delivery['kienhang_id'];
                                $kienhang_ids = explode(",",$kienhang_ids);

                                $packInDeliverys = $orderPackageModel->whereIn('thuongdo_id', $kienhang_ids)->get();

                                // Thêm giao hàng vào kiện
                                if (count($packInDeliverys) > 0) {
                                    foreach ($packInDeliverys as $key_182 => $item_182) {
                                        $item_182->delivery_id = $deliveryDB->id;
                                        $item_182->save();
                                    }
                                }

                                // // Thêm kiện vào giao hàng Logic 2
                                // if (count($kienhang_ids) > 0) {
                                //     foreach ($kienhang_ids as $key_182 => $item_182) {
                                //         $packInDelivery = $orderPackageModel->where('thuongdo_id', $item_182)->first();

                                //         if (!is_null($packInDelivery)) {
                                //             $packInDelivery->delivery_id = $deliveryDB->id;
                                //             $packInDelivery->save();
                                //         }

                                //         if (is_null($packInDelivery)) {
                                //             $data_pack_null_delivery = [
                                //                 'delivery_id' => $deliveryDB->id,
                                //                 'thuongdo_id' => $item_182,
                                //             ];
                                //             $packDB_182 = $orderPackageModel->create($data_pack_null_delivery);
                                //         }
                                //     }
                                // }

                                // Thêm vào quan hệ giữa giao hàng và đơn
                                $deliveryOrderDB = $deliveryOrderModel->where('order_id', $orderDB->id)
                                                                    ->where('delivery_id', $deliveryDB->id)
                                                                    ->first();

                                if (is_null($deliveryOrderDB)) {
                                    $data_delivery_order = [
                                        'delivery_id'   => $deliveryDB->id,
                                        'order_type'    => 'App\Models\Order', // Ở đây là mặc định các đơn đều là order hết
                                        'order_id'      => $orderDB->id,
                                        'thuongdo_sync' => 1,
                                    ];
                                    $deliveryOrderDB = $deliveryOrderModel->create($data_delivery_order);
                                }
                            }
                        }
                    }
                }

                // Phần khiếu nại
                if ($orderDB) {
                    $complainList = $item['complain'];
                    if (count($complainList) > 0) {

                        foreach ($complainList as $key_120 => $item_120) {
                            // complainTypeFullDB
                            // solutionFullDB
                            // dump($item_120);

                            $complainDB = $complainModel->where('thuongdo_id', $item_120['id'])->first();

                            if (is_null($complainDB)) {
                                $data_complain = [];

                                // ID
                                $data_complain['thuongdo_id'] = $item_120['id'];
                                // Thời gian tạo
                                $data_complain['created_at'] =  date('Y-m-d H:i:s', $item_120['created']);
                                $data_complain['updated_at'] =  date('Y-m-d H:i:s', $item_120['changed']);

                                $data_complain['order_id'] = $orderDB->id;
                                if (isset($complainTypeFullDB[$item_120['loaikhieunai']])) {
                                    $data_complain['complain_type_id'] = $complainTypeFullDB[$item_120['loaikhieunai']];
                                }
                                if ($item_120['phuong_an']) {
                                    if (isset($solutionFullDB[$item_120['phuong_an']])) {
                                        $data_complain['solution_id'] = $solutionFullDB[$item_120['phuong_an']];
                                    }
                                }
                                $data_complain['customer_id'] = $customerDB->id;
                                $data_complain['status'] = $this->getStatusComplain($item_120['trang_thai_id']);


                                $data_complain['staff_care_id'] = $orderDB->staff_care_id;
                                $data_complain['staff_counselor_id'] = $orderDB->staff_counselor_id;
                                $data_complain['staff_order_id'] = $orderDB->staff_order_id;

                                if ($item_120['uid_tiep_nhan'] != null) {
                                   $userTiepNhanDB = $userModel->where('thuongdo_uuid', $item_120['uid_tiep_nhan'])->first();
                                    if (!is_null($userTiepNhanDB)) {
                                        $data['staff_group_id'] = $userTiepNhanDB->id;
                                    }
                                }

                                if ($item_120['uid_xu_ly'] != null) {
                                   $userXuLyDB = $userModel->where('thuongdo_uuid', $item_120['uid_xu_ly'])->first();
                                    if (!is_null($userXuLyDB)) {
                                        $data['staff_complain_id'] = $userXuLyDB->id;
                                    }
                                }

                                $data_complain['compensation'] = $item_120['tien_boi_thuong'] == null ? 0 : $item_120['tien_boi_thuong'];

                                // dd($data_complain);

                                // Loại phản hồi
                                // 1 => Bồi thường
                                // 2 => Đổi trả
                                // 3 => Phát bù

                                $complainDB = $complainModel->create($data_complain);
                            }


                            // Gán chi tiết đơn
                            $productsTD = [];
                            if (isset($item_120['products'])) {
                                $productsTD = $item_120['products'];
                            }
                            if (count($productsTD)) {
                                foreach ($productsTD as $key_92013 => $item_92013) {

                                    $complainDetailDB = $complainDetailModel->where('thuongdo_id', $item_120['id'])->first();

                                    if (is_null($complainDetailDB)) {
                                        $packDB = $orderPackageModel->where('thuongdo_id', $key_92013)->first();
                                        $orderDetailComplainDB = $orderDetailModel->where('thuongdo_kien_hang_id', $key_92013)->first();

                                        if (!is_null($packDB) && !is_null($orderDetailComplainDB)) {

                                            $data_complain_detail = [
                                                'complain_id'      => $complainDB->id,
                                                'order_detail_id'  => $orderDetailComplainDB->id,
                                                'order_package_id' => $packDB->id,
                                                'thuongdo_id'      => $item_120['id'],
                                            ];

                                            $complainDetailDB = $complainDetailModel->create($data_complain_detail);
                                        } else {
                                            if ($showLog) {
                                                \Log::info('Không đủ data tạo khiếu nại');
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // dd("Done");
            }
        }


        // \Log::info('DoneSyncOrder 29122023: ' . $page);

        // Nếu còn data thì tiếp tục lấy tiếp
        if (count($result) > 0) {
            $pageNew = $page + 1;
            $maxPage = env('APP_ENV') == 'local1' ? 10 : 30000000;
            if ($pageNew < $maxPage) {
                $job = new \App\Jobs\SyncTD\SyncOrder($pageNew);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    // Xử lý phần lấy order_free, discount_fee ở phần OrderSupplier
    public function getOrderFeeForOrderSup($totalOrder, $percent)
    {
        $default = 9000;
        return AccountingHelper::getCosts($percent > 100  ? $default : max(($totalOrder * ($percent / 100)), $default));
    }

    public function getOrderDiscountForOrderSup($orderFeeTmp, $percent)
    {
        return AccountingHelper::getCosts($orderFeeTmp * ($percent / 100));
    }


    public function getStatusOder($status)
    {
        $arr_status_TD = [
            '1' => 'status_0',
            '2' => 'status_6',
            '3' => 'status_4',
            '4' => 'status_2',
            '13' => 'status_1',
            '14' => 'status_3',
            '15' => 'status_5',
        ];

        return $arr_status_TD[$status];
    }

    public function getStatusPack($status)
    {
        $arr_status_TD = [
            '1' => 'status_4', // Đang về kho TQ
            '3' => 'status_5', // Đến kho TQ
            '4' => 'status_6', // Đang về VN
            '5' => 'status_10', // Đến kho VN
            '6' => 'status_15', // Đang giao hàng
            '7' => 'status_16', // Đã nhận hàng
            '9' => 'status_0', // Chờ xử lý
            '10' => 'status_1', // Xác nhận lại
            '11' => 'status_17', // Đã hủy
            '12' => 'status_14', // Chờ COD
            '13' => 'status_11', // Đang về Tp.HCM
            '14' => 'status_13', // Đang về Hải Phòng
            '15' => 'status_2', // Đang ở kho QC
            '16' => 'status_3', // Đang về Bằng Tường
            '17' => 'status_7', // Đến kho Bằng Tường
            '18' => 'status_8', // Đang kiểm hàng
            '21' => 'status_9', // Đang chuyển hàng
            '22' => 'status_12', // Đang kiểm hàng HCM
            '23' => 'status_18', // Hàng chờ thanh lý
        ];

        return $arr_status_TD[$status];
    }

    public function getDeliveryPayment($id)
    {
        $arr_payment_TD = [
            null => 'null2024',
            '1' => 'e-wallet',
            '2' => 'cod',
            '3' => 'bank', // Chuyển khoản ngân hàng
            '4' => 'cash', // Tiền mặt
        ];

        if (array_key_exists($id, $arr_payment_TD)) {
            return $arr_payment_TD[$id];
        }

        $code = 'eee:' . $id;
        return $code;
    }

    public function getStatusComplain($id)
    {
        $arr_complain_TD = [
            '1' => 'status_0',     // Chờ xử lý
            '2' => 'status_3',     // Đã hoàn thành
            '3' => 'status_4',     // Đã hủy
            '4' => 'status_1',     // Đang xử lý
            '5' => 'status_2',     // Đã xử lý
        ];
        return $arr_complain_TD[$id];
    }
}
