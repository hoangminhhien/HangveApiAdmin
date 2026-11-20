<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

class SyncOrderV1 implements ShouldQueue
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

        \Log::info('SyncOrder ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/supplier';

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

        if ($result && count($result)) {

            $organizationModel = \App::make('App\Models\Organization');
            $orderModel = \App::make('App\Models\Order');
            $customerModel = \App::make('App\Models\Customer');
            $customerDeliveryModel = \App::make('App\Models\CustomerDelivery');
            $userModel = \App::make('App\Models\User');
            $warehouseModel = \App::make('App\Models\Warehouse');
            $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            foreach ($result as $key => $item) {
                $order = $item['orders'];

                // dd($item);
                // dd($order);

                if ($order['dong_go']) {
                    // dd($order);
                }
                if ($order['kiem_diem']) {
                    // dd($order);
                }
                if ($order['chong_soc']) {
                    // dd($order);
                }

                if ($order['kho_id']) {
                    // dd($order);
                }

                $truso = $order['truso_id'];
                if (!in_array($truso, [null,1,2,3,7,10,11])) {
                    \Log::info('truso_id:' . $order['truso_id']);
                }

                // if ($order['website_id'] != 1 && $order['website_id'] != null) {
                //     dd($order);
                // }

                if ($order['order_status'] == 3) {
                    // dd($item);
                }

                // Time
                $date_purchased = date('Y-m-d H:i:s', $order['dat_coc_time']);
                $date_ordered   = date('Y-m-d H:i:s', $order['dat_hang_time']);
                $date_done      = date('Y-m-d H:i:s', $order['hoan_thanh_time']);

                $pham_tram_dat_coc = $order['pham_tram_dat_coc'] == null ? 0 : $order['pham_tram_dat_coc'];

                $data_order = [
                    'organization_id'                => $organizationTD->id,
                    'thuongdo_id'                    => $order['id'],
                    'thuongdo_created'               => $order['created'],
                    'code'                           => $order['order_code'],
                    'code_po'                        => null,
                    'order_cost'                     => $order['total_dat_hang'],
                    'exchange_rate'                  => $order['ti_gia'],

                    // 'delivery_type'               => $order['delivery_type'], // Chưa có
                    // 'delivery_cost'               => $order['delivery_cost'], // Chưa có

                    // 'inspection_cost'             => $order['inspection_cost'], // Chưa có
                    // 'woodworking_cost'            => $order['woodworking_cost'], // Chưa có
                    // 'international_shipping_cost' => $order['international_shipping_cost'], // Chưa có
                    // 'china_shipping_cost'         => $order['china_shipping_cost'], // Chưa có

                    // 'is_woodworking'              => $order['dong_go'], // Không tìm ra
                    // 'is_inspection'               => $order['kiem_diem'], // Không tìm ra
                    // 'is_shock_proof'              => $order['chong_soc'], //  Không tìm ra

                    'is_deposit'                     => 0, //  Đặt cọc
                    'deposit_percent'                => $pham_tram_dat_coc, //  % Đặt cọc
                    'deposit_cost'                   => $pham_tram_dat_coc * $order['total'] / 100, // Chưa tìm ra , Tiền đặt cọc,

                    'is_tax'                         => false, // Bỏ
                    'tax_percent'                    => 0, // Bỏ

                    'discount_percent'               => $order['phan_tram_chiet_khau'], // % Chiết Khấu
                    'discount_cost'                  => $order['phi_chiet_khau'], // Phí Chiết Khấu

                    'order_fee'                   => $order['phi_giaodich'], // Phí Đặt hàng
                    'order_percent'               => $order['phi_dich_vu'], // % đặt hàng

                    'date_purchased'                 => $date_purchased, // Thời gian thanh toán (đặt cọc)
                    'date_ordered'                   => $date_ordered, // Thời gian đặt hàng
                    'date_done'                      => $date_done, // Thời gian hoàn thành

                    'status'                      => $this->getStatusOder($order['order_status']),
                ];

                $orderDB = $orderModel->where('thuongdo_id', $data_order['thuongdo_id'])->first();

                if (is_null($orderDB)) {
                    // Thêm customer_id
                    $customerDB = $customerModel->where('thuongdo_uuid', $order['uid'])->first();
                    if (!is_null($customerDB)) {
                        $data_order['customer_id'] = $customerDB->id;
                    } else {
                        \Log::info('Không tim khách');
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
                    // uid_xu_ly
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

                    // Chưa tìm dc đối tượng này
                    // $staffOrderDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_dat_hang_id'])->first();
                    // if (!is_null($staffOrderDB)) {
                    //     $data_order['staff_order_id'] = $staffOrderDB->id;
                    // }

                    $orderDB = $orderModel->create($data_order);
                }

                // Tạo order_status_time
                if ($orderDB) {
                    $orderStatusTimeDB = $orderStatusTimeModel->where('order_id', $orderDB->id)->first();

                    if (is_null($orderStatusTimeDB)) {
                        $histories = $item['lich_su_don_hang'];

                        $data_order_status_time = [];
                        foreach ($histories as $key => $history) {
                            $status = $this->getStatusOder($history['status_id']);
                            $data_order_status_time[$status] = date('Y-m-d H:i:s', $history['created']);
                        }
                        $data_order_status_time['order_id'] = $orderDB->id;

                        $orderStatusTimeDB = $orderStatusTimeModel->create($data_order_status_time);
                    }
                }

                // Tạo Order Suplier
                if ($orderDB) {

                }


                // dd('Done');
            }

            // Nếu còn data thì tiếp tục lấy tiếp
            if (count($result) > 0) {
                $pageNew = $page + 1;
                $job = new \App\Jobs\SyncTD\SyncOrder($pageNew);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin'); // Dùng chung đỡ phải cấu hình
            }
        }
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
}
