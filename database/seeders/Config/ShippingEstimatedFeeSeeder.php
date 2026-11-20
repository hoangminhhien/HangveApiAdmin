<?php

namespace Database\Seeders\Config;

use App\Models\Category;
use App\Models\ConfigShippingEstimatedFee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ShippingEstimatedFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Log::info('Bắt đầu seeder config_shipping_estimated_fee');
        $categories = Category::query()->select(['id', 'name'])->get();

        $data = $categories->map(fn ($cat, $catIndex) => ([
            'id' => $catIndex + 1,
            'category_code' => 'CAT_' . ($catIndex + 1),
            'category_id' => data_get($cat, 'id'),
            'category_name' => mb_ucfirst(data_get($cat, 'name')),
        ]));

        foreach ($data as $dat) {
            ConfigShippingEstimatedFee::query()->create($dat);
        }

        Log::info('Kết thúc seeder config_shipping_estimated_fee');
    }
}
