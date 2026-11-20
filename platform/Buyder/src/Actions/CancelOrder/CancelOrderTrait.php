<?php

namespace ThuongDo\Buyder\src\Actions\CancelOrder;

use App\Constants\Buyder\BuyderCancelReasonConstant;
use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\PackageConstant;
use App\Models\AssignSupportOrder;
use App\Models\OrderBuyder;
use App\Models\OrderPackageCancel;
use App\Models\SubOrderBuyder;
use App\Services\BuyderService;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use Illuminate\Support\Arr;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;
use ThuongDo\Buyder\src\Services\ServiceRequest;

trait CancelOrderTrait
{
    use GetPlatformStatusConstantNameTrait;

    /**
     * 1688 là B2B chứ không phải B2C như Taobao
     *   + Khi đặt hàng 1688 sẽ không chia ra các đơn nhỏ mà chỉ có 1 đơn to
     *   + Vì vậy chỉ có mã đặt hàng của đơn to, và khi hủy cần dùng mã của đơn to
     * Data FE đẩy lên có thể bị trùng mã đặt hàng đơn to dẫn tới lỗi, vì vậy phải xử lý trùng bằng hàm này
     *
     * @param array $params
     * @return array
     */
    private function getParamsWithUniqueBuyderIds(array $params): array
    {
        $tempParams = [];
        $uniqueBuyderIds = [];

        Arr::each(data_get($params, 'data'), function ($data) use (&$tempParams, &$uniqueBuyderIds) {
            $buyderId = data_get($data, 'buyder_id');

            if (!in_array($buyderId, $uniqueBuyderIds)) {
                $uniqueBuyderIds[] = $buyderId;
                $tempParams[] = $data;
            }
        });

        data_set($params, 'data', $tempParams);

        return $params;
    }

    /**
     * Không cho hủy đặt hàng các đơn đã thanh toán (order_buyders.supplier_status > 0)
     *
     * @param array $cancelData
     * @return void
     * @throws \Exception
     */
    private function checkWhetherOrdersPaid(array $cancelData): void
    {
        $orderBuyders = OrderBuyder::query()
            ->whereIn('buyder_id', Arr::pluck($cancelData, 'buyder_id'))
            ->source1688()
            ->where1688PaidOrAfter()
            ->where1688NotCancel()
            ->count();

        if ($orderBuyders) {
            throw new \Exception('Đơn hàng đã thanh toán không được hủy');
        }
    }

    /**
     * Tiến hành hủy đặt hàng các đơn Taobao
     *
     * @param array $cancelData
     * @param string $cancelType
     * @param string $platformId
     * @return void
     */
    private function cancelOrdersTaobao(array $cancelData, string $cancelType): void
    {
        Arr::each($cancelData, function ($data) use ($cancelType) {
            // hủy đặt hàng
            $buyderService = app()->make(BuyderService::class);

            $rs = $buyderService->cancelOrder([
                'orderId' => data_get($data, 'buyder_id'),
                'subOrderIds' => data_get($data, 'buyder_sub_ids'),
                'cancelType' => (string)$cancelType
            ]);

            if (data_get($rs, 'code') != 200) {
                throw new \Exception(data_get($rs['data'], 'error'));
            }
        });
    }

    /**
     * Tiến hành hủy đặt hàng các đơn 1688
     *
     * @param array $cancelData
     * @param string $cancelType
     * @param string $platformId
     * @return void
     */
    private function cancelOrders1688(array $cancelData, string $cancelType, string $platformId): void
    {
        Arr::each($cancelData, function ($data) use ($cancelType, $platformId) {
            // hủy đặt hàng
            $serviceRequest = app()->make(ServiceRequest::class);

            $cancelUrl = config('partner.buyder.domain') . config('partner.buyder_1688.order_cancel');
            $cancelOptions = array_merge($this->buyder1688Options, [
                'json' => [
                    'webSite' => data_get($data, 'platform_source'),
                    'orderId' => data_get($data, 'buyder_id'),
                    'cancelReason' => BuyderCancelReasonConstant::getConstantValue(
                        'CANCEL_REASON_LIST',
                        $cancelType
                    ),
                    'remark' => 'string',
                    'userId' => $platformId,
                ]
            ]);
            $rawResult = $serviceRequest->handlePost($cancelUrl, $cancelOptions);

            if (data_get($rawResult, 'code') != 200) {
                throw new \Exception(data_get($rawResult['data'], 'error'));
            }
        });
    }

    /**
     * Cập nhật lý do hủy đặt hàng (order_buyder_subs.close_reason)
     * Sau đó cập nhật mã đặt hàng về null (assign_support_orders.order_no)
     * Và xóa kiện tương ứng với order_buyder_sub đó
     *
     * @param array $subOrderIds
     * @param string|int $cancelType
     * @return void
     */
    private function updateCloseReason(array $subOrderIds, $cancelType): void
    {
        SubOrderBuyder::query()
            ->with(['sourceable', 'orderPackage'])
            ->whereIn('buyder_sub_id', $subOrderIds)
            ->each(function ($subOrderBuyder) use ($cancelType) {
                // Set null mã đặt hàng 'order_no' của đơn hàng hỗ trợ
                $sourceable = $subOrderBuyder->sourceable;

                if ($sourceable instanceof AssignSupportOrder) {
                    $sourceable->loadMissing(['orderDetail']);
                    $image = $sourceable->orderDetail->image;

                    $sourceable->order_no = null;
                    $sourceable->save();
                } else {
                    $image = $sourceable->image;
                }

                // tạo bản ghi tương ứng trong bảng 'order_package_cancels', rồi soft delete 'order_package'
                $orderPackage = $subOrderBuyder->orderPackage;
                if ($orderPackage) {
                    OrderPackageCancel::query()->create([
                        'order_package_id' => data_get($orderPackage, 'id'),
                        'bill_code' => data_get($orderPackage, 'bill_code'),
                        'customer_id' => data_get($orderPackage, 'customer_id'),
                        'staff_id' => data_get($orderPackage, 'staff_quotation_id'),
                        'type_cancel_value' => PackageConstant::TYPE_CANCEL_ORDER_CANCEL,
                        'staff_management_id' => data_get($orderPackage, 'staff_quotation_id'),
                        'image' => $image,
                        'status' => PackageConstant::STATUS_CANCEL_DELETE
                    ]);
                    $orderPackage->forceDelete();
                }

                // update order_buyder_subs.close_reason
                $subOrderBuyder->update([
                    'close_reason' => $cancelType,
                    'buyder_status' => $this->getOrderBuyderSubsClosedStatus($subOrderBuyder->source)
                ]);
            });
    }
}
