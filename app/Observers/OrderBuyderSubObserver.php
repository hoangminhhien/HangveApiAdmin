<?php

namespace App\Observers;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\OrderConstant;
use App\Models\AssignSupportOrder;
use App\Models\OrderBuyder;
use App\Models\SubOrderBuyder;
use App\Services\OrderPackageService;
use App\Traits\PlaceOrder\CreateLocalDataTrait;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class OrderBuyderSubObserver
{
    use GetPlatformStatusConstantNameTrait;
    use CreateLocalDataTrait;

    /**
     * Trigger khi một record của order_buyder_subs created
     * Nhiệm vụ
     *   + Lấy ra orders tương ứng với order_buyder_subs này
     *   + Lấy ra mọi order_details tương ứng với orders đó
     *   + Kiểm tra xem mọi order_details đã được đặt hàng hết chưa (hết số lượng)
     *     - Nếu chưa thì không làm gì
     *     - Nếu đã hết thì đổi orders.status sang OrderConstant::KEY_STATUS_WAIT_TO_PAY (Chờ thanh toán)
     *
     * @param SubOrderBuyder $orderBuyderSub
     * @return void
     */
    public function created(SubOrderBuyder $orderBuyderSub): void
    {
        $this->changeCorrespondingOrderStatus($orderBuyderSub);
    }

    /**
     * Trigger khi một record của order_buyder_subs updating
     * Nhiệm vụ: Cập nhật assign_support_orders.order_no tương ứng với order_buyder_subs nếu
     *   + Đang cập nhật order_buyder_subs.classification_zh
     *   + Soureable của order_buyder_subs là AssignSupportOrder
     *
     * @param SubOrderBuyder $orderBuyderSub
     * @return void
     */
    public function updating(SubOrderBuyder $orderBuyderSub): void
    {
        /*$orderBuyderSub->loadMissing(['sourceable', 'order']);*/

        // kiểm tra xem có phải update classification_zh không
        // nếu là update classification_zh và sourceable là AssignSupportOrder thì update sourceable.order_no
        /*$sourceable = $orderBuyderSub->sourceable;
        $originalZh = data_get($orderBuyderSub->getOriginal(), 'classification_zh');
        $newZh = data_get($orderBuyderSub, 'classification_zh');
        if ($originalZh != $newZh && $sourceable instanceof AssignSupportOrder) {
            $sourceable->update([
                'order_no' => $newZh
            ]);
        }*/
    }

    /**
     * Trigger khi một record của order_buyder_subs updated
     * Làm 2 nhiệm vụ
     *   + Nhiệm vụ 1: Kiểm tra có phải update logistic_number không, có thì tạo kiện
     *   + Nhiệm vụ 2: Kiểm tra có phải thay đổi 'close_reason' (hủy đặt hàng)
     *     - Kiểm tra và thay đổi trạng thái orders tương ứng nếu cần
     *     - Nếu mọi đơn con cùng cha của order_buyder_sub này đã bị hủy hết thì chủ động hủy đơn cha
     *
     * @param SubOrderBuyder $orderBuyderSub
     * @return void
     */
    public function updated(SubOrderBuyder $orderBuyderSub): void
    {
        // nhiệm vụ 1
        $orderBuyderId = data_get($orderBuyderSub, 'order_buyder_id');
        $originalLogisticCode = data_get($orderBuyderSub->getOriginal(), 'logistic_number');
        $newOriginalLogisticCode = data_get($orderBuyderSub, 'logistic_number');

        // tạo kiện tương ứng với order_buyder_subs vừa được update
        if (is_null($originalLogisticCode) && !is_null($newOriginalLogisticCode)) {
            $orderBuyderSub->loadMissing(['orderBuyder', 'order']);
            $order = $orderBuyderSub->order;
            $orderBuyder = $orderBuyderSub->orderBuyder;

            $dataBuyder = [
                'codePo' => data_get($orderBuyder, 'buyder_id'),
                'logisticCode' => $newOriginalLogisticCode,
                'purchaseOrderOuterId' => data_get($orderBuyder, 'purchase_order_outer_id')
            ];
            $packageData = $this->prepareDataToCreatePackage($orderBuyder, $dataBuyder);

            $packageService = app()->make(OrderPackageService::class);
            $packageService->storeByOrderId($packageData, $order);
        }

        // nhiệm vụ 2
        // nếu không phải hủy đơn thì không làm gì
        //if (is_null($orderBuyderSub->close_reason)) {
        //    return;
        //}

        // đầu tiên sẽ kiểm tra và thay đổi trạng thái của orders tương ứng nếu cần
        $this->changeCorrespondingOrderStatus($orderBuyderSub);

        // sau đó thay đổi trạng thái của order_buyder tương ứng nếu cần
        $orderBuyderSubs = SubOrderBuyder::query()
            ->where('order_buyder_id', $orderBuyderId)
            ->get();

        // tổng số đơn con cùng cha
        $numberSub = $orderBuyderSubs->count();

        // số đơn con cùng cha đã bị hủy
        $numberCanceledSub = $orderBuyderSubs->where('closed_reason', '!=', null)->count();

        // nếu số con đã hủy bằng tổng số con thì hủy luôn đơn cha
        if ($numberCanceledSub === $numberSub) {
            $canceledStatus = BuyderStatusConstant::ORDER_TRADE_CLOSED;

            OrderBuyder::query()
                ->find($orderBuyderId)
                ->update(['supplier_status' => $canceledStatus]);
        }
    }

    /**
     * Hàm này thay đổi trạng thái của orders (orders.status)
     *   + Nếu chưa đặt hàng hết mọi đơn con thì trạng thái là OrderConstant::KEY_STATUS_DEPOSITED (Đã đặt cọc) (1)
     *   + Nếu mọi đơn con được đặt hàng hết thì
     *     - Nếu có >= 1 đơn con chưa thanh toán thì tt là OrderConstant::KEY_STATUS_WAIT_TO_PAY (Chờ thanh toán) (2)
     *     - Nếu không thì tt là OrderConstant::KEY_STATUS_ORDERED (Đặt hàng) (3)
     *
     * @param SubOrderBuyder $orderBuyderSub
     * @return void
     */
    private function changeCorrespondingOrderStatus(SubOrderBuyder $orderBuyderSub): void
    {
        $orderBuyderSub->loadMissing(['order']);
        $order = $orderBuyderSub->order;
        $source = $orderBuyderSub->source;

        $order->loadMissing(['details']);
        $orderDetails = $order->details;

        // tổng số sp khách lên đơn của đơn hàng (orders) này
        $rawQuantity = $orderDetails->sum('quantity');

        // tổng số sp của các order_buyder_subs có tt khác 'đã hủy' của orders đó
        $subOrderBuyders = $order->subOrderBuyders;
        $placedQuantity = $subOrderBuyders->whereNotIn('buyder_status', [
            $this->getOrderBuyderSubsCancelStatus($source),
        ])->sum('quantity');

        // nếu tổng số sp khách lên đơn khác tổng số sp có tt khác 'đã hủy'
        // thì là case (1)
        if ($rawQuantity != $placedQuantity) {
            /*$order->update([
                'status' => OrderConstant::KEY_STATUS_WAIT_TO_PAY
            ]);*/
            /*return;*/
        }

        // tổng số sp của các đơn order_buyder_subs có tt 'chờ thanh toán' của orders đó
        $notPaidQuantity = $subOrderBuyders->whereIn('buyder_status', [
            $this->getOrderBuyderSubsInitStatus($source),
        ])->sum('quantity');
        // nếu tổng số sp của các đơn có tt 'chờ thanh toán' > 0 thì là case (2), chạy vào if
        if ($notPaidQuantity && $order->status != OrderConstant::KEY_STATUS_WAIT_TO_PAY) {
            /*$order->update([
                'status' => OrderConstant::KEY_STATUS_WAIT_TO_PAY
            ]);*/
            /*return;*/
        }

        // (3)
        /*if ($order->status != OrderConstant::KEY_STATUS_ORDERED) {
            $order->update([
                'status' => OrderConstant::KEY_STATUS_ORDERED
            ]);
        }*/
    }
}
