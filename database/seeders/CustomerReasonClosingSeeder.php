<?php

namespace Database\Seeders;

use App\Models\CustomerReasonInactive;
use Illuminate\Database\Seeder;

use App\Models\ReportComplain;
use App\Models\ReportDelivery;
use App\Models\ReportFine;

use App\Constants\LocateConstant;

class CustomerReasonClosingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Lí do hủy tài khoản
        CustomerReasonInactive::query()->truncate();
        $data = ['Tài khoản ảo', 'Khách không có nhu cầu', 'Khách hàng đặt hàng cấm', 'Trùng tài khoản'];
        $result = [];
        $customerReasonInactiveModel = \App::make('App\Models\CustomerReasonInactive');
        foreach ($data as $item) {
            $customerReasonInactiveDB = $customerReasonInactiveModel->where('name', $item)->first();
            if (is_null($customerReasonInactiveDB)) {
                $data_insert = [
                    'name' => $item
                ];
                $customerReasonInactiveDB = $customerReasonInactiveModel->create($data_insert);
            }
        }
        // CustomerReasonInactive::query()->insert($result);


        // Lí do khiếu nại
        $data_solution = [
            [
                'name'        => 'Nhận bồi thường',
                'status'      => 1,
                'code'        => 'NBT',
                'thuongdo_id' => 1,
            ],
            [
                'name'        => 'Đổi trả',
                'status'      => 0,
                'code'        => '',
                'thuongdo_id' => 2,
            ],
            [
                'name'        => 'Phát bù',
                'status'      => 1,
                'code'        => '',
                'thuongdo_id' => 3,
            ],
        ];

        $solutionModel = \App::make('App\Models\Solution');
        foreach ($data_solution as $item) {
            $solutionDB = $solutionModel->where('name', $item['name'])->first();
            if (is_null($solutionDB)) {
                $solutionDB = $solutionModel->create($item);
            }
        }

        // Phòng ban
        $data_department = [
            [
                'name'        => 'Kinh doanh',
                'thuongdo_id' => 1,
                'type'        => 'BUSINESS'
            ],
            [
                'name'        => 'Công nghệ',
                'thuongdo_id' => 2,
                'type'        => 'TECHNOLOGY'
            ],
            [
                'name'        => 'Kho vận',
                'thuongdo_id' => 3,
                'type'        => 'LOGISTICS'
            ],
            [
                'name'        => 'Kế toán - Lễ tân',
                'thuongdo_id' => 4,
                'type'        => 'ACCOUNTANT'
            ],
            [
                'name'        => 'Nhân sự',
                'thuongdo_id' => 5,
                'type'        => 'HUMAN_RESOURCES'
            ],
            [
                'name'        => 'Chi nhánh Đông Hưng',
                'thuongdo_id' => 6,
                'type'        => 'DONG_HUNG_BRANCH'
            ],
            [
                'name'        => 'Marketing',
                'thuongdo_id' => 7,
                'type'        => 'MARKETING'
            ],
            [
                'name'        => 'Tư vấn',
                'thuongdo_id' => 9,
                'type'        => 'CONSULTING'
            ],
            [
                'name'        => 'Chăm sóc',
                'thuongdo_id' => 14,
                'type'        => 'TAKE_CARE'
            ],
            [
                'name'        => 'Giao dịch quốc tế',
                'thuongdo_id' => 17,
                'type'        => 'INTERNATIONAL_TRADE'
            ],
            [
                'name'        => 'Đặt hàng',
                'thuongdo_id' => 20,
                'type'        => 'ORDER'
            ],
            [
                'name'        => 'Trung Chuyển',
                'thuongdo_id' => 23,
                'type'        => 'TRANSSHIPMENT'
            ],
            [
                'name'        => 'Trả Hàng',
                'thuongdo_id' => 25,
                'type'        => 'RETURNS'
            ],
            [
                'name'        => 'Chi nhánh Quảng Châu',
                'thuongdo_id' => 26,
                'type'        => 'GUANGZHOU_BRANCH'
            ],
            [
                'name'        => 'Báo giá',
                'thuongdo_id' => 0,
                'type'        => 'QUOTE'
            ],
            [
                'name'        => 'Đặt hàng',
                'thuongdo_id' => 0,
                'type'        => 'ORDER'
            ],
            [
                'name'        => 'Tư vấn',
                'thuongdo_id' => 0,
                'type'        => 'CONSULTING'
            ],
            [
                'name'        => 'Chăm sóc khách hàng',
                'thuongdo_id' => 0,
                'type'        => 'TAKE_CARE_CUSTOMER'
            ],
            [
                'name'        => 'Xử lý khiếu nại',
                'thuongdo_id' => 0,
                'type'        => 'COMPLAIN'
            ]
        ];

        $departmentModel = \App::make('App\Models\Department');
        foreach ($data_department as $item) {
            $departmentDB = $departmentModel->where('type', $item['type'])->first();
            if (is_null($departmentDB)) {
                $item['organization_id'] = 1;
                $departmentDB = $departmentModel->create($item);
            }
        }


        // Thêm Report cho Giao hàng, khiếu nại cho Thương Đô
        $data = ['organization_id' => 1];
        ReportDelivery::query()->firstOrCreate($data);
        ReportComplain::query()->firstOrCreate($data);
        ReportFine::query()->firstOrCreate($data);


        // Thêm luôn 2 kho mặc định CN
        $warehouseModel = \App::make('App\Models\Warehouse');
        $data_warehouse_cn = [
            [
                'code' => 'CN-QD-QC',
                'name' => 'Kho Quảng Châu',
                'organization_id' => 1,
                'address' => '广东省 广州市 白云区 石井街道 凰岗村 凤鸣路117号凰岗二社大型停车场F1档口 094918',
                'country' => LocateConstant::COUNTRY_CN
            ],
            [
                'code' => 'CN-QT-DH',
                'name' => 'Kho Đông Hưng',
                'organization_id' => 1,
                'address' => '广东省 广州市 白云区 石井街道 凰岗村 凤鸣路117号凰岗二社大型停车场F1档口 094918',
                'country' => LocateConstant::COUNTRY_CN
            ],
            [
                'code' => 'CN-TQ-BT',
                'name' => 'Kho Bằng Tường',
                'organization_id' => 1,
                'address' => '广西凭祥市南山恒标粮油市场B2号287470仓益翔电子商务有限公司',
                'country' => LocateConstant::COUNTRY_CN
            ],
        ];

        foreach ($data_warehouse_cn as $key => $item) {
            $warehouseDB = $warehouseModel->where('code', $item['code'])->first();
            if (is_null($warehouseDB)) {
                $warehouseDB = $warehouseModel->create($item);
            }
        }
    }
}
