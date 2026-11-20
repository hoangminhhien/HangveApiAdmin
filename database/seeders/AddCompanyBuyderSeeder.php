<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Constants\OrganizationConstant;

class AddCompanyBuyderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $lastCode = Organization::latest('id')->withTrashed()->value('code');
        if ($lastCode) {
            $number = (int) substr($lastCode, 3); // 1
            $newCode = 'TC_' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newCode = 'TC_0001';
        }

        $data = [
            'code' => $newCode,
            'name' => 'CÔNG TY BUYDER',
            'short_name' => OrganizationConstant::SHORT_NAME_BUYDER,
            'email' => 'admin@buyer.com',
        ];
        Organization::firstOrCreate(
            ['short_name' => $data['short_name']],
            $data
        );
    }
}
