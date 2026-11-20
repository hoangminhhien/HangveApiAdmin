<?php

namespace App\Services;

use App\Constants\PackageConstant;
use App\Jobs\OrderPackage\UpdateNameProductToDongDong;
use App\Models\Order;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Log;

class OneWarehouseService
{

    protected $client;

	public function __construct(Guzzle $client)
	{
		$this->client = $client;
	}

    public function forward($method, $uri, $data = [])
    {
        try {
            $domain = env('BASE_URL_DONGDONG');
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
                ],
            ];
            if($method == "GET"){
                $options = array_merge($options, ['query' => $data]);
            }
            if($method == "POST"){
                $options = array_merge($options, ['json' => $data]);
            }

			$response = $this->client->request($method, $domain.$uri, $options);
            $res = json_decode((string) $response->getBody(), true);
            return $res;
		} catch (\GuzzleHttp\Exception\ClientException $e) {
            \Log::error($e);
            return $e->getMessage();
		}
    }

    public function forwardToCn($method, $uri, $data = [])
    {
        $domain = env('URL_ONEWAREHOUSE');
        $options = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
        ];
        if($method == "GET"){
            $options = array_merge($options, ['query' => $data]);
        }
        if($method == "POST"){
            $options = array_merge($options, ['json' => $data]);
        }
        Log::info('data từ HV ' . json_encode($data));
        return $this->client->request($method, $domain.$uri, $options);
    }


    public function syncServiceOneWarehouse(array $packageIds, array $services)
    {
        $data = [
            'package_ids' => $packageIds,
        ];
        $data =  array_merge($data, $services);
        return $this->forward('POST', 'open/sync/pack-service', $data);
    }

    public function syncPackWithOrderOneWarehouse($orderPackage, $toSync = false, array $products = [])
    {
        $productIndexMap = collect($products)
            ->keyBy('id')
            ->map(fn($p) => $p['index'] ?? null);
        // Hàm này để đồng bộ kiện order sang bên kho dongdong
        $packType = 'order';
        $customer = $orderPackage->customer;
        $productDetails = [];
        if ($orderPackage->order_type == Order::class) {
            $orderRela = $orderPackage->orderPackageWithOrder;
            foreach ($orderRela->orderDetail as $orderDetail) {
                $classification = "";
                if (data_get($orderDetail, 'classification_zh_fast')) {
                    $classification = data_get($orderDetail, 'classification_zh_fast');
                } elseif (data_get($orderDetail, 'classification_zh')) {
                    $classification = data_get($orderDetail, 'classification_zh');
                } else {
                    $classification = data_get($orderDetail, 'classification');
                }
                array_push($productDetails, [
                    'order_detail_id' => data_get($orderDetail, 'id'),
                    'name' => data_get($orderDetail, 'name'),
                    'image' => data_get($orderDetail, 'image'),
                    'link' => data_get($orderDetail, 'link'),
                    'classification' => $classification,
                    'quantity' => data_get($orderDetail, 'quantity'),
                    'note_china' => data_get($orderDetail, 'note_china'),
                    'product_index' => $productIndexMap[$orderDetail->id] ?? null,
                ]);
            }
        }
        $data = [
            'pack_id' => $orderPackage->id, // ID bên HV
            'pack_code' => $orderPackage->bill_code, // Mã vận đơn
            'pack_type' => $packType, // Loại hàng,
            'warehouse_id' => $orderPackage->warehouse_id, // Kho trả hàng
            'order_code' => $orderPackage->order_code, // Mã đơn hàng
            'order_id' => $orderPackage->order_id,
            'customer_id' => data_get($customer, 'id'),
            'customer_code' => data_get($customer, 'code'),
            'customer_name' => data_get($customer, 'name'),
            'delivery_type' => $orderPackage->delivery_type, // Hình thức vận chuyển
            'is_inspection' => (int)$orderPackage->is_inspection,
            'is_woodworking' => (int)$orderPackage->is_woodworking,
            'is_shock_proof' => (int)$orderPackage->is_shock_proof,
            'is_advanced_inspection' => $orderPackage->is_advanced_inspection == 2 ? 1 : 0,
            'is_insurance' => 0,
            'staff_order_id' => $orderPackage->staff_order_id, // Nhân viên đặt hàng
            'staff_order' => data_get($orderPackage, 'staffOrder.name') ?? null,
            'staff_group' => data_get($orderPackage, 'staffGroup.name') ?? null,
            'staff_group_id' => $orderPackage->staff_group_id,
            'package_number' => $orderPackage->package_number, // Số kiện
            'product_details' => $productDetails, // Danh sách sản phẩm của kiện
            'company_id' => data_get($customer, 'organization_id'),
            'category_id' => $orderPackage->category_id,
            'pack_code_po' => $orderPackage->code_po,
            'height' => $orderPackage->height,
            'length' => $orderPackage->length,
            'width' => $orderPackage->width,
            'weight' => $orderPackage->weight,
            'weight_real' => $orderPackage->weight_real,
            'to_sync' => $toSync,
        ];
        $response = $this->forward('POST', 'open/sync/pack', $data);
        if (data_get($response, 'code') == 200) {
            $orderPackage->code_warehouse = data_get($response, 'data.code_warehouse');
            $orderPackage->status = PackageConstant::STATUS_ON_THE_WAY_CHINA;
            $orderPackage->save();
        }

        dispatch(new UpdateNameProductToDongDong($productDetails))->onQueue('export-1800');
    }

    public function syncOneWarehouse($orderPackage)
    {
        $data = [
            'origin_code'           => $orderPackage->bill_code, // Mã vận đơn
        ];

        return $this->forwardToCn('POST', '/api/admin/webhook/update-info-pack-from-hv', $data);
    }

    public function syncPackManualOneWarehouse($orderPackage)
    {
        // Hàm này để đồng bộ kiện tạo thủ công ở màn tạo kiện sang bên kho dongdong
        $packType = 'unconfirmed';
        if(data_get($orderPackage, 'order_type') == Order::class) {
            $packType = 'order';
        }
        if(data_get($orderPackage, 'order_type') == Consignment::class) {
            $packType = 'consignment';
        }
        $customer = $orderPackage->customer;
        $data_package = [
            'pack_id'             => $orderPackage->id, // ID bên HV
            'pack_code'           => $orderPackage->bill_code, // Mã vận đơn
            'pack_type'           => $packType, // Loại hàng,
            'warehouse_id'        => $orderPackage->warehouse_id, // Kho trả hàng
            'order_code'          => $orderPackage->order_code, // Mã đơn hàng
            'customer_id'         => data_get($customer, 'id'),
            'customer_code'       => data_get($customer, 'code'),
            'customer_name'       => data_get($customer, 'name'),
            'staff_order_id'      => data_get($orderPackage, 'staff_order_id'), // Nhân viên đặt hàng
            'staff_order'         => data_get($orderPackage, 'staffOrder.name') ?? null,
            'china_shipping_cost' => data_get($orderPackage, 'china_shipping_cost'),
            'category_id'         => data_get($orderPackage, 'category_id'),
            'volumn'              => data_get($orderPackage, 'volume'),
            'weight'              => data_get($orderPackage, 'weight'),
        ];
        $response = $this->forward('POST', 'open/sync/pack-manual', $data_package);
        if(data_get($response, 'code') == 200) {
            $orderPackage->code_warehouse = data_get($response, 'data.code_warehouse');
            $orderPackage->status = PackageConstant::STATUS_ON_THE_WAY_CHINA;
            $orderPackage->save();
        }
    }
}
