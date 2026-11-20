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

class GHTKService implements Carrier
{
    public const TYPE_ATTRIBUTE_ORDER = [
        'products' => [
            'name'   => 'string',
            'weight' => 'double'
        ],
        'order'    => [
            'id'            => 'string',
            'pick_name'     => 'string',
            'pick_money'    => 'integer',
            'pick_address'  => 'string',
            'pick_province' => 'string',
            'pick_district' => 'string',
            'pick_tel'      => 'string',
            'name'          => 'string',
            'address'       => 'string',
            'province'      => 'string',
            'district'      => 'string',
            'ward'          => 'string',
            'street'        => 'string',
            'hamlet'        => 'string',
            'tel'           => 'string',
            'value'         => 'integer'
        ]
    ];

    /**
     * @param array $array
     * @return float
     */
    public function getPrice(array $array): float
    {
        $url  = config('carrier.ghtk') . CarrierConstant::ENDPOINT_GHTK_PRICE;
        $body = $this->getParamsPrice($array);
        try {
            $response = Http::withHeaders($this->getHeaders())->get(
                $url . "?" . http_build_query($body)
            )->throw()->json();

            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, $response);

            if (!$response['success']) {
                return self::FAIL;
            }
            return $response['fee']['fee'];
        }
        catch (RequestException $e) {
            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, [$e->getMessage()]);
            return self::FAIL;
        }
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return (array)(new CarrierService())->getConfigByCarrier(CarrierConstant::GHTK);
    }

    /**
     * @param array $array
     * @return array|null
     */
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
        $weight = $packages->sum('weight');

        if (!$warehouse->province || !$warehouse->district || !$delivery->province || !$delivery->district) {
            return null;
        }

        return [
            "pick_province"  => $warehouse->province->name,
            "pick_district"  => $warehouse->district->name,
            "province"       => $delivery->province->name,
            "district"       => $delivery->district->name,
            "address"        => $delivery->address,
            "weight"         => ConvertHelper::kilogramToGram($weight),
            "transport"      => 'road',
            "deliver_option" => $array['delivery_type'] === 'fast' ? 'xteam' : 'none'
        ];
    }

    public function createOrder(array $array)
    {
        $url  = config('carrier.ghtk') . CarrierConstant::ENDPOINT_GHTK_CREATE_ORDER;
        $body = $this->getParamsCreateOrder($array);
        if (!$body) return -2;
        try {
            $response = Http::withHeaders($this->getHeaders())
                            ->post($url, $body)
                            ->throw()
                            ->json();

            (new CarrierService())->setActivity($body['customer_delivery_id'], $body, $response);

            if (!$response['success']) {
                Log::info($response);
                return self::FAIL;
            }

            $data = $response['order'];
            (new DeliveryService())->storeDeliveryGHTK($array['deliveryId'], $response, $body['shop_id']);
            
            (new DeliveryService())->updateCarrierInfo($array['deliveryId'], $data['label'], $data['fee']);


            return $response['order'];
        }
        catch (RequestException $e) {
            (new CarrierService())->setActivity($body['customer_delivery_id'], $body, [$e->getMessage()]);
            return self::FAIL;
        }
    }

    public function getParamsCreateOrder(array $array): ?array
    {
        $products           = [];
        $orderPackageDetail = collect();
        $sumValue           = 0;
        $convertWeight      = $array['convertWeight'];
        $delivery           = Delivery::find($array['deliveryId']);
        $warehouse          = Warehouse::where('code', $array['customer']['warehouse_code'])->first();
        $deiveryStore       = $warehouse->deliveryStores->where('type', CarrierConstant::GHTK)->first();
        $customerDelivery   = CustomerDelivery::with(['province', 'district', 'ward'])->find($delivery->customer_delivery_id);
        $pickMoney          = $delivery->payment === DeliveryConstant::PAYMENT_E_WALLET ? 0 : ($delivery->getAmountAttribute() - $delivery->discount_cost); // Phí CoD

        if ($array['packages']) {
            $arrPackageids       = collect($array['packages']['data'])->map(function ($orderPackage) {
                return $orderPackage['id'];
            })->toArray();
            $collectOrderPackage = OrderPackage::with('orderDetails')->whereIn('id', $arrPackageids)->get();
            $sumValue            = $collectOrderPackage->sum('order_cost') ?? 0;
            $orderPackageDetail  = $collectOrderPackage->map(function ($orderPackage) {
                return $orderPackage->orderDetails;
            });
        }

        $averageWeight = round($convertWeight / count($orderPackageDetail->flatten()), 2);
        $averageWeight = max($averageWeight, 0.01);
        if (count($orderPackageDetail)) {
            $products = $orderPackageDetail->flatten()->map(function ($detail) use ($averageWeight) {
                return [
                    'name'   => $detail->name,
                    'weight' => $averageWeight,
                ];
            });
        }

        $order = [
            'id'            => $array['deliveryId'] . '-' . time(), // Thêm thời gian để có thể tạo lại đơn hàng nhiều lần
            'pick_name'     => $array['customer']['name'],
            'pick_money'    => (int)ceil($pickMoney),
            'pick_address'  => $warehouse->address,
            'pick_province' => $warehouse->province->name ?? null,
            'pick_district' => $warehouse->district->name ?? null,
            'pick_tel'      => $warehouse->phone_number,
            'name'          => $delivery->receiver,
            'address'       => $delivery->address,
            'province'      => $customerDelivery->province->name,
            'district'      => $customerDelivery->district->name,
            'ward'          => $customerDelivery->ward->name,
            'street'        => '',
            'hamlet'        => 'Khác',
            'tel'           => $delivery->phone_number,
            'value'         => (int)ceil($sumValue),
            //'is_freeship'   => $array['payment'] === DeliveryConstant::PAYMENT_E_WALLET ? 0 : 1,
            'is_freeship'   => 0 // mặc đinh là khách trả tiền ship
        ];

        $flag = $this->validateOrderAttributes(['products' => $products->toArray(), 'order' => $order]);
        return $flag ? [
            'products'             => $products->toArray(),
            'order'                => $order,
            'shop_id'              => $deiveryStore->store_id,
            'customer_delivery_id' => $delivery->customer_delivery_id
        ] : null;
    }

    public function validateOrderAttributes(array $attributes = []): bool
    {
        $flag          = true;
        $products      = $attributes['products'] ?? null;
        $order         = $attributes['order'] ?? null;
        $attributeType = self::TYPE_ATTRIBUTE_ORDER;
        if (!$products || !$order) {
            Log::info('Không tồn tại products hoặc order');
            return false;
        }

        foreach ($products as $product) {
            foreach ($attributeType['products'] as $_key => $val) {
                $type = gettype($product[$_key]);
                if (!isset($product[$_key]) || $type !== $val) {
                    $flag = false;
                    Log::info('Validation', ['value' => $product[$_key], 'key' => $_key]);
                }
            }
        }

        foreach ($attributeType['order'] as $_key => $val) {
            $type = gettype($order[$_key]);
            if (!isset($order[$_key]) || $type !== $val) {
                $flag = false;
                Log::info('Validation', ['value' => $order[$_key], 'key' => $_key]);
            }
        }

        if ($attributes['order']['pick_tel'] == $attributes['order']['tel']) {
            $flag = false;
            Log::info('Validation', ['Số điện thoại đặt đơn trùng sđt nhận đơn']);
        }

        return $flag;
    }

    public function cancelOrder(array $array = [])
    {
        try {
            $url = config('carrier.ghtk').CarrierConstant::ENDPOINT_GHTk_CANCEL_ORDER.'/'.$array['carrier_order_id'];
            $header = $this->getHeaders();
            $response = Http::withHeaders($header)->post($url)->json();
            // return $response;
            if ($response['success'] !== true) {
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
