<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    public function __construct()
    {

    }

    public function initDefault(Request $request)
    {
        $fundModel = \App::make('App\Models\Fund');

        $data = [
            ['name' => 'Vietcombank', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'Techcombank', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'BIDV', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'MB Bank', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'Tiền mặt', 'type_currency' => 1, 'unit_currency' => 1],

            ['name' => 'Công thương', 'type_currency' => 2, 'unit_currency' => 2],
            ['name' => 'Nhân dân', 'type_currency' => 2, 'unit_currency' => 2],

        ];

        $userOnline = \Auth::user();

        foreach ($data as $key => $item) {
            $fundDB = $fundModel->where('name', $item['name'])
                                ->where('organization_id', $userOnline->organization_id)
                                ->first();

            if (is_null($fundDB)) {
                $item['organization_id'] = $userOnline->organization_id;
                $fundModel->create($item);
            }
        }

        return resSuccess("OK");
    }
}

