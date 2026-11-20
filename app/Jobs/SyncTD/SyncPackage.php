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

class SyncPackage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $page;
    protected $typePack;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($page, $typePack)
    {
        $this->page = $page;
        $this->typePack = $typePack;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $page = $this->page;
        $typePack = $this->typePack;

        \Log::info('SyncPackage V18 - 26092024: - v81: ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/package';

        $pageCron = $page;

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'created',
                    'contents' => '1728266460',
                ],
                [
                    'name'     => 'type',
                    'contents' => $typePack,
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
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $orderModel = \App::make('App\Models\Order');
            $customerModel = \App::make('App\Models\Customer');
            $warehouseModel = \App::make('App\Models\Warehouse');
            $categoryModel = \App::make('App\Models\Category');
            $userModel = \App::make('App\Models\User');

            foreach ($result as $key => $item) {

                // if ($item['ma_van_don'] == '78835981850228') {
                //     dd('done');
                // } else {
                //     continue;
                // }

                // dump($item);
                $pack = $item;
                $orderPackageDB = $orderPackageModel->where('thuongdo_id', $item['id'])->first();
                if (is_null($orderPackageDB)) {
                    $customerDB = $customerModel->where('thuongdo_uuid', $item['uid'])->first();
                    if (is_null($customerDB)) {
                        \Log::info('Không có khách kiện page: ' . $pageCron);
                    }

                    $pack = $item;

                    $data_order_package = [
                        'order_id'   => $item['order_id'],
                        'code'       => null,
                        'code_po'    => $pack['ma_dat_hang'],
                        'bill_code'  => $pack['ma_van_don']
                    ];

                    $data_order_package['delivery_id'] = null; // Chưa có

                    $data_order_package['organization_id'] = 1;

                    $arr_type_order_pack = [
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
                    // $exchange_rateTD = $orderDB->exchange_rate;
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

                    // $data_order_package['customer_delivery_id'] = $orderDB->customer_delivery_id; // Xem lúc lưu tại app/Http/Controllers/PackageController.php




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

                    $staffCareDB = $userModel->where('thuongdo_uuid', $pack['nhan_vien_cs_uid'])->first();
                    if (!is_null($staffCareDB)) {
                        $data_order_package['staff_care_id'] = $staffCareDB->id;
                    }

                    $staffCounselorDB = $userModel->where('thuongdo_uuid', $pack['nhan_vien_tu_van_uid'])->first();
                    if (!is_null($staffCounselorDB)) {
                        $data_order_package['staff_counselor_id'] = $staffCounselorDB->id;
                    }

                    // Chưa tìm dc đối tượng này
                    $staffOrderDB = $userModel->where('thuongdo_uuid', $pack['uid_xu_ly'])->first();
                    if (!is_null($staffOrderDB)) {
                        $data_order_package['staff_order_id'] = $staffOrderDB->id;
                    }

                    // Đơn giá phí vận chuyển quốc tế
                    $data_order_package['unit_price'] = $pack['price'] == null ? 0 : $pack['price'];

                    // Status

                    $data_order_package['status'] = $this->getStatusPack($pack['status']);

                    // ID
                    $data_order_package['thuongdo_id'] = $pack['id'];

                    // Thời gian tạo
                    $data_order_package['created_at'] =  date('Y-m-d H:i:s', $pack['created']);

                    // staff_group_id
                    if (!is_null($customerDB)) {
                        $data_order_package['staff_group_id'] = $customerDB->staff_group_id;
                    }

                    $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageModel, $data_order_package) {
                        $orderPackageDB = $orderPackageModel->create($data_order_package);
                        return $orderPackageDB;
                    });
                } else {
                    $orderPackageDB->status = $this->getStatusPack($pack['status']);

                    $orderPackageDB = OrderPackage::withoutEvents(function () use ($orderPackageDB) {
                        $orderPackageDB->save();
                        return $orderPackageDB;
                    });

                    // Cập nhật giá
                    $this->updatePricePackage($orderPackageDB, $item);
                }
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

        // return $arr_status_TD[$status];
        return array_key_exists($status, $arr_status_TD) ? $arr_status_TD[$status] : 'status_null';
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
