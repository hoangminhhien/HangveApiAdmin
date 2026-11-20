<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

class SyncCustomerV3 implements ShouldQueue
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

        \Log::info('SyncPageV3 ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/customer';

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

            $count = 0;
            $kCodeLast = 0;
            $customerModel = \App::make('App\Models\Customer');
            $userModel = \App::make('App\Models\User');
            $provinceModel = \App::make('App\Models\Province');
            $districtModel = \App::make('App\Models\District');
            $warehouseModel = \App::make('App\Models\Warehouse');
            $roleModel = \App::make('App\Models\Role');
            $organizationModel = \App::make('App\Models\Organization');

            // Lấy tổ chức TD ra
            $organizationTD = $organizationModel->where('code', 'TC_0001')->first();

            // Lấy ra role của 4 nhân viên
            $nameTuVan = 'nhan-vien-tu-van.' . $organizationTD->id;
            $roleTuVan = $roleModel->where('name', $nameTuVan)->where('type', 0)->first();

            $nameChamSoc = 'nhan-vien-cham-soc.' . $organizationTD->id;
            $roleChamSoc = $roleModel->where('name', $nameChamSoc)->where('type', 0)->first();

            $arrTuVan = [];
            $arrChamSoc = [];

            foreach ($result as $key => $item) {
                if ($item['mail'] == '') {
                    continue;
                }
                if ($item['kcode'] == '') {
                    continue;
                }
                // loai_vc, kenh_id, loai_hinh_id, type_customer, type, tu_van_count_id, loai_khach_hang
                $gioi_tinh = '';
                if ($item['gioi_tinh'] == 1) {
                    $gioi_tinh = 'male';
                }
                if ($item['gioi_tinh'] == 2) {
                    $gioi_tinh = 'female';
                }


                $bod = null;
                if ($item['ngay_sinh'] != '' && $item['ngay_sinh'] > 0) {
                    $bod = date("Y-m-d", $item['ngay_sinh']);
                }

                $name = $item['fullname'];
                if ($item['fullname'] == '') {
                    $name = $item['username'];
                }

                $phone = $item['phone'];
                if ($phone == null) {
                    $phone = 11111;
                }

                $level = $item['cap_do'];
                if ($level == null) {
                    $level = 0;
                }

                // Loại VC:
                // 1 => Đặt hàng
                // 7 => Ký gửi

                $service = 99; // Trong HV 1 là kí gửi, 0 là đặt hàng
                if ($item['loai_vc'] == 1) {
                    $service = 0;
                }
                if ($item['loai_vc'] == 7) {
                    $service = 1;
                }


                // kenh_id:
                // 1 => Google ads
                // 2 => Zalo ads
                // 3 => Facebook ads
                // 4 => OGS
                // 5 => OGS Google
                // 6 => Facebook Form
                // 7 => Google GDN
                // 8 => Email marketing
                // 9 => Tu tim kiem
                // 10=> Thong Tien
                // 11 => Tiktok

                $arr_kenh = [
                    1  => 'Google ads',
                    2  => 'Zalo ads',
                    3  => 'Facebook ads',
                    4  => 'OGS',
                    5  => 'OGS Google',
                    6  => 'Facebook Form',
                    7  => 'Google GDN',
                    8  => 'Email marketing',
                    9  => 'Tu tim kiem',
                    10 => 'Thong Tien',
                    11 => 'Tiktok',
                ];
                $via = null;

                if ($item['kenh_id'] != '') {
                    $arr_temp = [
                        1,2,3,4,5,6,7,8,9,10,11
                    ];
                    if (in_array($item['kenh_id'], $arr_temp)) {
                        $via = $arr_kenh[$item['kenh_id']];
                    }
                }


                // Loai_hinh_id:
                // 1 => Tiêu dùng
                // 2 => Kinh doanh

                $business_type = null;

                if ($item['loai_hinh_id'] == 1) {
                    $business_type = 'personal';
                }
                if ($item['loai_hinh_id'] == 2) {
                    $business_type = 'business';
                }

                // Loại khách hàng :===> bỏ


                // Chia nhân viên:

                // nhan_vien_kinh_doanh_id, nhan_vien_dat_hang_id, nhan_vien_tu_van_id
                // if ($item['nhan_vien_dat_hang_id']) {
                //     dump($item['nhan_vien_dat_hang_id']);
                // }

                // dd($item);

                $data_customer = [
                    'code'            => $item['kcode'],
                    'name'            => $name,
                    'email'           => $item['mail'],
                    'password'        => \Hash::make('123456mb'),
                    'phone_number'    => $phone,
                    'address'         => $item['address'],
                    'bod'             => $bod,
                    'gender'          => $gioi_tinh,
                    'facebook_link'   => $item['facebook'],
                    'level'           => $level,
                    'service'         => $service,
                    'via'             => $via,
                    'business_type'   => $business_type,
                    'organization_id' => $organizationTD->id,
                    'thuongdo_id'     => (int)$item['id'],
                    'thuongdo_uuid'   => (int)$item['uid'],
                ];

                $customerDB = $customerModel->where('email', $data_customer['email'])->first();
                if (is_null($customerDB)) {
                    // Gán lại Kcode:
                    $kCodeLast = $item['kcode'];
                    // Thêm phần tỉnh thành quận huyện
                    $provinceDB = $provinceModel->where('thuongdo_id', $item['tinh_id'])->first();
                    if (!is_null($provinceDB)) {
                        $data_customer['province_id'] = $provinceDB->id;
                    }
                    $districtDB = $districtModel->where('thuongdo_id', $item['quan_id'])->first();
                    if (!is_null($districtDB)) {
                        $data_customer['district_id'] = $districtDB->id;
                    }

                    // Thêm phần kho nhận hàng
                    $warehouseDB = $warehouseModel->where('thuongdo_id', $item['chi_nhanh_id'])->first();
                    if (!is_null($warehouseDB)) {
                        $data_customer['warehouse_id'] = $warehouseDB->id;
                    }

                    // Thêm phần chia nhân vien
                    $staffCareDB = $userModel->where('thuongdo_uuid', $item['nhanvien_cskh'])->first();
                    if (!is_null($staffCareDB)) {
                        $data_customer['staff_care_id'] = $staffCareDB->id;
                        array_push($arrChamSoc, $data_customer['staff_care_id']);
                    }
                    $staffCounselorDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_tu_van_id'])->first();
                    if (!is_null($staffCounselorDB)) {
                        $data_customer['staff_counselor_id'] = $staffCounselorDB->id;
                        array_push($arrTuVan, $data_customer['staff_counselor_id']);
                    }
                    $staffOrderDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_dat_hang_id'])->first();
                    if (!is_null($staffOrderDB)) {
                        \Log::info('NVDH' . $item['nhan_vien_dat_hang_id']);
                        $data_customer['staff_order_id'] = $staffOrderDB->id;
                    }
                    $staffSaleDB = $userModel->where('thuongdo_uuid', $item['nhan_vien_kinh_doanh_id'])->first();
                    if (!is_null($staffSaleDB)) {
                        \Log::info('NVKD' . $item['nhan_vien_kinh_doanh_id']);
                        $data_customer['staff_sale_id'] = $staffSaleDB->id;
                    }
                    // dd($data_customer);

                    $customerNew = $customerModel->create($data_customer);
                    $count += 1;
                }  else {
                    if ($customerDB->staff_care_id) {
                        array_push($arrChamSoc, $customerDB->staff_care_id);
                    }
                    if ($customerDB->staff_counselor_id) {
                        array_push($arrTuVan, $customerDB->staff_counselor_id);
                    }
                }
            }

            // Set Role cho các nhân viên
            $arrTuVan = array_unique($arrTuVan);
            $arrChamSoc = array_unique($arrChamSoc);

            foreach ($arrTuVan as $key => $item1) {
                $staffCareDB = $userModel->where('id', $item1)->first();
                $hasRoleTuVan = $staffCareDB->hasRole($roleTuVan);
                if (!$hasRoleTuVan) {
                    \Log::info('GanTuVan: ' . $staffCareDB->email);
                    $staffCareDB->assignRole($roleTuVan);

                    // Sync Per vào
                    $perOfRole = $roleTuVan->permissions->pluck('name');
                    $staffCareDB->givePermissionTo($perOfRole);
                }
            }

            foreach ($arrChamSoc as $key => $item2) {
                $staffCounselorDB = $userModel->where('id', $item2)->first();
                $hasRoleChamSoc = $staffCounselorDB->hasRole($roleChamSoc);
                if (!$hasRoleChamSoc) {
                    \Log::info('GanChamSoc: ' . $staffCounselorDB->email);
                    $staffCounselorDB->assignRole($roleChamSoc);

                    // Sync Per vào
                    $perOfRole = $roleChamSoc->permissions->pluck('name');
                    $staffCounselorDB->givePermissionTo($perOfRole);
                }
            }

            // Set lại sổ tổng người
            if ($kCodeLast) {
                $reportQuantityModel = \App::make('App\Models\ReportQuantity');
                $reportQuantityUser = $reportQuantityModel->where('key', 'customer')->first();

                if ($reportQuantityUser) {
                    $reportQuantityUser->quantity = $kCodeLast;
                    $reportQuantityUser->save();
                }
            }

            // Nếu còn data thì tiếp tục lấy tiếp
            if (count($result) > 0) {
                $pageNew = $page + 1;
                $job = new \App\Jobs\SyncTD\SyncCustomer($pageNew);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin'); // Dùng chung đỡ phải cấu hình
            }
        }
    }
}
