<?php

namespace Database\Seeders;

use App\Models\DeliveryStore;
use Illuminate\Database\Seeder;

class DeliveryStoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'store_id'        => '3866656',
                'warehouse_id'    => '',
                'name'            => 'Thương Đô Logistics',
                'phone'           => "0965398998",
                'address'         => "Số 14, Nguyễn Viết Xuân, Thanh Xuân, Hà Nội",
                'ward_code'       => '',
                'district_id'     => '',
                'client_id'       => '',
                'bank_account_id' => '',
                'type'            => "ghtk",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '16861764',
                'warehouse_id'    => '1eda5f97-9239-66e8-a055-00163e0109a7',
                'name'            => 'Thương Đô',
                'phone'           => "0898169666",
                'address'         => "298 Trần Điền, Đường Trần Điền, Phường Định Công, Quận Hoàng Mai, Hà Nội",
                'ward_code'       => '',
                'district_id'     => '',
                'client_id'       => '',
                'bank_account_id' => '',
                'type'            => "ghtk",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '16861767',
                'warehouse_id'    => '1eda5f97-923c-6546-8556-00163e0109a7',
                'name'            => 'Thương Đô',
                'phone'           => "0898166666",
                'address'         => "107 E2 Ngõ 27, Đường Tạ Quang Bửu, Phường Bách Khoa, Quận Hai Bà Trưng, Hà Nội",
                'ward_code'       => '',
                'district_id'     => '',
                'client_id'       => '',
                'bank_account_id' => '',
                'type'            => "ghtk",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '16861773',
                'warehouse_id'    => '1eda5f97-923e-69f4-baa3-00163e0109a7',
                'name'            => 'Thương Đô Logistics',
                'phone'           => "0898666666",
                'address'         => "Số 21, Đường 18M, Khu Đô Thị Mỗ Lao, Phường Mộ Lao, Quận Hà Đông, Hà Nội",
                'ward_code'       => '',
                'district_id'     => '',
                'client_id'       => '',
                'bank_account_id' => '',
                'type'            => "ghtk",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '16861784',
                'warehouse_id'    => '1eda5f97-9240-6f06-b38e-00163e0109a7',
                'name'            => 'Thương Đô',
                'phone'           => "0898166660",
                'address'         => "47, Đường Nguyễn Văn Luông, Phường 12, Quận 6, TP Hồ Chí Minh",
                'ward_code'       => '',
                'district_id'     => '',
                'client_id'       => '',
                'bank_account_id' => '',
                'type'            => "ghtk",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '358088',
                'warehouse_id'    => '',
                'name'            => 'Thương Đô',
                'phone'           => "0965398998",
                'address'         => "176 Trường Chinh",
                'ward_code'       => '1A0404',
                'district_id'     => '1486',
                'client_id'       => '486391',
                'bank_account_id' => '',
                'type'            => "ghn",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '3872780',
                'warehouse_id'    => '1eda5f97-9239-66e8-a055-00163e0109a7',
                'name'            => 'Thương Đô',
                'phone'           => "19006825",
                'address'         => "298 Trần Điền",
                'ward_code'       => '1A0802',
                'district_id'     => '1490',
                'client_id'       => '486391',
                'bank_account_id' => '',
                'type'            => "ghn",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '3872795',
                'warehouse_id'    => '1eda5f97-923c-6546-8556-00163e0109a7',
                'name'            => 'Thương Đô',
                'phone'           => "19006825",
                'address'         => "107 E2 Ngõ 27 Phố Tạ Quang Bửu",
                'ward_code'       => '1A0302',
                'district_id'     => '1488',
                'client_id'       => '486391',
                'bank_account_id' => '',
                'type'            => "ghn",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '3872797',
                'warehouse_id'    => '1eda5f97-923e-69f4-baa3-00163e0109a7',
                'name'            => 'Thương Đô',
                'phone'           => "19006825",
                'address'         => "Số 21 Phố văn quán",
                'ward_code'       => '1B1515',
                'district_id'     => '1542',
                'client_id'       => '486391',
                'bank_account_id' => '',
                'type'            => "ghn",
                'created_at'      => now(),
            ],
            [
                'store_id'        => '3872803',
                'warehouse_id'    => '1eda5f97-9240-6f06-b38e-00163e0109a7',
                'name'            => 'Thương Đô',
                'phone'           => "19006825",
                'address'         => "47 Nguyễn Văn Luông",
                'ward_code'       => '20612',
                'district_id'     => '1448',
                'client_id'       => '486391',
                'bank_account_id' => '',
                'type'            => "ghn",
                'created_at'      => now(),
            ],
        ];

        DeliveryStore::query()->insert($data);
    }
}
