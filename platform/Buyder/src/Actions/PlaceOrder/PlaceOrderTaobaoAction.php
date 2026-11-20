<?php

namespace ThuongDo\Buyder\src\Actions\PlaceOrder;

use App\Constants\Buyder\OrderBuyderConstant;
use App\Constants\CurrencyConstant;
use App\Events\PlaceOrder\PlaceOrderCreateLocalDataEvent;
use App\Models\OrderDetail;
use App\Services\BuyderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PlaceOrderTaobaoAction extends BaseAction
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
    public function handle(?array $params): JsonResponse
    {
        // trang nào gửi request lên
        $source = $params['source'];
        // dữ liệu đặt hàng
        $data = $params['data'];
        // id đặt hàng Taobao
        $platformId = data_get($params, 'platform_id');
        // delay cập nhật mã vận đơn sau bao lâu
        $logisticDelayTime = data_get($params, 'delay_time');


        $orderDetailIds = Arr::pluck($data, 'order_detail_id');
        $assignSupportIds = Arr::pluck($data, 'assign_support_id');
        $orderDetailList = OrderDetail::query()
            ->with(['order', 'supplier'])
            ->whereIn('id', $orderDetailIds)->get()
            ->transform(function ($item, $index) use ($assignSupportIds, $data) {
                $item->offsetSet('assign_support_id', $assignSupportIds[$index]);
                $item->offsetSet(
                    'quantity_submit',
                    Arr::first($data, fn($d) => $d['order_detail_id'] == $item->id)['quantity']
                );
                $item->offsetSet('amount_cny', $item->unit_price_cny * $data[$index]['quantity']);
                return $item;
            });

        // các dữ liệu chung của payload đặt hàng
        $mutualData = [
            'orderSource' => OrderBuyderConstant::HANGVE,
            'receiver' => $params['receiver'],
            'userId' => $platformId,
            'delayTime' => $logisticDelayTime,
            'list_order' => $params['list_order']
        ];
        $rawBuyderInputData = $this->getBuyderInputData($orderDetailList, $mutualData);
        $buyderInputData = $this->checkCoincidenceItemSku($rawBuyderInputData);

        if ($buyderInputData instanceof JsonResponse) {
            return $buyderInputData;
        }
        DB::beginTransaction();
        try {
            $buyderService = app()->make(BuyderService::class);
            if (!empty($buyderInputData['orderLineList'])) {
                $buyderInputData['orderLineList'] = json_encode($buyderInputData['orderLineList']);
            }
            if (!empty($buyderInputData['receiver'])) {
                $buyderInputData['receiver'] = json_encode($buyderInputData['receiver']);
            }
            $createBuyder = $buyderService->createOrder($buyderInputData);
            // nếu tạo đơn hàng thành công thì tạo các bản ghi trong 'order_buyders'
            // rồi sau đó tạo bản ghi trong 'order_buyder_subs' và tạo kiện
            if (data_get($createBuyder, 'code') == 200) {
                event(new PlaceOrderCreateLocalDataEvent(
                    $buyderInputData,
                    data_get($createBuyder, 'data', []),
                    $source,
                    BuyderServiceConstant::SOURCE_TAOBAO
                ));
            } else {
                return resError(data_get($createBuyder, 'message'));
            }
            DB::commit();
            return resSuccess('Đặt hàng thành công');
        } catch (\Exception $e) {
            Log::error("Lỗi khi đặt hàng Taobao: {$e}");
            DB::rollBack();
            return resError($e->getMessage(), 400);
        }
    }

    /**
     * Kiểm tra trùng sản phẩm khi nhân viên đặt hàng
     * Không cho đặt cùng lúc nhiều đơn khác nhau mà lại cùng sản phẩm (itemId và skuId)
     *
     * @param array $rawBuyderInputData
     * @return array|JsonResponse
     */
    private function checkCoincidenceItemSku(array $rawBuyderInputData)
    {
        $orderLineList = data_get($rawBuyderInputData, 'orderLineList');
        $check = [];

        foreach ($orderLineList as $data) {
            $checkExist = Arr::find($check, fn ($c) => (
                data_get($c, 'itemId') == data_get($data, 'itemId')
                && data_get($c, 'skuId') == data_get($data, 'skuId')
            ));

            if (is_null($checkExist)) {
                $check[] = $data;
            }
        }

        return count($orderLineList) == count($check)
            ? $rawBuyderInputData
            : resError('Không được đặt hàng các đơn trùng sản phẩm', 400);
    }

    /**
     * Chuẩn bị dữ liệu gửi sang Buyder để đặt hàng
     *
     * @param Collection $orderDetailList
     * @param array $mutualData
     * @return Collection
     */
    private function getBuyderInputData(Collection $orderDetailList, array $mutualData): array
    {
        $purchaseAmount = 0;
        $orderLineList = $orderDetailList->map(function ($orderDetail, $index) use (&$purchaseAmount) {
            $purchaseAmount += $orderDetail->amount_cny;

            return [
                'localOrderId' => $orderDetail->order->id,
                'orderDetailId' => data_get($orderDetail, 'id'),
                'assignSupportId' => data_get($orderDetail, 'assign_support_id'),
                'itemId' => data_get($orderDetail, 'mp_id'),
                'skuId' => data_get($orderDetail, 'mp_sku_id'),
                'orderLineNo' => ++$index,
                'quantity' => data_get($orderDetail, 'quantity_submit'),
                'price' => data_get($orderDetail, 'unit_price_cny'),
                'currency' => CurrencyConstant::CNY,
            ];
        })->toArray();

        return array_merge($mutualData, [
            'orderLineList' => $orderLineList,
            'purchaseAmount' => (string) round($purchaseAmount * 100, 2),
            'sellerOrderNumber' => data_get($orderDetailList[0]->supplier, 'code'),
        ]);
    }
}
