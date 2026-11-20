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

use App\Constants\DeliveryConstant;
use App\Models\OrderPackage;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Delivery;

// Đồng bộ hoàn tiền
use App\Constants\RefundConstant;

class SyncRefund implements ShouldQueue
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

        \Log::info('SyncRefund V16 Update - 30092024: - v2: ' . $page);

        sleep(5);

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
            $userModel = \App::make('App\Models\User');

            // Phần hoàn tiền
            $refundModel = \App::make('App\Models\Refund');

            foreach ($result as $key => $item) {
                if ($showLog) {
                    \Log::info('ID:' . $key . ':code:' . $item['orders']['order_code']);
                }
                // dump($item);

                $order = $item['orders'];
                $orders_supplier = $item['orders_supplier'];

                if (count($item['refunds']) == 0) {
                   continue;
                } else {

                }

                $orderDB = $orderModel->where('thuongdo_id', $order['id'])->first();
                // Phần hoàn tiên
                if ($orderDB) {
                    $refundList = $item['refunds'];
                    if (count($refundList) > 0) {
                        $arr_status = [
                            '1' => RefundConstant::KEY_STATUS_PENDING, // Chờ xử lý
                            '4' => RefundConstant::KEY_STATUS_PROCESS, // Đang xử lý
                            '5' => RefundConstant::KEY_STATUS_DONE, // Đã xử lý
                            '2' => RefundConstant::KEY_STATUS_DONE, // Đã hoàn thành
                            '3' => RefundConstant::KEY_STATUS_CANCEL, // Đã hủy
                        ];

                        foreach ($refundList as $key => $refundItem) {
                            $staffDB = $userModel->where('thuongdo_uuid', $refundItem['uid'])->first();
                            $status_refund = array_key_exists($refundItem['status'], $arr_status) ? $arr_status[$refundItem['status']] : 0;

                            $file = "";
                            if (count($refundItem['files']) > 0) {
                                $firstKey = array_key_first($refundItem['files']);
                                $file = $refundItem['files'][$firstKey]['url'];
                            }

                            $sotienTD = $refundItem['sotien'] == null ? 0 : $refundItem['sotien'];

                            $data_refund = [
                                'organization_id' => 1,
                                'staff_id'        => is_null($staffDB) ? 0 : (int) $staffDB->id,
                                'order_id'        => (int) $orderDB->id,
                                'order_code'      => $orderDB->code,
                                'order_status'    => $orderDB->status,
                                'amount_cn'       => round($sotienTD / $refundItem['ty_gia'], 2),
                                'amount_vn'       => $sotienTD,
                                'exchange_rate'   => $refundItem['ty_gia'],
                                'note'            => $refundItem['ghi_chu'],
                                'type'            => 1, // Hoàn tiền cho khách
                                'status'          => $status_refund,
                                'file'            => $file,
                                'files'           => json_encode($refundItem['files'], JSON_UNESCAPED_UNICODE),
                                'created_at'      => date('Y-m-d H:i:s', $refundItem['created']),
                                'thuongdo_id'     => $refundItem['id']
                            ];

                            $refundDB = $refundModel->where('thuongdo_id', $refundItem['id'])->first();
                            if (is_null($refundDB)) {
                                $refundDB = $refundModel->create($data_refund);
                            }
                        }
                    }
                }
            }
        }


        // \Log::info('DoneSyncOrder 29122023: ' . $page);

        // Nếu còn data thì tiếp tục lấy tiếp
        // if (count($result) > 0) {
        //     $pageNew = $page + 1;
        //     $maxPage = env('APP_ENV') == 'local1' ? 10 : 30000000;
        //     if ($pageNew < $maxPage) {
        //         $job = new \App\Jobs\SyncTD\SyncOrder($pageNew);
        //         dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');
        //     }
        // }
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

    public function getStatusDelivery($id)
    {
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
        return $arr_status[$id];
    }

    public function updatePricePackage($orderPackageDB, $pack)
    {
        $data_order_package = [];

        // Thêm đơn giá cho phần phí vận chuyển quốc tế:
        $data_order_package['unit_price'] = $pack['price'] == null ? 0 : $pack['price'];

        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('thuongdo_uuid', $pack['uid'])->first();
        if (!is_null($customerDB)) {
            $data_order_package['customer_id'] = (int) $customerDB->id;
        }

        $data_order_package['code_po'] = $pack['ma_dat_hang'];
        $data_order_package['bill_code'] = $pack['ma_van_don'];

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

        // $orderPackageDB->update($data_order_package);
        $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB, $data_order_package) {
            $orderPackageDB->update($data_order_package);
            return $orderPackageDB;
        });
    }
}
