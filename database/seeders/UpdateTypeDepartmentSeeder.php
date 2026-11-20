<?php

namespace Database\Seeders;

use App\Constants\DepartmentConstant;
use App\Models\Department;
use Illuminate\Database\Seeder;

class UpdateTypeDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $listUpdate = [
            '1edc48e2-be79-660a-ac1b-00163e0109a7' => DepartmentConstant::TYPE_TAKE_CARE_CUSTOMER,
            '1edc48e2-be8d-68b2-90c4-00163e0109a7' => DepartmentConstant::TYPE_COMPLAIN,
            '1edce0ce-faee-69b2-9edb-00163e0109a7' => DepartmentConstant::TYPE_CONSULTING,
            '1edce0cf-46a2-6c50-a030-00163e0109a7' => DepartmentConstant::TYPE_ORDER,
            '1edce0cf-ebfc-61c4-bb38-00163e0109a7' => DepartmentConstant::TYPE_QUOTE,
            '1ee06e1b-c6ff-6a1a-b5e2-00163e0109a7' => DepartmentConstant::TYPE_BUSINESS,
            '1ee06e1b-c70b-6d88-b1ef-00163e0109a7' => DepartmentConstant::TYPE_TECHNOLOGY,
            '1ee06e1b-c714-6a5a-b7dc-00163e0109a7' => DepartmentConstant::TYPE_LOGISTICS,
            '1ee06e1b-c722-665a-bb0d-00163e0109a7' => DepartmentConstant::TYPE_ACCOUNTANT,
            '1ee06e1b-c72c-616e-8a8d-00163e0109a7' => DepartmentConstant::TYPE_HUMAN_RESOURCES,
            '1ee06e1b-c735-69ee-81f3-00163e0109a7' => DepartmentConstant::TYPE_DONG_HUNG_BRANCH,
            '1ee06e1b-c73f-64e4-be12-00163e0109a7' => DepartmentConstant::TYPE_MARKETING,
            '1ee06e1b-c751-6860-8447-00163e0109a7' => DepartmentConstant::TYPE_CONSULTING,
            '1ee06e1b-c75a-6546-b746-00163e0109a7' => DepartmentConstant::TYPE_TAKE_CARE,
            '1ee06e1b-c763-6362-bb5b-00163e0109a7' => DepartmentConstant::TYPE_INTERNATIONAL_TRADE,
            '1ee06e1b-c76c-605c-a942-00163e0109a7' => DepartmentConstant::TYPE_ORDER,
            '1ee06e1b-c775-627e-b128-00163e0109a7' => DepartmentConstant::TYPE_TRANSSHIPMENT,
            '1ee06e1b-c77e-6324-834f-00163e0109a7' => DepartmentConstant::TYPE_RETURNS,
            '1ee06e1b-c787-6276-8460-00163e0109a7' => DepartmentConstant::TYPE_GUANGZHOU_BRANCH,
        ];

        foreach ($listUpdate as $key => $item) {
            Department::query()->where("id", $key)->update(['type' => $item]);
        }
    }
}
