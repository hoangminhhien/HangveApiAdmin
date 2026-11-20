<?php

namespace App\Traits\OrderDetail;

use App\Factories\BasePlatformFactory;
use App\Models\Category;
use App\Models\Item;
use App\Models\OrderDetail;
use App\Models\OrderSupplier;
use App\Models\Supplier;
use App\Services\MediaService;
use App\Services\PlatformServices\BasePlatformService;
use Exception;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait UpdateOrderDetailTrait
{
    /**
     * @throws BindingResolutionException
     */
    private function updateOrderDetailWithExistedItem(Item $item, OrderDetail $orderDetail): string
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
            'name' => data_get($item, 'title'),
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
        // cập nhật OrderDetail
        $this->updateLocalOrderDetail($orderDetail, $updateOrderDetailData, $mp_sku_id, $classification);

        // rồi return items.sku_properties
        return $item;
    }

    /**
     * @throws Exception
     */
    private function syncBuyderItem(string $link, ?OrderDetail $orderDetail): string
    {
        $product_id = getPlatformIdFromUrl($link) ?? get1688IdFromRouteParam($link);
        $skuId = getTaoBaoSkuIdFromRouteParam($link);
        $order_detail_link = $orderDetail->link ?? '';
        $order_detail_product_id = getPlatformIdFromUrl($order_detail_link)
            ?? get1688IdFromRouteParam($order_detail_link);
        $response = $this->getItemData($product_id, $orderDetail->source ?? '');
        if ($response && isset($response['code']) && $response['code'] != 200) {
            return $response['message'];
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
            'name' => data_get($detailResult, 'title'),
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
        return data_get($detailResult, 'sku_properties');
    }

    /**
     * @throws Exception
     */
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

    /**
     * Upload ảnh gốc của sản phẩm lên server của mình
     * Lưu lại, rồi return path trong server của mình
     *
     * @param string $rawPath
     * @return string
     * @throws BindingResolutionException
     */
    private function uploadImage(string $rawPath): string
    {
        $imageContent = Http::get($rawPath)->body();

        // tạo instance của UploadedFile để truyền vào service
        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'uploaded_file');
        file_put_contents($temporaryFilePath, $imageContent);

        $uploadedFile = new UploadedFile(
            $temporaryFilePath,
            'image.jpg',
            'image/jpeg',
            null,
            true // set $test to true to prevent moving the file to the final destination
        );

        $service = app()->make(MediaService::class);
        $rawUploadResult = $service->upload([$uploadedFile])->getContent();
        $uploadResult = json_decode($rawUploadResult);

        return data_get(($uploadResult->data)[0], 'path');
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
}
