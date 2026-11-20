<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateBankChargeCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $banks = DB::table('banks')->get();
        foreach ($banks as $key => $bank) {
           DB::table('banks')->where('id', $bank->id)->update(['charge_code' => 'HV'. ($key + 1)]);
        }
    }
}
