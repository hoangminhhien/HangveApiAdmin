<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateBankPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $banksPrority = [
            'Techcombank', 'Agribank', 'VCB', 'VPBank', 'MB', 'BIDV', 'ACB', 'VIB', 'Vietinbank', 'SHB'
        ];

        DB::table('banks')->whereIn('short_name', $banksPrority)->update(['priority' => 1]);
    }
}
