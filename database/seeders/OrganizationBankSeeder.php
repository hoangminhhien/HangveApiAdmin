<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\OrganizationBank;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationBankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $organization = Organization::where('email', 'thuongdo@gmail.com')->first();
        $id = data_get($organization, 'id');
        $data = [
            [
                // Agribank
                'bank_id' => Bank::query()->where('bank_id', "970405")->first()->id,
                'organization_id' => $id,
                'name' => 'NGO DUC LOI',
                'account_number' => '1500205183642',
            ],
            // Vietinbank
            [
                'bank_id' => Bank::query()->where('bank_id', "970415")->first()->id,
                'organization_id' => $id,
                'name' => 'NGO DUC LOI',
                'account_number' => '102878275204',
            ],
            // BIDV
            [
                'bank_id' => Bank::query()->where('bank_id', "970418")->first()->id,
                'organization_id' => $id,
                'name' => 'NGO DUC LOI',
                'account_number' => '1509853310',
            ],
            // Techcombank
            [
                'bank_id' => Bank::query()->where('bank_id', "970407")->first()->id,
                'organization_id' => $id,
                'name' => 'NGO DUC LOI',
                'account_number' => '19050028700014',
            ],
        ];
        foreach($data as $item) {
            OrganizationBank::firstOrCreate($item);
        }
    }
}
