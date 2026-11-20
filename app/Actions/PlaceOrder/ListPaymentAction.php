<?php

namespace App\Actions\PlaceOrder;

use App\Constants\ResourceConstant;
use App\Models\OrderBuyder;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use App\Traits\PlaceOrder\ListPaymentTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class ListPaymentAction extends BaseAction
{
    use GetPlatformStatusConstantNameTrait;
    use ListPaymentTrait;

    private string $source;

    public function handle(?array $params)
    {
        $request = request();
        $is_xadal = $request->get('is_xadal');
        $this->source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;
        $orderBuyderStatus = $request->get('order_buyder_status');
        $orderBuyders = OrderBuyder::query();
        $orderBuyders->where('source', $this->source);
        $orderBuyders->with(['staffOrder' => function ($staffOrder) {
            $staffOrder->select('id', 'name', 'code');
        }]);
        $orderBuyders->with(['subOrderBuyders' => function ($subOrderBuyders) {
            $subOrderBuyders->with(['orderDetailSub' => function ($orderDetailSub) {
                $orderDetailSub->with(['item' => function ($item) {
                    $item->select('id', 'num_iid', 'seller_id', 'detail_url');
                }])->with(['order' => function ($order) {
                    $order->select('id', 'code', 'status');
                }])->with(['supplier' => function ($supplier) {
                    $supplier->select('id', 'name', 'code');
                }]);
            }]);
        }]);
        /*$orderBuyders->with(['subOrderBuyders.orderDetailSub']);*/
        if ($request->get('staff_id')) {
            $orderBuyders->where('creator_id', $request->get('staff_id'));
        }
        if ($request->get('order_buyder_id')) {
            $orderBuyders->where('buyder_id', $request->get('order_buyder_id'));
        }
        if ($request->get('order_buyder_no')) {
            if ($this->source == BuyderServiceConstant::SOURCE_TAOBAO) {
                $orderBuyders->where('purchase_order_outer_id', $request->get('order_buyder_no'));
            } else {
                $orderBuyders->where('buyder_no', $request->get('order_buyder_no'));
            }
        }
        if ($request->get('from')) {
            $orderBuyders->where('created_at', '>=', $request->get('from'));
        }
        if ($request->get('to')) {
            $orderBuyders->where('created_at','<=', $request->get('to'));
        }
        if (isset($orderBuyderStatus) && $orderBuyderStatus != 'all') {
            $orderBuyders->where('supplier_status', $orderBuyderStatus);
        }
        if ($is_xadal != '') {
            $orderBuyders->where('is_xadal', $is_xadal);
        }
        $orderBuyders = $orderBuyders->owned()->orderBy('id', 'DESC')->paginate(ResourceConstant::PER_PAGE_MIN);
        /*dd($orderBuyders);*/
        if ($this->source == BuyderServiceConstant::SOURCE_TAOBAO) {
            return $this->handleFormatDataTaobao($orderBuyders);
        } else {
            return $this->handleFormatData1688($orderBuyders);
        }
    }

    private function handleFormatDataTaobao(LengthAwarePaginator $orderBuyders): LengthAwarePaginator
    {
        $orderBuyders->getCollection()->transform(function ($orderBuyder) {
            $generalInfo = $this->getGeneralInfo($orderBuyder);

            $offerId = '';
            $sellerId = '';
            $orderBuyderSubs = $this->getOrderBuyderSubsData($orderBuyder, $offerId, $sellerId);
            $orderBuyderSubFormat = [];
            $total = [];
            foreach ($orderBuyderSubs as $buyderSub) {
                if (isset($total[$buyderSub['order_code']])) {
                    $total[$buyderSub['order_code']]['order_buyder_sub_total_price']
                        += $buyderSub['order_buyder_sub_total_price'];
                    $total[$buyderSub['order_code']]['order_buyder_platform_sub_total_price']
                        += $buyderSub['order_buyder_platform_sub_total_price'];
                } else {
                    $total[$buyderSub['order_code']]['order_buyder_sub_total_price']
                        = $buyderSub['order_buyder_sub_total_price'];
                    $total[$buyderSub['order_code']]['order_buyder_platform_sub_total_price']
                        = $buyderSub['order_buyder_platform_sub_total_price'];
                }
                $buyderSub['order_buyder_sub_shipping_cost'] = $buyderSub['order_buyder_sub_shipping_cost'] ?? 0;
                $orderBuyderSubFormat[$buyderSub['order_code']]['list_order'][] = $buyderSub;
                $orderBuyderSubFormat[$buyderSub['order_code']]['order_information'] = [
                    'order_id' => $buyderSub['order_id'],
                    'order_code' => $buyderSub['order_code'],
                    'order_status' => $buyderSub['order_status'],
                    'order_status_text' => $buyderSub['order_status_text'],
                    'supplier' => $buyderSub['order_buyder_sub_supplier'],
                    'supplier_id' => $buyderSub['order_buyder_sub_supplier_id'],
                    'offer_id' => $buyderSub['offer_id'],
                    'order_buyder_platform_source' => $generalInfo['order_buyder_platform_source'],
                    'total_customer_payment' => 0,
                    'total_platform_price' => 0,
                    'total_different' => 0
                ];
            }
            foreach ($orderBuyderSubFormat as $key => &$value) {
                if (isset($total[$key])) {
                    $value['order_information']['total_customer_payment']
                        = round($total[$key]['order_buyder_sub_total_price']);
                    $value['order_information']['total_platform_price']
                        = round($total[$key]['order_buyder_platform_sub_total_price']);
                    $value['order_information']['total_different']
                        = round(
                            $total[$key]['order_buyder_sub_total_price']
                            - $total[$key]['order_buyder_platform_sub_total_price']
                    );
                }
            }
            unset($value);
            $orderBuyderShippingCost = (float)$orderBuyder->platform_domestic_post_fee ?? 0;
            $platform_total_amount = (float)$orderBuyder->platform_total_amount;
            $purchase_amount = (float)$orderBuyder->purchase_amount;
            return array_merge($generalInfo, [
                'order_buyder_offer_id' => $offerId,
                'order_buyder_seller_id' => $sellerId,
                'total_buyer_sub' => sizeof($orderBuyderSubs) + 2,
                'order_buyder_shipping_cost' => $orderBuyderShippingCost,
                'order_buyder_total_price' => $platform_total_amount,
                'total_different' => $purchase_amount - $platform_total_amount,
                'order_buyder_subs' => array_values($orderBuyderSubFormat)
            ]);
        });

        return $orderBuyders;
    }

    private function handleFormatData1688(LengthAwarePaginator $orderBuyders): LengthAwarePaginator
    {
        $orderBuyders->getCollection()->transform(function ($orderBuyder) {
            $generalInfo = $this->getGeneralInfo($orderBuyder);

            $offerId = '';
            $sellerId = '';
            $orderBuyderSubs = $this->getOrderBuyderSubsData($orderBuyder, $offerId, $sellerId);
            $orderBuyderSubFormat = [];
            $total = [];
            foreach ($orderBuyderSubs as $buyderSub) {
                if (isset($total[$buyderSub['order_code']])) {
                    $total[$buyderSub['order_code']]['order_buyder_sub_total_price']
                        += $buyderSub['order_buyder_sub_total_price'];
                    $total[$buyderSub['order_code']]['order_buyder_platform_sub_total_price']
                        += $buyderSub['order_buyder_platform_sub_total_price'];
                } else {
                    $total[$buyderSub['order_code']]['order_buyder_sub_total_price']
                        = $buyderSub['order_buyder_sub_total_price'];
                    $total[$buyderSub['order_code']]['order_buyder_platform_sub_total_price']
                        = $buyderSub['order_buyder_platform_sub_total_price'];
                }
                $buyderSub['order_buyder_sub_shipping_cost'] = $buyderSub['order_buyder_sub_shipping_cost'] ?? 0;
                $orderBuyderSubFormat[$buyderSub['order_code']]['list_order'][] = $buyderSub;
                $orderBuyderSubFormat[$buyderSub['order_code']]['order_information'] = [
                    'order_id' => $buyderSub['order_id'],
                    'order_code' => $buyderSub['order_code'],
                    'order_status' => $buyderSub['order_status'],
                    'order_status_text' => $buyderSub['order_status_text'],
                    'supplier' => $buyderSub['order_buyder_sub_supplier'],
                    'supplier_id' => $buyderSub['order_buyder_sub_supplier_id'],
                    'offer_id' => $buyderSub['offer_id'],
                    'order_buyder_platform_source' => $generalInfo['order_buyder_platform_source'],
                    'total_customer_payment' => 0,
                    'total_platform_price' => 0,
                    'total_different' => 0
                ];
            }
            foreach ($orderBuyderSubFormat as $key => &$value) {
                if (isset($total[$key])) {
                    $value['order_information']['total_customer_payment']
                        = round($total[$key]['order_buyder_sub_total_price']);
                    $value['order_information']['total_platform_price']
                        = round($total[$key]['order_buyder_platform_sub_total_price']);
                    $value['order_information']['total_different']
                        = round(
                        $total[$key]['order_buyder_sub_total_price']
                        - $total[$key]['order_buyder_platform_sub_total_price']
                    );
                }
            }
            unset($value);

            $orderBuyderShippingCost = (float)$orderBuyder->platform_domestic_post_fee ?? 0;
            $platform_total_amount = (float)$orderBuyder->platform_total_amount;
            $purchase_amount = (float)$orderBuyder->purchase_amount;

            return array_merge($generalInfo, [
                'order_buyder_offer_id' => $offerId,
                'total_buyer_sub' => sizeof($orderBuyderSubs) + 2,
                'order_buyder_seller_id' => $sellerId,
                'order_buyder_shipping_cost' => $orderBuyderShippingCost,
                'order_buyder_total_price' => $platform_total_amount,
                'total_different' => $purchase_amount - $platform_total_amount,
                'order_buyder_subs' => array_values($orderBuyderSubFormat)
            ]);
        });

        return $orderBuyders;
    }
}
