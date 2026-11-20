<?php

namespace App\Services\PlatformServices;

use App\Abstracts\AbstractBasePlatformService;
use App\Constants\StatusConstant;
use App\Interfaces\BasePlatformInterface;
use App\Models\Item;
use App\Services\GoogleTranslateService;
use App\Services\OrderService;
use Carbon\Carbon;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PlatformTaoBaoService extends AbstractBasePlatformService implements BasePlatformInterface
{
    protected string $url;
    public function __construct()
    {
        parent::__construct();
        $this->api_token = config('partner.buyder.tokenAuth');
        $this->uuId = config('partner.buyder_taobao.user_id');
        $this->url = config('partner.buyder.endpoint').'/open/taobao/';
        /*$this->url = 'http://127.0.0.1:8007/open/taobao/';*/
    }


    public function createOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'create-order';
        $url = $this->url.$method_search;
        $purchaseAmount = 0;
        if (!empty($request['orderLineList'])) {
            foreach ($request['orderLineList'] as &$list) {
                $skuId = $list['skuId'] ?? 0;
                $list['skuId'] = $skuId;
                $purchaseAmount += $list['purchaseAmount'];
                $list['price'] = $list['price'] * 100;
            }
            unset($list);
        }
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'sellerOrderNumber' => $this->generateRandom(),
            'receiver' => json_encode($request['receiver']) ?? '',
            'orderLineList' => !empty($request['orderLineList'])
                ? ($request['orderLineList']) : [],
            'purchaseAmount' => $request['purchaseAmount'] ?? $purchaseAmount * 100,
            'orderSource' => $request['orderSource'] ?? 'buyder',
        ];
        return $this->curl($params, $url, $method);
    }

    public function detailOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'order-detail';
        $url = $this->url.$method_search;
        $method = 'GET';
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'orderId' => $request['orderId'] ?? 0
        ];
        return $this->curl($params, $url, $method);
    }

    public function cancelOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'cancel-order';
        $url = $this->url.$method_search;
        $params = [
            'orderId' => $request['orderId'] ?? 0,
            'userId' => $request['userId'] ?? $this->uuId
        ];
        return $this->curl($params, $url, $method);
    }

    public function paymentOrderPlatform(array $request, string $method = 'POST')
    {
        $method_search = 'pay-order';
        $url = $this->url.$method_search;
        $params = [
            'orderId' => $request['orderIds'] ?? [],
            'userId' => $request['userId'] ?? $this->uuId,
        ];
        return $this->curl($params, $url, $method);
    }

    public function getProductPlatformDetail(array $request, $method = 'POST'): array
    {
        $data = [
            'itemId' => $request['itemId'] ?? '',
            'userId' => $request['userId'] ?? $this->uuId,
            'language' => $request['language'] ?? 'zh',
            'api_provider' => 'x_port',
            'not_translate' => 1, // Đánh dấu không dịch thuộc tính bên buyder
        ];
        (new OrderService())->cacheRedisForSearch();

        $method_search = 'get-item';
        $url = $this->url.$method_search;
        $method = 'GET';
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
            $item = Item::query()->where('num_iid', $results->itemId)
                ->where('source', 'taobao')->first();
            return [
                'code' => 200,
                'data' => $item
            ];
        } else {
            return [
                'code' => 500,
                'data' => [],
                'message' => $response->message ?? 'Thất bại'
            ];
        }
    }

    public function createOrUpdateProductDetail($response): array
    {
        $res = json_decode(json_encode($response), true);
        if (!empty($res['picUrls'])) {
            $res['picUrls'] = array_values(array_filter($res['picUrls']));
        }
        $pic = $res['picUrls'][0] ?? '';
        $skuList = $res['skuList'] ?? [];
        if (!$pic) {
            foreach ($skuList as $sku_list) {
                if ($sku_list['picUrl']) {
                    $pic = $sku_list['picUrl'];
                    break;
                }
            }
        }
        $itemId = $res['itemId'];
        $localItem = Item::query()->where('num_iid', $itemId)
            ->where('source', 'taobao')->first();
        $title = $res['title'] ?? '';
        $title_vi = $title;
        $seller_nick = $res['shopName'] ?? 'Không xác định';
        $seller_id = $res['shopId'] ?? 0;
        $seller_nick_vi = $seller_nick;
        if (!$localItem) {
            /*$seller_nick_vi = $seller_nick ? data_get(
                $this->translateSearchText([$seller_nick], 'vi'),
                '0.translatedText'
            ) : '';*/
            /*$title_vi = $title ? data_get(
                $this->translateSearchText([$title], 'vi'),
                '0.translatedText'
            ) : '';*/

            $itemData = [
                'num_iid' => $itemId,
                'key_search' => 'search_link',
                'source' => BuyderServiceConstant::SOURCE_TAOBAO,
                'pic' => $pic,
                'title' => $title,
                'title_vi' => $title_vi,
                'price' => $res['price'] / 100,
                'promotion_price' => $res['promotionPrice'] / 100,
                'sales' => (int)data_get($res, 'salesVolume'),
                'seller_id' => $seller_id,
                'seller_nick' => $seller_nick,
                'seller_nick_vi' => $seller_nick_vi,
                'user_type' => 0,
                'delivery_fee' => 0,
                'is_china_freeshiping' => 1,
                'detail_url' => data_get($res, 'itemUrl'),
                'data' => null,
                'last_time_sync' => 0,
                'buyder_data' => null,
                'buyder_data_last_time_sync' => null,
                'status' => 1
            ];
            $localItem = Item::query()->create($itemData);
        }
        /*if ($localItem->translate_prop) {
            Log::info('đã dịch tao bao');
            $translatedProps = json_decode($localItem->translate_prop, true);
        } else {
            Log::info('chưa dịch tao bao');
            $translatedProps = $this->getTranslatedProps($res);
        }*/
        $translatedProps = [];
        $newBuyderData = $this->handleTranslateViName($res, $translatedProps);
        $deliveryFee = data_get($newBuyderData, 'skuList.0.postFee', 0);

        // rồi update item
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $dataColumn = $this->getItemDataColumn($itemId, $res);
        $skuPropertiesColumn = !empty($newBuyderData)
            ? $this->getUpdatedSkuProperties($newBuyderData, $translatedProps) : [];
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

        $skuPropertiesColumn = json_encode($skuPropertiesColumn);
        $key_search = !empty($localItem->key_search) ? $localItem->key_search : 'search_link';
        $localItem->update([
            'seller_id' => data_get($res, 'shopId' , 0),
            'seller_nick' => $seller_nick,
            'seller_nick_vi' => $seller_nick_vi,
            'key_search' => $key_search,
            'delivery_fee' => $deliveryFee,
            'title' => $title,
            'title_vi' => $title_vi,
            'pic' => $pic,
            'detail_url' => $res['original_product_url'] ?? '',
            'is_china_freeshiping' => !$deliveryFee,
            'buyder_data' => $newBuyderData,
            'buyder_data_last_time_sync' => $now,
            'sku_properties' => $skuPropertiesColumn,
            'data' => json_encode($dataColumn),
            'translate_prop' => json_encode($translatedProps),
            'last_time_sync' => time(),
        ]);
        return [
            'data' => $dataColumn,
            'buyderData' => json_encode($newBuyderData),
            'skuProperties' => $skuPropertiesColumn,
        ];
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

    private function getUpdatedSkuProperties(array $newBuyderData, array $translatedProps): array
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

    private function getTranslatedPropValues(array $valueNames, array $translatedPropValues): array
    {
        return Arr::map($valueNames, fn ($valueName) => data_get($translatedPropValues, $valueName));
    }


    private function getItemDataColumn(string $platformId, array $buyderResult): array
    {
        return [
            'status' => [
                'requestAction' => 1,
                'msg' => 'success',
                'code' => 200,
                'execution_time' => 1,
            ],
            'item' => [
                'num_iid' => $platformId,
                'title' => data_get($buyderResult, 'title'),
                'total_sales' => null,
                'cat_id' => null,
                'detail_url' => data_get($buyderResult, 'original_product_url', ''),
                'price' => data_get($buyderResult, 'price', 0),
                'promotion_price' => data_get($buyderResult, 'promotionPrice', 0),
                'images' => data_get($buyderResult, 'picUrls'),
                'properties' => data_get($buyderResult, 'skuList')
            ]
        ];
    }

    private function handleTranslateViName(array $buyderResult, array $translatedProps): array
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

    private function getTranslatedProps(array $buyderResult, string $target = 'vi'): array
    {
        $properties = collect($buyderResult['skuList'])->pluck('properties')->flatten(1);
        $propNames = $properties->pluck('propName')->unique()->toArray();
        $valueNames = $properties->pluck('valueName')->unique()->toArray();
        $merge = array_merge($propNames, $valueNames);
        $merge = array_filter($merge);
        if (empty($merge)) {
            return [];
        }
        return $this->getMappedTranslated($merge, $target);
    }

    private function getMappedTranslated(array $input, string $target = 'vi'): array
    {
        /*$input = array_filter($input);
        if (empty($input)) {
            return [];
        }
        $rawTranslated = GoogleTranslateService::translateSearchText($input, $target);
        $mappedTranslated = [];
        foreach ($rawTranslated as $index => $data) {
            $mappedTranslated[data_get($input, $index)] = data_get($data, 'translatedText');
        }

        return $mappedTranslated;*/
        $input = array_filter($input);
        if (empty($input)) {
            return [];
        }
        $merges = array_chunk($input, 50);
        $rawTranslated = [];
        foreach ($merges as $merge) {
            $rawTranslated[] = GoogleTranslateService::translateSearchText($merge, $target);
        }
        $rawTranslated = array_merge(...$rawTranslated);
        $mappedTranslated = [];
        foreach ($rawTranslated as $index => $data) {
            $mappedTranslated[data_get($input, $index)] = data_get($data, 'translatedText');
        }
        return $mappedTranslated;
    }

    public function previewOrderPlatform(array $request, string $method = 'POST')
    {
        $url = config('partner.buyder.domain') . config('partner.buyder_taobao.order_create_preview');
        return $this->curl($request, $url, $method);
    }

    public function connectChat(array $request, string $method = 'POST')
    {
        $method_search = 'connect-im';
        $url = $this->url.$method_search;
        $params = [
            'loginName' => $request['loginName'] ?? '',
            'pageName' => $request['pageName'] ?? '',
            'password' => $request['password'] ?? '',
            'pageParamMap' => json_encode($request['pageParamMap'] ?? []),
            'userId' => $request['userId'] ?? $this->uuId,
        ];
        return $this->curl($params, $url, $method);
    }

    public function createManyOrderPlatform(array $request, string $method = 'POST')
    {
        // TODO: Implement createManyOrderPlatform() method.
    }

    public function getMpIdSkuIdTaoBao(array $request, string $method = 'POST')
    {
        $method_search = 'get-mpid-and-mpskuid';
        $url = $this->url.$method_search;
        $method = 'POST';
        $params = [
            'userId' => $request['userId'] ?? $this->uuId,
            'params' => json_encode($request['params'] ?? []),
        ];
        return $this->curl($params, $url, $method);
    }
}
