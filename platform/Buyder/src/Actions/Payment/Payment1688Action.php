<?php

namespace ThuongDo\Buyder\src\Actions\Payment;

use App\Constants\Buyder\BuyderStatusConstant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use Illuminate\Support\Facades\DB;
use ThuongDo\Buyder\src\Services\ServiceRequest;

class Payment1688Action extends BaseAction
{
    use PaymentActionTrait;

    /**
     * Thanh toán 1688
     *
     * @param array|null $params
     * @return void
     */
    public function handle(?array $params): JsonResponse
    {
        try {
            /** Đây là logic giả lập thanh toán thành công **/
            $this->handleChangeLocalStatuses(
                data_get($params, 'buyder_ids'),
                BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND,
                BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND
            );
            return resSuccess('Thanh toán thành công');

            /** Bên dưới là logic thanh toán chuẩn **/
            //DB::transaction(function () use ($params): void {
            //    $platformId = data_get($params, 'platform_id');
            //    $buyderIds = data_get($params, 'buyder_ids');
            //
            //    $paymentUrl = config('partner.buyder.domain') . config('partner.buyder_1688.pay-orders');
            //    $paymentOptions = array_merge($this->buyder1688Options, [
            //        'json' => [
            //            'orderIds' => $buyderIds,
            //            'userId' => $platformId,
            //        ]
            //    ]);
            //
            //    $serviceRequest = app()->make(ServiceRequest::class);
            //    $paymentResult = $serviceRequest->handlePost($paymentUrl, $paymentOptions);
            //
            //    if (data_get($paymentResult, 'code') != 200) {
            //        throw new \Exception(data_get($paymentResult, 'message'));
            //    }
            //
            //    // Cập nhật trạng thái đặt hàng trong order_buyders và order_buyder_subs
            //    $this->handleChangeLocalStatuses(
            //        $buyderIds,
            //        BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND,
            //        BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND
            //    );
            //});

            return resSuccess('Thanh toán thành công');
        } catch (\Exception $e) {
            $message = "Lỗi khi thanh toán 1688: " . $e->getMessage();
            Log::info($message);
            return resError($message, 400);
        }
    }
}
