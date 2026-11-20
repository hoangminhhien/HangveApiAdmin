<?php

namespace App\Services\PlatformServices;

use App\Abstracts\AbstractBasePlatformService;
use App\Interfaces\BasePlatformInterface;
use App\Models\Item;
use App\Services\OrderService;
use Carbon\Carbon;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class Platform1688Service extends AbstractBasePlatformService implements BasePlatformInterface
{

    protected string $url;
    public function __construct()
    {
        parent::__construct();
        $this->api_token = config('partner.buyder.tokenAuth1688');
        $this->uuId = config('partner.buyder_1688.user_id');
        $this->url = config('partner.buyder.endpoint').'/open/1688/';
        /*$this->url = 'http://127.0.0.1:8007/open/1688/';*/
    }

    public function createOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'order-create';
        $url = $this->url.$method_search;
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'flow' => $request['flow'] ?? 'general',
            'message' => $request['message'] ?? 'message',
            'addressParam' => $request['addressParam'] ?? [],
            'cargoParamList' => $request['cargoParamList'] ?? []
        ];
        return $this->curl($params, $url, $method);
    }

    public function createManyOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'order-create-many';
        $url = $this->url.$method_search;
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'flow' => $request['flow'] ?? 'general',
            'message' => $request['message'] ?? 'message',
            'addressParam' => $request['addressParam'] ?? [],
            'cargoParamList' => $request['cargoParamList'] ?? []
        ];
        return $this->curl($params, $url, $method);
    }

    public function detailOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'order-detail';
        $url = $this->url.$method_search;
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'webSite' => '1688',
            'orderId' => $request['orderId'] ?? 0,
        ];
        return $this->curl($params, $url, $method);
    }

    public function cancelOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'order-cancel';
        $url = $this->url.$method_search;
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'orderId' => $request['orderId'] ?? '',
            'cancelReason' => $request['cancelReason'] ?? '',
            'webSite' => $request['webSite'] ?? '1688',
            'remark' => $request['remark'] ?? 'string'
        ];
        return $this->curl($params, $url, $method);
    }

    public function paymentOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'order-pay-arr';
        $url = $this->url.$method_search;
        $params = [
            'orderIds' => $request['orderIds'] ?? [],
            'userId' => $request['userId'] ?? $this->uuId,
        ];
        return $this->curl($params, $url, $method);
    }

    public function previewOrderPlatform(array $request, string $method = 'POST')
    {
        $url = config('partner.buyder.domain') . config('partner.buyder_1688.order_create_preview');
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'addressParam' => $request['addressParam'] ?? [],
            'cargoParamList' => $request['cargoParamList'] ?? []
        ];
        Log::info(json_encode($params, JSON_UNESCAPED_UNICODE));
        return $this->curl($params, $url, $method);
    }

    public function connectChat(array $request, string $method = 'POST')
    {
        $method_search = 'product-openuid-decrypt';
        $url = $this->url.$method_search;
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'offerId' => $request['offer_id'] ?? 0,
            'openUid' => $request['seller_id'] ?? 0
        ];
        return $this->curl($params, $url, $method);
    }

    public function getMpIdSkuIdTaoBao(array $request, string $method = 'POST')
    {
        // TODO: Implement getMpIdSkuIdTaoBao() method.
    }

    public function getProductPlatformDetail(array $request, string $method = 'POST'): array
    {
        $data = [
            'offerId' => $request['offerId'] ?? '',
            'country' => $request['country'] ?? 'vi',
            'outMemberId' => $request['out_member_id'] ?? '',
            'userId' => $request['userId'] ?? $this->uuId,
            'not_translate' => 1, // Đánh dấu không dịch thuộc tính bên buyder
        ];
        (new OrderService())->cacheRedisForSearch();
        $method_search = 'detail-item';
        $url = $this->url.$method_search;
        $response = $this->curl($data, $url, $method);
        if ($response && !empty($response->code) && $response->code == 200 && !empty($response->data)) {
            $results = $response->data;
            if (isset($request['is_not_sync_product_detail'])) {
                return [
                    'code' => 200,
                    'data' => $results
                ];
            }
            $this->createOrUpdateProductDetail($results);
            $item = Item::query()->where('num_iid', $request['offerId'])
                ->where('source', '1688')->first();
            return [
                'code' => 200,
                'data' => $item
            ];
        } else {
            return [
                'code' => 500,
                'data' => [],
                'message' => $response->message
            ];
        }
    }

    public function createOrUpdateProductDetail($response)
    {
        $res = json_decode(json_encode($response), true);
        $localItem = Item::query()->where('num_iid', $res['offerId'])
            ->where('source', '1688')->first();
        $deliveryFee = data_get($res, 'productShippingInfo.fee', 0);
        $title = $res['subject'] ?? '';
        $title_vi = $title;
        $seller_nick = $res['shopName'] ?? 'Không xác định';
        $seller_id = $res['sellerOpenId'] ?? 0;
        $seller_nick_vi = $seller_nick;
        if (!$localItem) {
            /*$seller_nick_vi = $seller_nick ? data_get(
                $this->translateSearchText([$seller_nick], 'vi'),
                '0.translatedText'
            ) : '';*/
            /*$title = $res['subject'] ?? '';
            $title_vi = $title ? data_get(
                $this->translateSearchText([$title], 'vi'),
                '0.translatedText'
            ) : '';*/
            $image = $res['productImage']['images'][0] ?? '';
            $price = $res['productSaleInfo']['priceRangeList'][0]['price'] ?? 0;
            $initValues = [
                'key_search' => 'search_link',
                'source' => BuyderServiceConstant::SOURCE_1688,
                'num_iid' => $res['offerId'],
                'pic' => $image,
                'title' => $title,
                'title_vi' => $title_vi,
                'price' => $price,
                'promotion_price' => $price,
                'sales' => 0,
                'seller_id' => $seller_id,
                'seller_nick' => $seller_nick,
                'seller_nick_vi' => $seller_nick_vi,
                'user_type' => 0,
                'delivery_fee' => 0,
                'is_china_freeshiping' => 1,
                'detail_url' => $res['original_product_url'] ?? "https://detail.1688.com/offer/{$res['offerId']}.html",
                'data' => null,
                'last_time_sync' => 0,
                'buyder_data' => null,
                'buyder_data_last_time_sync' => null,
                'status' => 1
            ];
            $localItem = Item::query()->create($initValues);
        }
        /*if ($localItem->translate_prop) {
            $translatedPropNamesAndValues = json_decode($localItem->translate_prop, true);
        } else {
            $translatedPropNamesAndValues = $this->getTranslatedPropNamesAndValues($res);
        }*/
        $translatedPropNamesAndValues = [];
        $getUpdatedSkuProperties = $this->getUpdatedSkuProperties($res, $translatedPropNamesAndValues);
        $skuProperties = data_get($getUpdatedSkuProperties, 'skuProperties');
        $formatData = json_encode($this->formatBuyderData($res, $localItem, $translatedPropNamesAndValues));
        $dataColumn = json_encode($this->formatData($res, $localItem));
        $key_search = !empty($localItem->key_search) ? $localItem->key_search : 'search_link';
        $localItem->update([
            'seller_id' => data_get($res, 'sellerOpenId'),
            'title' => $title,
            'title_vi' => $title_vi,
            'key_search' => $key_search,
            'seller_nick' => $seller_nick,
            'seller_nick_vi' => $seller_nick_vi,
            'delivery_fee' => $deliveryFee,
            'is_china_freeshiping' => !$deliveryFee,
            'translate_prop' => json_encode($translatedPropNamesAndValues),
            'data' => $dataColumn,
            'last_time_sync' => time(),
            'buyder_data' => $formatData,
            'buyder_data_last_time_sync' => \Illuminate\Support\Carbon::now()->format('Y-m-d H:i:s'),
            'sku_properties' => $skuProperties,
        ]);
        return $localItem;
    }

    private function translateSearchText(array $text, string $target = 'zh-CN'): array
    {
        $translateUrl = sprintf(
            config('services.gg-translate.endpoint_text'),
            config('services.gg-translate.key')
        );

        $params = [
            'q' => $text,
            'target' => $target
        ];
        $guzzle = new Guzzle();
        $rawResponse = $guzzle->post(
            $translateUrl,
            [ RequestOptions::JSON => $params ],
        )->getBody()->getContents();
        $response = data_get(json_decode($rawResponse, true), 'data');

        return data_get($response, 'translations') ?? [];
    }

    private function formatData(array $newData, $localItem): array
    {
        return [
            "status" => [
                "requestAction" => "1",
                "msg" => "success",
                "code" => "200",
                "execution_time" => "1.08"
            ],
            "item" => [
                "num_iid" => data_get($localItem, 'num_iid'),
                "title" => $newData['subject'],
                "total_sales" => null,
                "cat_id" => null,
                "detail_url" => data_get($localItem, 'detail_url'),
                "images" => !empty($newData['productImage']) ? $newData['productImage']['images'] : [],
                "price" => data_get($localItem, 'price'),
                "promotion_price" => data_get($localItem, 'promotion_price'),
                "properties" => array_map(function ($item) {
                    return [
                        "name" => $item['attributeName'],
                        "value" => $item['value']
                    ];
                }, $newData['productAttribute']),
            ],
        ];
    }

    private function getUpdatedSkuProperties(array $newBuyderData, array $translatedPropNamesAndValues): array
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
            // phần này lấy random ảnh là sai. Mới sửa lại như này
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

    private function formatBuyderData(array $newBuyderData, $localItem, array $translatedPropNamesAndValues): array {
        $productSkuInfos = $newBuyderData['productSkuInfos'];
        $skuList = array_map(function ($sku) use ($localItem, $translatedPropNamesAndValues) {
            $price = $sku['price'] ?: $sku['jxhyPrice'];
            return [
                'price' => $price ?: data_get($localItem, 'price', 0),
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
                'promotionPrice' => data_get($localItem, 'promotion_price', 0)
            ];
        }, $productSkuInfos);

        return [
            "mpId" => $newBuyderData['offerId'],
            "price" => data_get($localItem, 'price'),
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
            "promotionPrice" => data_get($localItem, 'promotion_price'),
            "priceRangeList" => data_get($newBuyderData, 'productSaleInfo.priceRangeList')
        ];
    }

    private function getTranslatedPropNamesAndValues(array $newBuyderData): array
    {
        $skuList = $newBuyderData['productSkuInfos'];
        array_map(function ($item) {
            if (count($item['skuAttributes']) == 0) {
                $classification = ";";
            } elseif (count($item['skuAttributes']) == 1) {
                $classification = implode(';', array_map(function ($attr) {
                        return $attr['value'];
                    }, $item['skuAttributes'])) . ";";
            } else {
                $classification = implode(';', array_map(function ($attr) {
                    return $attr['value'];
                }, $item['skuAttributes']));
            }
            $data = [];
            $data['classification'] = $classification;
            $data['quantity'] = $item['amountOnSale'];
            $data['price'] = $item['price'] ?: $item['jxhyPrice'];
            $data['post_fee'] = 0;
            $data['pic_url'] = $item['skuAttributes'][0]['skuImageUrl'];
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
        $rawPropNames = array_keys($result);
        $merges = array_merge($propValues, $rawPropNames);
        $merges = array_chunk($merges, 50);
        $data = [];
        foreach ($merges as $merge) {
            $data[] = $this->handleTranslatePropValues($merge);
        }
        return array_merge(...$data);
    }

    private function handleTranslatePropValues(array $propValues)
    {
        $encodedPropValues = implode("&q=", array_map("rawurlencode", $propValues));
        $translateUrl = sprintf(
            "https://www.googleapis.com/language/translate/v2?key=%s&q=%s&target=vi",
            config('services.gg-translate.key'),
            $encodedPropValues
        );
        $guzzle = new Guzzle();
        $rawTranslateResponse = $guzzle->request(
            'GET',
            $translateUrl,
            [ 'timeout' => 10, 'connect_timeout' => 10 ]
        );
        $translateResponse = json_decode(
            $rawTranslateResponse->getBody()->getContents(), true
        )['data']['translations'];
        $result = [];
        foreach ($translateResponse as $index => $china) {
            $result[data_get($propValues, $index)] = data_get($china, 'translatedText');
        }
        return $result;
    }

    private function getTranslatedPropValues(array $valueNames, array $translatedPropValues): array
    {
        return Arr::map($valueNames, fn ($valueName) => data_get($translatedPropValues, $valueName));
    }
}
