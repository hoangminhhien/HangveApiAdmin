<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use GuzzleHttp\Client as Guzzle;

use App\Helpers\RandomHelper;
use App\Services\ReportService;
use App\Constants\ReportConstant;

use App\Constants\WarehouseConstant;
use App\Constants\PackageConstant;
use App\Constants\TransactionConstant;

use App\Helpers\AccountingHelper;

use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

class SyncID2024Controller extends Controller
{
    public function __construct()
    {

    }

    public function sync2024Location(Request $request)
    {
        // Đồng bộ tình thành, quận huyện
        $guzzle = new Guzzle;
        $url = env('URL_HANGVE_OLD') . 'partner_id/location-sync-962516312327172';

        $options = [];
        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result['code'] == 200 && count($result['data']) > 0) {
            $provinceRepo = \App::make('App\Models\Province');
            $districtRepo = \App::make('App\Models\District');
            $wardRepo = \App::make('App\Models\Ward');

            // Lưu tỉnh
            foreach ($result['data'] as $key_1 => $province) {
                $provinceDB = $provinceRepo->where('name', $province['name'])->first();
                if (is_null($provinceDB)) {
                    $provinceDB = $provinceRepo->create($province);
                }
                // Lưu quận huyện
                foreach ($province['data_district'] as $key_2 => $district) {
                    $districtDB = $districtRepo->where('name', $district['name'])
                                                ->where('province_id', $provinceDB->id)
                                                ->first();
                    if (is_null($districtDB)) {
                        $district['province_id'] = $provinceDB->id;
                        $districtDB = $districtRepo->create($district);
                    }
                    // Lưu phường xã
                    foreach ($district['data_ward'] as $key_3 => $ward) {
                        $wardDB = $wardRepo->where('code', $ward['code'])
                                                    ->where('district_id', $districtDB->id)
                                                    ->first();
                        if (is_null($wardDB)) {
                            $ward['district_id'] = $districtDB->id;
                            $wardDB = $wardRepo->create($ward);
                        }
                    }
                }
            }
        }

        // Khởi tạo 1 tổ chức:
        $originRepo = \App::make('App\Models\Organization');
        $originDB = $originRepo->where('code', 'TC_0001')->first();
        if (is_null($originDB)) {
            $data_origin = [
                'name' => 'CÔNG TY CỔ PHẦN QUỐC TẾ THƯƠNG ĐÔ',
                'address' => 'Số 14 Nguyễn Viết Xuân, Thanh Xuân, Hà Nội',
                'email' => 'thuongdo@gmail.com',
                'phone_number' => '19006825- 0473005999',
                'code' => 'TC_0001',
                'avatar' => '/storage/organization/thuongdo.png',
                'domain' => '*',
                'tax_code' => '0107464325'
            ];
            $originDB = $originRepo->create($data_origin);
        }


        // Tạo seeder 1 kho:
        // $warehhouseRepo = \App::make('App\Models\Warehouse');
        // $warehouseDB = $originRepo->where('code', 'WH_001')->first();
        // if (is_null($warehouseDB)) {
        //     $data_warehouse_test = [
        //         'code'            => 'WH_001',
        //         'name'            => 'Kho 19 Nguyễn Trãi',
        //         'address'         => '19 Nguyễn Trãi, Thanh Xuân',
        //         'address_other'   => '19 Nguyễn Trãi',
        //         'district_id'     => 1,
        //         'province_id'     => 1,
        //         'organization_id' => $originDB->id,
        //         'country'         => 'vi',
        //     ];
        //     $warehouseDB = $warehhouseRepo->create($data_warehouse_test);
        // }
    }

    public function sync2024UserRole(Request $request)
    {
        $userModel = \App::make('App\Models\User');

        $sAdminDB = $userModel->where('email', 'systemadmin@thuongdo.com')->first();
        if (is_null($sAdminDB)) {
            $data_sAdmin = [
                'name'            => 'System Administrator',
                'email'           => 'systemadmin@thuongdo.com',
                'code'            => '0001',
                'password'        => \Hash::make('Ak28278944032120'),
                'organization_id' => 1,
                'status'          => 1
            ];
            $sAdminDB = $userModel->create($data_sAdmin);
        }


        $adminDB = $userModel->where('email', 'admin@thuongdo.com')->first();
        if (is_null($adminDB)) {
            $data_Admin = [
                'name'            => 'Administrator',
                'email'           => 'admin@thuongdo.com',
                'code'            => '0002',
                'password'        => \Hash::make('Nc2827822403211a1821'),
                'organization_id' => 1,
                'status'          => 1
            ];
            $adminDB = $userModel->create($data_Admin);
        }

        // Sync Quyền
        $adminDB->assignRole('admin.1');
    }

}

