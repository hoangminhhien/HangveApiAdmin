<?php


namespace App\Services\Carriers;

use App\Constants\CarrierConstant;
use App\Constants\DeliveryConstant;
use App\Helpers\ConvertHelper;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\OrderPackage;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GHNService implements Carrier
{

    public function getPrice(array $array): float
    {
        $url  = config('carrier.ghn') . CarrierConstant::ENDPOINT_GHN_PRICE;
        $body = $this->getParamsPrice($array);
        try {
            $response = Http::withHeaders($this->getHeaders())->get(
                $url . "?" . http_build_query($body)
            )->throw()->json();

            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, $response);

            if ($response['code'] !== 200) {
                return self::FAIL;
            }

            return $response['data']['service_fee'];
        }
        catch (RequestException $e) {
            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, [$e->getMessage()]);
            return self::FAIL;
        }
    }

    public function getHeaders(): array
    {
        return (array)(new CarrierService())->getConfigByCarrier(CarrierConstant::GHN);
    }

    public function getParamsPrice(array $array): ?array
    {
        [$packages, $warehouse, $delivery] = (new CarrierService())->getVariables(
            $array['warehouse_id'],
            $array['packages'],
            $array['customer_delivery_id']
        );

        /** @var Warehouse $warehouse */
        /** @var CustomerDelivery $delivery */
        /** @var Collection $packages */

        if (!$warehouse->province || !$warehouse->district || !$delivery->province || !$delivery->district) {
            return null;
        }

        return [
            "from_district_id" => $warehouse->district->ghn_id,
            "service_type_id"  => 2,
            "to_district_id"   => $delivery->district->ghn_id,
            "to_ward_code"     => $delivery->ward->code,
            "height"           => ConvertHelper::floatToInt((clone $packages)->max('height')),
            "length"           => ConvertHelper::floatToInt((clone $packages)->max('length')),
            "width"            => ConvertHelper::floatToInt((clone $packages)->max('width')),
            "weight"           => ConvertHelper::floatToInt((clone $packages)->sum('weight')),
            "insurance_value"  => ConvertHelper::floatToInt((clone $packages)->sum('order_cost')),
            "coupon"           => null
        ];
    }

    public function createOrder(array $array)
    {
        $url              = config('carrier.ghn') . CarrierConstant::ENDPOINT_GHN_CREATE_ORDER;
        $body             = $this->getParamsCreateOrder($array);
        $header           = $this->getHeaders();
        $header['ShopId'] = $body['shop_id'] ?? null;
        if (!$body) {
            return self::DATA_FORMAT_WRONG;
        }
        try {
            $response = Http::withHeaders($header)
                            ->post($url, $body)
                            ->json();

            (new CarrierService())->setActivity($body['customer_delivery_id'], $body, $response);

            if ($response['code'] !== 200) {
                return self::FAIL;
            }

            $data = $response['data'];
            (new DeliveryService())->updateCarrierInfo($array['deliveryId'], $data['order_code'], $data['total_fee']);

            (new DeliveryService())->storeDeliveryGHN($array['deliveryId'], $response, $body['shop_id']);

            return $response;
        }
        catch (\Exception $e) {
            Log::info($e->getMessage());
            (new CarrierService())->setActivity($body['customer_delivery_id'], $body, [$e->getMessage()]);
            return self::FAIL;
        }
    }

    public function getParamsCreateOrder(array $array): ?array
    {
        $products           = [];
        $orderPackageDetail = collect();
        $insurance_value    = 0;
        $delivery           = Delivery::find($array['deliveryId']);
        $warehouse          = Warehouse::where('code', $array['customer']['warehouse_code'])->first();
        $customerDelivery   = CustomerDelivery::with(['province', 'district', 'ward'])->find($delivery->customer_delivery_id);
        $cod_amount         = $delivery->payment === DeliveryConstant::PAYMENT_E_WALLET ? 0 : $delivery->getAmountAttribute() - $delivery->discount_cost; // Phí CoD
        $deiveryStore       = $warehouse->deliveryStores->where('type', CarrierConstant::GHN)->first();
        $availableService   = $this->getAvailableService($deiveryStore->store_id, $deiveryStore->district_id, $customerDelivery->district->ghn_id);

        if ($array['packages']) {
            $arrPackageids       = collect($array['packages']['data'])->map(function ($orderPackage) {
                return $orderPackage['id'];
            })->toArray();
            $collectOrderPackage = OrderPackage::with('orderDetails')->whereIn('id', $arrPackageids)->get();
            $insurance_value     = (int)ceil($collectOrderPackage->sum('order_cost'));
            $orderPackageDetail  = $collectOrderPackage->map(function ($orderPackage) {
                return $orderPackage->orderDetails;
            });
        }

        foreach ($orderPackageDetail->flatten() as $detail) {
            $products[] = [
                'name'     => $detail->name,
                'quantity' => $detail->quantity ?? 0,
            ];
        }

        $data = [
            'customer_delivery_id' => $delivery->customer_delivery_id,
            'payment_type_id'      => $array['payment'] === DeliveryConstant::PAYMENT_E_WALLET ? 1 : 2,
            'note'                 => $array['note'] ?? '',
            'from_name'            => $deiveryStore->name ?? '',
            'from_phone'           => $deiveryStore->phone ?? '',
            'from_address'         => $deiveryStore->address ?? '',
            'from_ward_name'       => '',
            'from_district_name'   => $warehouse->district->name ?? null,
            'from_province_name'   => $warehouse->province->name ?? null,
            'required_note'        => $array['required_note'] ?? CarrierConstant::GHN_NOTE_SHIPPING[2],
            'to_name'              => $customerDelivery->receiver ?? '',
            'to_phone'             => $customerDelivery->phone_number ?? '',
            'to_address'           => $customerDelivery->address ?? '',
            'to_ward_name'         => $customerDelivery->ward->name ?? '',
            'to_district_name'     => $customerDelivery->district->name ?? '',
            'to_province_name'     => $customerDelivery->province->name ?? '',
            'cod_amount'           => (int)ceil($cod_amount) ?? 0,
            'content'              => $array['delivery']->note ?? '',
            'deliver_station_id'   => $array['deliver_station_id'] ?? null,
            'coupon'               => $array['coupon'] ?? null,
            'pick_shift'           => $array['pick_shift'] ?? null,
            'pickup_time'          => strtotime($array['date']) ?? null,
            'service_id'           => $availableService[0]['service_id'] ?? 0,
            'service_type_id'      => $availableService[0]['service_type_id'] ?? 2,
            'pick_station_id'      => $array['pick_station_id'] ?? null,
            'weight'               => (int)($array['convertWeight'] * 1000),
            'height'               => (int)$array['sizeHeight'],
            'width'                => (int)$array['sizeWidth'],
            'length'               => (int)$array['sizeLength'],
            'insurance_value'      => $insurance_value,
            'items'                => $products,
            'shop_id'              => $deiveryStore->store_id,
        ];

        $flag = $this->validateOrderAttributes($data);
        return $flag ? $data : null;
    }

    public function validateOrderAttributes(array $attributes): bool
    {
        $flag        = true;
        $requiredKey = [
            'to_name', 'to_phone', 'to_address', 'to_ward_name', 'to_district_name', 'to_province_name', 'weight', 'length', 'width', 'height',
            'service_type_id', 'service_id', 'payment_type_id', 'required_note', 'items'
        ];

        foreach ($requiredKey as $value) {
            if (!isset($attributes[$value])) {
                $flag = false;
            }
            if (isset($attributes['items'])) {
                foreach ($attributes['items'] as $val) {
                    if (!$val['name'] || $val['name'] == '') {
                        $flag = false;
                    }
                    if (!$val['quantity'] || $val['quantity'] < 1) {
                        $flag = false;
                    }
                }
            }
        }

        return $flag;
    }

    public function getStoreInfo()
    {
        $url  = config('carrier.ghn') . CarrierConstant::ENDPOINT_GHN_GET_STORE;
        $body = [
            "offset"       => 0,
            "limit"        => 50,
            "client_phone" => ""
        ];

        try {
            $response = Http::withHeaders($this->getHeaders())->get(
                $url . "?" . http_build_query($body)
            )->throw()->json();

            if ($response['code'] !== 200) {
                return self::FAIL;
            }

//            if ($response['data']['shops'][0]['ward_code']) {
//                $response['data']['shops'][0]['ward'] = Ward::where('code', $response['data']['shops'][0]['ward_code'])->first()->toArray();
//            }
//
//            if ($response['data']['shops'][0]['district_id']) {
//                $response['data']['shops'][0]['district'] = District::where('ghn_id', $response['data']['shops'][0]['district_id'])->first()->toArray();
//                if ($response['data']['shops'][0]['district']['province_id']) {
//                    $response['data']['shops'][0]['province'] = Province::find('5c8c099c-e7ad-4351-8b09-d2df15f2a068')->toArray();
//                }
//            }

            return $response['data']['shops'];
        }
        catch (RequestException $e) {
            return self::FAIL;
        }
    }

    public function getAvailableService($shopId = null, $fromDistrict = null, $toDistrict = null)
    {
        $url  = config('carrier.ghn') . CarrierConstant::ENDPOINT_GHN_AVAILABLE_SERVICES;
        $body = [
            "shop_id"       => $shopId,
            "from_district" => $fromDistrict,
            "to_district"   => $toDistrict
        ];

        try {
            $response = Http::withHeaders($this->getHeaders())->get(
                $url . "?" . http_build_query($body)
            )->throw()->json();

            if ($response['code'] !== 200) {
                return self::FAIL;
            }

            return $response['data'];
        }
        catch (RequestException $e) {
            return self::FAIL;
        }
    }

    public function cancelOrder(array $array = [])
    {
        $url              = config('carrier.ghn') . CarrierConstant::ENDPOINT_GHN_CANCEL_ORDER;
        $header           = $this->getHeaders();
        $header['ShopId'] = $array['shop_id'];
        $delivery = Delivery::query()->find($array['delivery_id']);
        $data             = ["order_codes" => [$delivery->carrier_code] ?? []];
        try {
            $response = Http::withHeaders($header)->post($url, $data)->json();
            if ($response['code'] !== 200) {
                Log::info('Hủy đơn bên giao hàng không thành công', $response);
                return self::FAIL;
            }

            (new DeliveryService())->deleteDeliveryOrderStatus($array['delivery_id']);

            return $response;
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            return self::FAIL;
        }
    }
}
