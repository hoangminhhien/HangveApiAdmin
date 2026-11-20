<?php

namespace Database\Seeders;

use App\Constants\OrderConstant;
use App\Models\Ecommerce;
use Illuminate\Database\Seeder;

class EcommerceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $ecommerce = OrderConstant::ECOMMERCE;
        $arrayInsert = [];
        foreach ($ecommerce as $key => $e) {
            if (!Ecommerce::query()->where('key', $key)->first()) {
                $arrayInsert[] = [
                    'name' => $e,
                    'key' => $key,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        if (!empty($arrayInsert)) {
            Ecommerce::query()->insert($arrayInsert);
        }
        if (!Ecommerce::query()->where('key', 'other')->first()) {
            $arrayInsert = [
                'name' => 'Khác',
                'key' => 'other',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            Ecommerce::query()->create($arrayInsert);
        }
    }
}
