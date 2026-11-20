<?php

namespace App\Services;

use App\Actions\OrderDetail\UpdateOrderDetailAction;
use App\Constants\TimeConstant;
use App\Events\SyncProductDetailEvent;
use App\Factories\BasePlatformFactory;
use App\Http\Requests\OrderDetail\UpdateClassificationRequest;
use App\Http\Resources\Order\ProductPaginateResource;
use App\Models\Category;
use App\Models\Complain;
use App\Models\Item;
use App\Models\OrderDetail;
use App\Models\OrderDetailHistory;
use App\Models\OrderDetailImage;
use App\Models\OrderHistory;
use App\Models\OrderSupplier;
use App\Models\Supplier;
use App\Services\PlatformServices\BasePlatformService;
use Illuminate\Http\JsonResponse;
use App\Constants\OrderConstant;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class OrderDetailService extends BaseService
{
    public function insertFromOrder(string $orderId, array $orderDetail, $detailFirst)
    {
        $unitPriceCny = $orderDetail['unit_price_cny'] ?? 0;
        $quantity = $orderDetail['quantity'] ?? 0;
        $this->removeKey($orderDetail);
        $orderDetail['created_at'] = date(TimeConstant::DATETIME);
        $orderDetail['order_id'] = $orderId;
        $orderDetail['amount_cny'] = $unitPriceCny * $quantity;
        $orderDetail['organization_id'] = $detailFirst->organization_id;
        $orderDetail['type_order'] = OrderConstant::TYPE_ORDER_MANUAL;
        $create = OrderDetail::query()->create($orderDetail);
        $job = new \App\Jobs\Order\SyncMpidSkuIdOrderManualJob([$create->id]);
        dispatch($job)->delay(now()->addSeconds(2))->onQueue('sync-data-report');
        return $create;
    }

    public function getSupplier($id): ProductPaginateResource
    {
        $query = $this->_model->query();
        $query = $query->with(['orderPackages', 'notes', 'orderNote']);
        $query = $query->with(['order' => function ($order) {
                $order->with(['packages']);
            }]);
        $query = $query->with(['supplier' => function ($supplier) use ($id) {
            $supplier->select('id', 'name', 'code');
            $supplier->with(['orderSupplier' => function ($orderSupplier) use ($id) {
                $orderSupplier->where('order_id', $id);
            }]);
        }]);
        $query = $query->with(['productItem' => function ($productItem) {
            $productItem->select('id', 'num_iid', 'sku_properties', 'title');
        }]);
        // $query = $query->withCount('orderHistory');
        $query->withCount([
            'orderHistory as order_history_count' => function ($q) {
                $q->where('history_type', '!=', OrderHistory::ORDER_HISTORY_NOTE_BUSINESS_TYPE);
            }
        ]);
        $query = $query->withCount('orderDetailNoteCustomer');
        $query = $query->with(['orderDetailHistoriyByPrice' => function($q) {
            return $q->orderBy('created_at', 'desc');
        }, 'orderDetailHistoriyByQuantity' => function($q) {
            return $q->orderBy('created_at', 'desc');
        }, 'orderDetailHistoryByClassification' => function($q) {
            return $q->orderBy('created_at', 'desc');
        }]);
        $query = $query->where('order_id', $id);
        $query = $query->orderBy('supplier_id', 'DESC');
        $query = $query->get();
        $query = $query->groupBy('supplier_id');
        return new ProductPaginateResource($query);
    }

    private function removeKey(array &$item)
    {
        $diffs = array_diff(array_keys($item), (new OrderDetail())->getFillable());

        if (!$diffs) {
            return;
        }
        $diffs = array_values($diffs);

        foreach ($diffs as $diff) {
            unset($item[$diff]);
        }
    }

    /**
     * @throws \Exception
     */
    public function getSkuList(string $orderDetailId)
    {
        // Log::info('getSkuList order detail start ' . $orderDetailId);
        $orderDetail = OrderDetail::query()->select('id', 'link')->where('id', $orderDetailId)->first();
        // Log::info('getSkuList order detail end ' . $orderDetailId);
        $link = $orderDetail->link ?? null;
        if (!$link) {
            return resError('Chưa có link sản phẩm');
        }
        $itemId = getPlatformIdFromUrl($link)
            ?? get1688IdFromRouteParam($link);
        $platform = getSourceFromProductLink($link);
        if (!$platform) {
            return [
                'code' => 500,
                'message' => 'Link sản phẩm phải là 1688 hoặc TaoBao'
            ];
        }
        if (!$itemId) {
            return [
                'code' => 500,
                'message' => 'ID sản phẩm không hợp lệ!'
            ];
        }
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($platform));
        $payload = [];
        if ($platform == '1688') {
            $payload['offerId'] = $itemId;
            $payload['is_not_sync_product_detail'] = true;
        }
        if ($platform == 'taobao') {
            $payload['itemId'] = $itemId;
            $payload['is_not_sync_product_detail'] = true;
        }
        // Log::info('getSkuList order detail  start getProductPlatformDetail' . $orderDetailId);
        $response = $service->getProductPlatformDetail($payload);
        \Log::info(json_encode($response));
        // Log::info('getSkuList order detail  end getProductPlatformDetail' . $orderDetailId);
        if ($response['code'] == 500) {
            return [
                'code' => 500,
                'message' => $response['message']
            ];
        }
        $item = $response['data'];
        $res = json_decode(json_encode($item), true);
        if ($platform == '1688') {
            $buyderData = $this->formatBuyderData1688($res, []);
            $getUpdatedSkuProperties = $this->getUpdatedSkuProperties1688($res, []);
            $sku_properties = data_get($getUpdatedSkuProperties, 'skuProperties');
        } else {
            $buyderData = $this->handleTranslateViNameTaoBao($res, []);
            $skuPropertiesColumn = !empty($buyderData)
                ? $this->getUpdatedSkuPropertiesTaoBao($buyderData, []) : [];
            $pic_urls = [];
            if (!empty($skuPropertiesColumn)) {
                if (empty($skuPropertiesColumn['pic_urls'])) {
                    $details = $skuPropertiesColumn['details'] ?? [];
                    foreach ($details as $detail) {
                        $pic_urls[] = $detail['pic_url'] ?? '';
                    }
                    $skuPropertiesColumn['pic_urls'] = $pic_urls;
                }
            }
            $sku_properties = $skuPropertiesColumn;
        }

        /*$buyderData = json_decode(data_get($item, 'buyder_data'));*/
        $mp_id = data_get($buyderData, 'mpId', '');
        if ($mp_id) {
            $orderDetail->mp_id = $mp_id;
            $orderDetail->save();
        }
        /*$sku_properties = json_decode($item->sku_properties ?? '');*/
        $sku_properties = json_decode(json_encode($sku_properties));
        $pic_urls = $sku_properties->pic_urls ?? [];
        if (!empty($pic_urls)) {
            $pic_urls = array_values(array_filter($pic_urls));
            $pic_url_format = [];
            if (sizeof($pic_urls) > 5) {
                foreach ($pic_urls as $pic_url) {
                    if (sizeof($pic_url_format) < 6) {
                        $pic_url_format[] = $pic_url;
                    }
                }
            }
            $sku_properties->pic_urls = $pic_url_format;
        }
        if (!empty($sku_properties) && empty($sku_properties->pic_urls)) {
            $details = $sku_properties->details ?? [];
            $pic_urls = [];
            foreach ($details as $detail) {
                if (sizeof($pic_urls) < 6) {
                    $pic_urls[] = $detail->pic_url ?? '';
                }

            }
            $sku_properties->pic_urls = $pic_urls;
        }
        if (!empty($sku_properties) && !empty($sku_properties->properties)) {
            foreach ($sku_properties->properties as &$property) {
                if (empty($property->prop_name)) {
                    $property->prop_name = 'Không có thuộc tính';
                    $property->prop_name_original = 'Không có thuộc tính';
                }
                $prop_values = $property->prop_values ?? [];
                $prop_values = array_filter($prop_values);
                if (empty($prop_values)) {
                    $property->prop_values = $property->prop_values_original;
                }
            }
            unset($property);
        }
        if (!empty($sku_properties) && !empty($sku_properties->details)) {
            foreach ($sku_properties->details as &$details) {
                $classification = $details->classification ?? '';
                if ($classification) {
                    $character = substr($classification, -1);
                    if ($character == ';') {
                        $details->classification = substr($classification, 0, -1);
                    }
                }
                $classification = $details->classification ?? '';
                if ($classification) {
                    $classification_array = explode(';', $classification);
                    $classification_array = array_filter($classification_array);
                    if (sizeof($classification_array) < 2) {
                        $classification = str_replace(';', '', $classification);
                        $details->classification = $classification;
                    }
                }
            }
            unset($details);
        }
        if (empty($sku_properties)) {
            return [
                'code' => 500,
                'message' => 'Không có thuộc tính'
            ];
        }
        event(new SyncProductDetailEvent($platform, $item));
        return [
            'code' => 200,
            'message' => 'Thành công!',
            'data' => $sku_properties
        ];
    }

    private function getUpdatedSkuPropertiesTaoBao(array $newBuyderData, array $translatedProps): array
    {
        $skuList = $newBuyderData['skuList'];
        // lấy danh sách các thuộc tính
        $rawProperties = collect(Arr::flatten(
            Arr::pluck($skuList, 'properties'), 1)
        )->groupBy('propName');

        $formattedProperties = $rawProperties->map(function ($t, $index) use ($translatedProps) {
            $valueNames = array_values($t->pluck('valueNameVi')->unique()->toArray());
            $valueNamesCn = array_values($t->pluck('valueName')->unique()->toArray());
            return [
                'prop_name' => $t[0]['propNameVi'] ?? '',
                'prop_name_original' => $index,
                /*'prop_values' => $this->getTranslatedPropValues($valueNames, $valueNames),*/
                /*'prop_values' => $valueNames,*/
                'prop_values' => $valueNamesCn,
                'prop_values_original' => $valueNamesCn
            ];
        });

        // các thông tin liên quan của từng thuộc tính: số lượng, giá, phí ship nội địa TQ, ảnh
        $details = Arr::map($skuList, fn($sku) => ([
            'classification' => data_get($sku, 'properties') ?
                implode(';', Arr::pluck(data_get($sku, 'properties'), 'valueName'))
                : 'Không có thuộc tính',
            'classification_cn' => data_get($sku, 'properties') ?
                implode(';', Arr::pluck(data_get($sku, 'properties'), 'valueName'))
                : 'Không có thuộc tính',
            'quantity' => data_get($sku, 'quantity'),
            'price' => data_get($sku, 'price', 0),
            'price_real' => data_get($sku, 'promotionPrice', 0) / 100 ?? data_get($sku, 'price', 0)/100,
            'post_fee' => data_get($sku, 'postFee'),
            'pic_url' => data_get($sku, 'picUrl'),
            /*'mp_sku_id' => data_get($sku, 'mpSkuId'),*/
            'mp_sku_id' => data_get($sku, 'skuId'),
            'sku_id' => data_get($sku, 'skuId')
        ]));

        return [
            'pic_urls' => data_get($newBuyderData, 'picUrls'),
            'properties' => array_values($formattedProperties->toArray()),
            'details' => array_values($details)
        ];
    }

    private function formatBuyderData1688(array $newBuyderData, array $translatedPropNamesAndValues): array {
        $productSkuInfos = $newBuyderData['productSkuInfos'];
        $skuList = array_map(function ($sku) use ( $translatedPropNamesAndValues) {
            $price = $sku['price'] ?: $sku['jxhyPrice'];
            return [
                'price' => $price,
                'skuId' => $sku['skuId'],
                'picUrl' => data_get($sku, 'skuAttributes.0.skuImageUrl'),
                'status' => 'active',
                'mpSkuId' => $sku['specId'],
                'postFee' => 0,
                'quantity' => (int)$sku['amountOnSale'],
                'properties' => array_map(function ($property) use ($translatedPropNamesAndValues) {
                    return [
                        'propId' => $property['attributeId'],
                        'valueId' => $property['attributeId'],
                        'propName' => $property['attributeName'],
                        /*'valueName' => data_get($translatedPropNamesAndValues, $property['value']),*/
                        'valueName' => $property['valueTrans'],
                        'rawValueName' => $property['valueTrans'],
                        /*'translatedPropName' => data_get($translatedPropNamesAndValues, $property['attributeName']),*/
                        'translatedPropName' => $property['attributeNameTrans'],
                    ];
                }, $sku['skuAttributes']),
                'promotionPrice' => 0
            ];
        }, $productSkuInfos);

        return [
            "mpId" => $newBuyderData['offerId'],
            "price" => 0,
            "title" => $newBuyderData['subject'],
            "itemId" => null,
            "shopId" => null,
            "status" => $newBuyderData['status'],
            "picUrls" => data_get($newBuyderData, 'productImage.images'),
            "skuList" => $skuList,
            "itemType" => null,
            "quantity" => null,
            "minOrderQuantity" => $newBuyderData['minOrderQuantity'],
            "shopName" => $newBuyderData['shopName'] ?? 'Không xác định' ,
            "userNick" => $newBuyderData['sellerShopUrl'] ?? '',
            "categoryId" => $newBuyderData['categoryId'] ?? 0,
            "beginAmount" => 0,
            "description" => $newBuyderData['description'] ?? '',
            "productUnit" => null,
            "categoryName" => $newBuyderData['categoryName'] ?? '',
            "categoryPath" =>  $newBuyderData['categoryPath'] ?? '',
            "promotionPrice" => 0,
            "priceRangeList" => data_get($newBuyderData, 'productSaleInfo.priceRangeList')
        ];
    }
    private function getUpdatedSkuProperties1688(array $newBuyderData, array $translatedPropNamesAndValues): array
    {
        $skuList = $newBuyderData['productSkuInfos'];
        $picUrls = data_get($newBuyderData, 'productImage.images', null);
        $details = array_map(function ($item) use ($picUrls) {
            if (count($item['skuAttributes']) == 0) {
                $classification = "";
            } elseif (count($item['skuAttributes']) == 1) {
                $classification = implode(';', array_map(function ($attr) {
                    return $attr['value'];
                }, $item['skuAttributes']));
            } else {
                $classification = implode(';', array_map(function ($attr) {
                    return $attr['value'];
                }, $item['skuAttributes']));
            }
            $data = [];
            $pic_url = '';
            if(count($item['skuAttributes']) > 1) {
                foreach ($item['skuAttributes'] as $key => $attr) {
                    $pic_url = $item['skuAttributes'][$key]['skuImageUrl'];
                    if($pic_url) {
                        break;
                    }
                }
            } else if(count($item['skuAttributes']) == 1) {
                $pic_url = $item['skuAttributes'][0]['skuImageUrl'];
            }

            if ($classification) {
                $classification_array = explode(';', $classification);
                $classification_array = array_filter($classification_array);
                if (sizeof($classification_array) < 2) {
                    $classification = str_replace(';', '', $classification);
                }
            }
            $data['classification'] = $classification;
            $data['classification_cn'] = $classification;
            $data['quantity'] = $item['amountOnSale'];
            $data['price'] = $item['price'] ?: $item['jxhyPrice'];
            $data['price_real'] = $item['price'] ?: $item['jxhyPrice'];
            $data['post_fee'] = 0;
            $data['pic_url'] = $pic_url;
            $data['mp_sku_id'] = $item['specId'];
            return $data;

        }, $skuList);

        $rawProperties = [];
        foreach ($skuList as $item) {
            foreach ($item['skuAttributes'] as $attr) {
                $rawProperties[] = $attr;
            }
        }
        $result = [];
        foreach ($rawProperties as $key => $item) {
            $result[$item['attributeName']][$key] = $item['value'];
        }

        $rawPropValues = Arr::flatten($result, 1);
        $propValues = array_values(array_unique($rawPropValues));
        $propValues = array_chunk($propValues, 50);
        /* $translatedPropValues = [];
         foreach ($propValues as $propValue) {
             $translatedPropValues[] = $this->handleTranslatePropValues($propValue);
         }
         $translatedPropValues = array_merge(...$translatedPropValues);*/
        $properties = [];
        foreach ($result as $key => $re) {
            $valueNames = array_values(array_unique($re));
            $property = [];
            /*$property['prop_name'] = data_get($translatedPropNamesAndValues, $key);*/
            $property['prop_name'] = $key;
            $property['prop_name_original'] = $key;
            /*$property['prop_values'] = $this->getTranslatedPropValues($valueNames, $translatedPropNamesAndValues);*/
            $property['prop_values'] = $valueNames;
            $property['prop_values_original'] = $valueNames;
            $properties[] = $property;
        }

        return [
            'translatePropValues' => $propValues,
            'skuProperties' => [
                'pic_urls' => data_get($newBuyderData, 'productImage.images'),
                'properties' => $properties,
                'details' => $details
            ]
        ];
    }

    private function handleTranslateViNameTaoBao(array $buyderResult, array $translatedProps): array
    {
        $skuList = data_get($buyderResult, 'skuList');
        $buyderResult['skuList'] = Arr::map($skuList, function ($sku) use ($translatedProps) {
            $properties = '';
            if (data_get($sku, 'properties')) {
                $properties = Arr::map(data_get($sku, 'properties'), function ($property) use ($translatedProps) {
                    $valueName = data_get($property, 'valueName');
                    $valueDescOrigin = data_get($property, 'valueDescOrigin');
                    $normalizedName = normalize($valueName);
                    $normalizedDesc = normalize($valueDescOrigin);

                    $valueName = str_contains($normalizedDesc, $normalizedName)
                        ? $valueDescOrigin
                        : $valueName;
                    $propName = data_get($property, 'propName');
                    $valueNameTranslate = data_get($property, 'valueNameTranslate');
                    $propNameTranslate = data_get($property, 'propNameTranslate');
                    return [
                        'valueId' => data_get($property, 'valueId'),
                        'valueName' => $valueName,
                        /*'valueNameVi' => data_get($translatedProps, $valueName),*/
                        'valueNameVi' => $valueNameTranslate,
                        'propId' => data_get($property, 'propId'),
                        'propName' => $propName,
                        /*'propNameVi' => data_get($translatedProps, $propName),*/
                        'propNameVi' => $propNameTranslate,
                    ];
                });
            }
            return [
                'picUrl' => data_get($sku, 'picUrl'),
                'quantity' => data_get($sku, 'quantity'),
                'price' => data_get($sku, 'price'),
                'skuId' => data_get($sku, 'skuId'),
                'status' => data_get($sku, 'status'),
                'promotionPrice' => data_get($sku, 'promotionPrice'),
                'postFee' => data_get($sku, 'postFee'),
                'mpSkuId' => data_get($sku, 'mpSkuId'),
                'properties' => $properties,
            ];
        });
        return $buyderResult;
    }

    /**
     * Cập nhật nguồn đặt hàng của OrderDetail
     *
     * @param array $data
     * @return JsonResponse
     */
    public function updateSource(array $data): JsonResponse
    {
        $action = new UpdateOrderDetailAction();

        return $action->updateSource($data);
    }

    /**
     * Cập nhật link sản phẩm của OrderDetail
     *
     * @param array $data
     * @return JsonResponse
     */
    public function updateLink(array $data): JsonResponse
    {
        $action = new UpdateOrderDetailAction();

        // Sửa link thì cập nhật ecommerce của đơn hàng đó
        $orderDetailId = data_get($data, 'order_detail_id');
        if($orderDetailId){
            $orderDetail = OrderDetail::query()->find($orderDetailId);
            if($orderDetail){
                foreach(OrderConstant::ECOMMERCE as $key => $ecommerce){
                    $linkProduct = data_get($data, 'link');
                    if(strpos($linkProduct, $key)){
                        Order::query()
                            ->where('id', $orderDetail->order_id ?? 0)
                            ->update(['ecommerce' => $key]);
                        $data['ecommerce'] = $key;
                    }
                }
            }
        }
        return $action->updateLink($data);
    }

    public function updateLinkOrderDetail($data): array
    {
        $orderDetailId = data_get($data, 'order_detail_id');
        if ($orderDetailId) {
            $orderDetail = OrderDetail::query()->find($orderDetailId);
            if($orderDetail){
                foreach(OrderConstant::ECOMMERCE as $key => $ecommerce){
                    $linkProduct = data_get($data, 'link');
                    if(strpos($linkProduct, $key)){
                        Order::query()
                            ->where('id', $orderDetail->order_id ?? 0)
                            ->update(['ecommerce' => $key]);
                        $data['ecommerce'] = $key;
                    }
                }
            }
            $link = data_get($data, 'link');
            $orderDetailId = data_get($data, 'order_detail_id');
            $orderDetail = OrderDetail::query()->findOrFail($orderDetailId);
            if($link && $orderDetail->link != $link) {
                OrderDetailHistory::query()->create([
                    'order_detail_id' => $orderDetail->id,
                    'object' => OrderDetailHistory::LINK,
                    'value' => $link,
                    'value_old' => $orderDetail->link,
                    'user_id' => getCurrentUser()->id,
                ]);
            }
            $orderDetail->link = $link;
            $source = $orderDetail->source;
            $platformItemId = getPlatformItemId($link, $source);
            $item = Item::query()
                ->where('source', $source)
                ->firstWhere('num_iid', $platformItemId);
            if ($item && $item->buyder_data) {
                $res = $this->updateOrderDetailWithExistedItem($item, $orderDetail);
            } else {
                $res = $this->syncBuyderItem($link, $orderDetail);
            }
            return $res;
        }
        return [
            'code' => 500,
            'message' => 'Đơn không tồn tại!'
        ];
    }

    private function updateOrderDetailWithExistedItem(Item $item, OrderDetail $orderDetail): array
    {
        $buyderData = json_decode(data_get($item, 'buyder_data'));
        $name = $item->seller_nick_vi ?? $item->seller_nick;
        $name_exits = 'shop'.$item->seller_id ?? 0;
        $supplier = Supplier::query()
            ->firstWhere('name', $name_exits);
        if (!$supplier) {
            $supplier = Supplier::query()->withoutGlobalScopes()->create(
                [
                    'name' => !empty($name) ? $name : ' Không xác định',
                    'logo' => '',
                    'code' => data_get($item, 'seller_id'),
                    'organization_id' => getOrganization(),
                    'customer_id' => $orderDetail->order->customer_id ?? 0
                ]
            );
        }
        $category = Category::query()
            ->firstWhere('thuongdo_name_china', data_get($buyderData, 'categoryName'));
        $supplier_id = (int)data_get($supplier, 'id');
        $order_detail_link = $orderDetail->link ?? '';
        $skuId = getTaoBaoSkuIdFromRouteParam($order_detail_link);
        $order_detail_product_id = getPlatformIdFromUrl($order_detail_link)
            ?? get1688IdFromRouteParam($order_detail_link);
        $item_detail_link = $item->detail_url ?? '';
        $item_detail_product_id = getPlatformIdFromUrl($item_detail_link)
            ?? get1688IdFromRouteParam($item_detail_link);
        if ($item_detail_product_id == $order_detail_product_id) {
            $supplier_id = $orderDetail->supplier_id ?? 0;
        }
        $updateOrderDetailData = [
            'mp_id' => data_get($buyderData, 'mpId'),
            'name' => data_get($item, 'title_vi'),
            'link' => $item->detail_url ?? '',
            'image' => $item->pic ?? '',
            'supplier_id' => $supplier_id,
            'category_id' => data_get($category, 'id'),
        ];
        $skuList = $buyderData->skuList ?? [];
        $mp_sku_id = null;
        $classification = '';
        if (sizeof($skuList) == 1 && empty($skuList[0]->properties)) {
            $mp_sku_id = $skuList[0]->mpSkuId;
        }
        if (!empty($skuList)) {
            foreach ($skuList as $item) {
                if ($skuId && $item->skuId == $skuId) {
                    $mp_sku_id = $item->mpSkuId;
                    $properties = $item->properties ?? null;
                    if ($properties) {
                        $array_property = [];
                        foreach ($properties as $property) {
                            $array_property[] = $property->valueName;
                        }
                        $classification = implode(';', $array_property);
                    }
                    break;
                }
            }
        }
        if ($item_detail_link) {
            $orderDetail->link = $item_detail_link;
            $orderDetail->save();
        }
        $this->updateLocalOrderDetail($orderDetail, $updateOrderDetailData, $mp_sku_id, $classification);
        return [
            'code' => 200,
            'message' => 'Success!'
        ];
    }

    private function updateLocalOrderDetail(
        OrderDetail $orderDetail,
        array $data, $mp_sku_id = null,
                    $classification = null
    ): void
    {
        $order_supplier_id_old = $orderDetail->supplier_id ?? 0;
        if ($order_supplier_id_old != $data['supplier_id']) {
            OrderSupplier::query()
                ->where('order_id', $orderDetail->order_id)
                ->where('supplier_id', $order_supplier_id_old)
                ->update(['supplier_id' => $data['supplier_id']]);
        }
        $updateData = array_merge($data, [
            'mp_sku_id' => $mp_sku_id,
            'classification' => $classification
                ?: $orderDetail->classification
        ]);
        $orderDetail->update($updateData);
    }

    private function syncBuyderItem(string $link, ?OrderDetail $orderDetail): array
    {
        $product_id = getPlatformIdFromUrl($link) ?? get1688IdFromRouteParam($link);
        $skuId = getTaoBaoSkuIdFromRouteParam($link);
        $order_detail_link = $orderDetail->link ?? '';
        $order_detail_product_id = getPlatformIdFromUrl($order_detail_link)
            ?? get1688IdFromRouteParam($order_detail_link);
        $response = $this->getItemData($product_id, $orderDetail->source ?? '');
        if ($response && isset($response['code']) && $response['code'] != 200) {
            return $response;
        }
        $detailResult = $response['data'];
        $buyderData = json_decode(data_get($detailResult, 'buyder_data'));
        $name = $detailResult['seller_nick_vi'] ?? $detailResult['seller_nick'];
        $name_exits = '';
        $source = $orderDetail->source ?? '';
        if ($source == 'taobao') {
            $name_exits = 'shop'.$detailResult['seller_id'] ?? 0;
        }

        $supplier = Supplier::query()
            ->firstWhere('name', $name_exits);
        if (!$supplier) {
            $supplier = Supplier::query()->withoutGlobalScopes()->create(
                [
                    'name' => !empty($name) ? $name : ' Không xác định',
                    'logo' => '',
                    'code' => data_get($detailResult, 'seller_id'),
                    'organization_id' => getOrganization(),
                    'customer_id' => $orderDetail->order->customer_id ?? 0
                ]
            );
        }
        $category = Category::query()
            ->firstWhere('thuongdo_name_china', data_get($buyderData, 'categoryName'));
        $supplier_id = data_get($supplier, 'id');
        if ($product_id == $order_detail_product_id) {
            $supplier_id = $orderDetail->supplier_id ?? 0;
        }

        $updateOrderDetailData = [
            'mp_id' => data_get($buyderData, 'mpId'),
            'name' => data_get($detailResult, 'title_vi'),
            'link' => data_get($detailResult, 'detail_url'),
            'image' => data_get($detailResult, 'pic'),
            'supplier_id' => $supplier_id,
            'category_id' => data_get($category, 'id'),
        ];
        $skuList = $buyderData->skuList ?? [];
        $classification = '';
        $mp_sku_id = null;
        if (sizeof($skuList) == 1 && empty($skuList[0]->properties)) {
            $mp_sku_id = $skuList[0]->mpSkuId;
        }
        if (!empty($skuList)) {
            foreach ($skuList as $item) {
                if ($skuId && $item->skuId == $skuId) {
                    $mp_sku_id = $item->mpSkuId;
                    $properties = $item->properties ?? null;
                    if ($properties) {
                        $array_property = [];
                        foreach ($properties as $property) {
                            $array_property[] = $property->valueName;
                        }
                        $classification = implode(';', $array_property);
                    }
                    break;
                }
            }
        }
        $this->updateLocalOrderDetail($orderDetail, $updateOrderDetailData, $mp_sku_id, $classification);
        return [
            'code' => 200,
            'message' => 'Success!'
        ];
    }

    private function getItemData($product_id, string $source)
    {
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        $payload = [];
        if ($source == 'taobao') {
            $payload = [
                'itemId' => $product_id
            ];
        }
        if ($source == '1688') {
            $payload = [
                'offerId' => $product_id
            ];
        }
        // upload ảnh lên server của mình, và sửa key 'image' trong $result
        /*$uploadedImagePath = $this->uploadImage(
            'https://img.alicdn' . explode('alicdn', data_get($response, 'pic'))[1]
        );*/

        return $service->getProductPlatformDetail($payload);
    }

    public function updateClassification(array $data): JsonResponse
    {
        $action = new UpdateOrderDetailAction();

        return $action->updateClassification($data);
    }

    public function getHistories(int $orderDetailId)
    {
        $query = OrderDetailHistory::query();
        $query->with(['user' => function ($q) {
            $q->select('id', 'name');
        }]);
        $query->with(['customer' => function ($q) {
            $q->select('id', 'name');
        }]);

        $query = $query->where('order_detail_id', $orderDetailId);

        $object = request()->get('object', 'unit_price_cny');
        $query->where('object', $object);

        $query->orderBy('created_at', 'desc');

        return $query->get()->transform(function ($item) {
            return [
                'id' => $item->id,
                'order_detail_id' => $item->order_detail_id,
                'object' => $item->object,
                'value' => $item->value . ($item->object == 'unit_price_cny' ? ' ¥' : ''),
                'value_old' => $item->value_old . ($item->object == 'unit_price_cny' ? ' ¥' : ''),
                'user' => [
                    'id' =>  data_get($item->user, 'id'),
                    'name' => data_get($item->user, 'name'),
                ],
                'customer' => [
                    'id' =>  data_get($item->customer, 'id'),
                    'name' => data_get($item->customer, 'name'),
                ],
                'created_at' => date('d/m/Y H:i', strtotime($item->created_at)),
            ];
        });
    }

    public function insertComplainImage(array $params, Complain $complain): void
    {
        $detailImages = [];
        $details = [];
        $products = $params['products'];
        if (!empty($products)) {
            foreach ($products as $product) {
                $details[] = [
                    'order_detail_id' => $product['order_detail_id'],
                    'note' => $product['complain_note'] ?? null,
                    'complain_id' => $complain->id,
                    'order_package_id' => $product['order_package_id'],
                    'created_at' => now()
                ];
                if (isset($product['images'])) {

                    foreach ($product['images'] as $image) {
                        $detailImages[] = [
                            'order_detail_id' => $product['order_detail_id'],
                            'complain_id' => $complain->id,
                            'image' => $image,
                            'created_at' => now()
                        ];
                    }
                }
            }
            $complain->orderDetails()->sync($details);
            OrderDetailImage::query()->where(['order_detail_id' => $product['order_detail_id'], 'complain_id' => $complain->id])->delete();
            OrderDetailImage::query()->insert($detailImages);
        }

    }
}
