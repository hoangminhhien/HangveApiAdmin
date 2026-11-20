<?php

namespace Database\Seeders;

use App\Constants\LocateConstant;
use App\Models\District;
use App\Models\Organization;
use App\Models\Province;
use App\Models\Warehouse;
use App\Models\Website;
use Illuminate\Database\Seeder;

class WebsiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Website::query()->truncate();
        Website::query()->insert(
            [
                [
                    'code' => 'HV',
                    'name' => 'hangve.com',
                    'status' => 1,
                ],
                [
                    'code' => 'TD',
                    'name' => 'thuongdo.com',
                    'status' => 1,
                ],
                [
                    'code' => 'NH247',
                    'name' => 'nguonhang247.com',
                    'status' => 1,
                ],
            ]
        );
    }
}
