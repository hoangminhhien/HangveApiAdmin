<?php

namespace ThuongDo\Buyder\src\Actions\Payment;

use App\Constants\Buyder\BuyderStatusConstant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use Illuminate\Support\Facades\DB;
use ThuongDo\Buyder\src\Services\ServiceRequest;

class PaymentTaobaoAction extends BaseAction
{
    use PaymentActionTrait;

    /**
     * Thanh toán Taobao
     *
     * @param array|null $params
     * @return void
     */
    public function handle(?array $params): JsonResponse
    {
        try {
            /** Đây là logic giả lập thanh toán thành công, mục đích test cronjob cập nhật mã vận đơn Taobao **/
            $this->handleChangeLocalStatuses(
                data_get($params, 'buyder_ids'),
                BuyderStatusConstant::BUYDER_ORDER_PAID,
                BuyderStatusConstant::SUB_BUYDER_ORDER_WAIT_SELLER_SEND_GOODS
            );
            return resSuccess('Thanh toán thành công');

            /** Bên dưới là logic thanh toán chuẩn **/
            //DB::transaction(function () use ($params): void {
            //    $buyderIds = data_get($params, 'buyder_ids');
            //
            //    $paymentUrl = config('partner.buyder.domain') . config('partner.buyder_taobao.pay-order');
            //    $paymentOptions = array_merge($this->buyderTaobaoOptions, [
            //        'json' => [
            //            'userId' => data_get($params, 'platform_id'),
            //            'orderId' => $buyderIds
            //        ]
            //    ]);
            //
            //    $serviceRequest = app()->make(ServiceRequest::class);
            //    $paymentResult = $serviceRequest->handlePost($paymentUrl, $paymentOptions);
            //
            //    if (data_get($paymentResult, 'code') == 200) {
            //        $this->handleChangeLocalStatuses(
            //            $buyderIds,
            //            BuyderStatusConstant::BUYDER_ORDER_PAID,
            //            BuyderStatusConstant::SUB_BUYDER_ORDER_WAIT_SELLER_SEND_GOODS
            //        );
            //    } else {
            //        throw new \Exception(data_get($paymentResult, 'message'));
            //    }
            //});
            //
            //return resSuccess('Thanh toán thành công');
        } catch (\Exception $e) {
            $message = "Lỗi khi thanh toán Taobao: " . $e->getMessage();
            Log::info($message);
            return resError($message, 400);
        }
    }
}
