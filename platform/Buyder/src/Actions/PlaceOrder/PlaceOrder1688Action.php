<?php

namespace ThuongDo\Buyder\src\Actions\PlaceOrder;

use App\Constants\Buyder\OrderBuyderConstant;
use App\Events\PlaceOrder\PlaceOrderCreateLocalDataEvent;
use App\Models\OrderDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PlaceOrder1688Action extends BaseAction
{
    /**
     * Đặt hàng
     * Các bước thực hiện
     *   + Chuẩn bị data gửi qua Buyder để đặt hàng
     *   + Nhận response từ Buyder, thêm bản ghi vào 'order_buyders', 'order_buyder_subs' và thêm kiện
     *
     * @param array|null $params
     * @return array
     */
    public function handle(?array $params)
    {
        // trang nào gửi request lên
        $source = $params['source'];
        // dữ liệu đặt hàng
        $data = $params['data'];
        $listOrder = data_get($params, 'list_order', []);

        // danh sách order_details được user gửi lên
        $orderDetailIds = Arr::pluck($data, 'order_detail_id');
        $assignSupportIds = Arr::pluck($data, 'assign_support_id');
        $groupByColumn = $source == 'place_order_list' ? 'link' : 'mp_id';

        $orderDetailList = OrderDetail::query()
            ->with(['order', 'supplier', 'item'])
            ->whereIn('id', $orderDetailIds)->get()
            ->transform(function ($orderDetail, $index) use ($assignSupportIds, $data) {
                $orderDetail->offsetSet('assign_support_id', $assignSupportIds[$index]);
                $orderDetail->offsetSet(
                    'quantity_submit',
                    Arr::first($data, fn($d) => $d['order_detail_id'] == $orderDetail->id)['quantity']
                );
                $orderDetail->offsetSet('amount_cny', $orderDetail->unit_price_cny * $data[$index]['quantity']);

                $minOrderQuantity = data_get(
                    json_decode($orderDetail->item->buyder_data),
                    'minOrderQuantity',
                    1);
                $orderDetail->offsetSet('min_order_quantity', $minOrderQuantity);

                return $orderDetail;
            })->groupBy($groupByColumn);

        // lấy dữ liệu cần thiết để preview và đặt hàng
        $buyderInputData = $this->getBuyderInputData($orderDetailList, data_get($params, 'receiver'), $listOrder);

        // kiểm tra số lượng đặt hàng thực tế đã >= số lượng tối thiểu chưa
        $failedProds = $this->checkMinOrderQuantity($buyderInputData);

        return count($failedProds)
            ? resError('Các đơn sau chưa đủ số lượng tối thiểu: ' . implode(', ', $failedProds), 400)
            : $this->handlePlaceOrder($buyderInputData, $source, data_get($params, 'platform_id'));
    }

    /**
     * Chuẩn bị dữ liệu gửi sang Buyder để đặt hàng
     *
     * @param Collection $orderDetailList
     * @param array $mutualData
     * @return array < array {
     *   flow: string,
     *   message: string,
     *   addressParam: array {
     *     address: string, mobile: string, fullName: string,
     *     postCode: string, areaText: string, cityText: string, provinceText: string,
     *   },
     *   cargoParamList: array<array {offerId: int, specId: string, quantity: int}>
     * }>
     */
    private function getBuyderInputData(Collection $orderDetailList, array $submitReceiver, array $listOrder): array
    {
        $mutualData = [
            'orderSource' => OrderBuyderConstant::HANGVE,
            'addressParam' => [
                'address' => data_get($submitReceiver, 'address'),
                'mobile' => data_get($submitReceiver, 'mobile_phone'),
                'fullName' => data_get($submitReceiver, 'name'),
                'postCode' => data_get($submitReceiver, 'zip'),
                'areaText' => data_get($submitReceiver, 'state'),
                'cityText' => data_get($submitReceiver, 'city'),
                'provinceText' => data_get($submitReceiver, 'district'),
            ]
        ];

        $placeOrderData = $orderDetailList->map(function ($orderDetailItem) use ($mutualData, $orderDetailList, $listOrder) {
            $cargoParamList = $orderDetailItem->map(fn($orderDetail) => ([
                'localOrderId' => $orderDetail->order->id,
                'orderDetailId' => data_get($orderDetail, 'id'),
                'assignSupportId' => data_get($orderDetail, 'assign_support_id'),
                'offerId' => data_get($orderDetail, 'mp_id'),
                'specId' => data_get($orderDetail, 'mp_sku_id'),
                'quantity' => data_get($orderDetail, 'quantity_submit'),
            ]))->toArray();

            $mutualData = array_merge($mutualData, [
                'product_name' => data_get($orderDetailItem->first(), 'name')
            ]);
            $orderId = $cargoParamList[0]['localOrderId'];

            return array_merge($mutualData, [
                'cargoParamList' => $cargoParamList,
                'minOrderQuantity' => $orderDetailItem->first()->min_order_quantity,
                'realPlacedQuantity' => collect($cargoParamList)->sum('quantity'),
                'list_order' => array_filter($listOrder, function($a) use ($orderId) {
                    return $a['order_id'] == $orderId;
                })
            ]);
        });

        return array_values($placeOrderData->toArray());
    }

    /**
     * Kiểm tra đơn đặt hàng đã đảm bảo số sản phẩm tối thiểu chưa
     * Dựa vào dữ liệu gửi qua Buyder để đặt hàng 1688
     *
     * @param array $buyderInputData
     * @return array
     */
    private function checkMinOrderQuantity(array $buyderInputData): array
    {
        $failedProducts = [];

        Arr::each($buyderInputData, function ($data) use (&$failedProducts) {
            $minOrderQuantity = data_get($data, 'minOrderQuantity');
            if (data_get($data, 'realPlacedQuantity') < $minOrderQuantity) {
                $failedProducts[] = data_get($data, 'product_name') . " ({$minOrderQuantity} sản phẩm)";
            }
        });

        return $failedProducts;
    }

    /**
     * Bắn request sang Buyder để preview và đặt hàng
     *
     * @param array $buyderInputData
     * @param string $source
     * @return JsonResponse
     */
    private function handlePlaceOrder(array $placeOrderData, string $source, string $platformId): JsonResponse
    {
        try {
            $previewUrl = config('partner.buyder.domain') . config('partner.buyder_1688.order_create_preview');
            $placeOrderUrl = config('partner.buyder.domain') . config('partner.buyder_1688.order_create');

            $failedProductNames = [];

            // Hiện đang cho phép tạo nhiều đơn 1688 cùng lúc
            // Tạm thời không dùng transaction vì nếu đơn lỗi không phải đơn đầu tiên thì có thể gây lỗi dữ liệu
            // Nghĩa là có tạo đơn 1688 nhưng không lưu lại vào db của HangVe

            foreach ($placeOrderData as $buyderInputData) {
                // bắn request sang Buyder để xem trước đơn
                $previewOptions = array_merge($this->buyder1688Options, [
                    'json' => array_merge($buyderInputData, [
                        'userId' => $platformId,
                        'api_provider' => 'x_port'
                    ])
                ]);
                $previewResult = $this->serviceRequest->handlePost($previewUrl, $previewOptions);
                if (data_get($previewResult, 'code') != 200 && !is_null(data_get($previewResult, 'data'))) {
                    $failedProductNames[] = data_get($buyderInputData, 'product_name');
                    continue;
                }

                // bắn request sang Buyder để tạo đơn
                $placeOrderOptions = array_merge($this->buyder1688Options, [
                    'json' => array_merge($buyderInputData, [
                        // 'orderPreviewResuslt' do API trả về viết sai chính tả 'resuslt' chứ dev không viết sai
                        'flow' => $previewResult['data']['orderPreviewResuslt'][0]['flowFlag'],
                        'message' => 'message',
                        'api_provider' => 'x_port',
                        'userId' => $platformId,
                    ])
                ]);
                $placeOrderResult = $this->serviceRequest->handlePost($placeOrderUrl, $placeOrderOptions);
                // nếu tạo đơn hàng thành công thì tạo các bản ghi trong 'order_buyders'
                // rồi sau đó tạo bản ghi trong 'order_buyder_subs' và tạo kiện
                if (data_get($placeOrderResult, 'code') == 200) {
                    event(new PlaceOrderCreateLocalDataEvent(
                        $buyderInputData,
                        data_get($placeOrderResult, 'data', []),
                        $source,
                        BuyderServiceConstant::SOURCE_1688
                    ));
                } else {
                    $failedProductNames[] = data_get($buyderInputData, 'product_name');
                }
            }

            $responseMessage = count($failedProductNames)
                ? 'Các đơn không đặt hàng thành công: ' . implode(', ', $failedProductNames)
                : 'Đặt hàng thành công';

            return resSuccess($responseMessage);
        } catch (\Exception $e) {
            Log::error("Lỗi khi đặt hàng 1688: {$e->getMessage()}");
            return resError('Đặt hàng không thành công, vui lòng kiểm tra ID 1688 hoặc liên hệ kỹ thuật', 400);
        }
    }
}
