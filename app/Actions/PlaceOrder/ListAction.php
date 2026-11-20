<?php

namespace App\Actions\PlaceOrder;

use App\Constants\ResourceConstant;
use App\Models\Order;
use App\Traits\PlaceOrder\ListActionTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class ListAction extends BaseAction
{
    use ListActionTrait;

    public function handleOld(?array $params)
    {
        $request = request();
        $perPage = $request->get('per_page') ?? ResourceConstant::PER_PAGE_MIN;
        $page = $request->get('page', 1);
        $source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;
        $orderBuyderStatus = $request->get('order_buyder_status');
        $order_buyder_no = $request->get('order_buyder_no');
        $order_status = $request->get('order_status');
        $waiting_to_order = $request->get('waiting_to_order');

        $orderQuery = Order::query()
            ->select([
                'orders.*',
                'order_details.id as order_detail_id',
                'order_details.source',
            ])
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->when($order_status, fn($q, $st) => $q->where('orders.status', $st))
            ->when($source, fn($q, $src) => $q->where('order_details.source', $src))
            ->when($request->get('organization_id'), fn($q, $orgId) => $q->where('orders.organization_id', $orgId))
            ->when($request->get('staff_id'), fn($q, $staffId) => $q->where('orders.staff_order_id', $staffId))
            ->when($request->get('order_code'), fn($q, $orderCode) => $q->where('orders.code', $orderCode))
            ->when($request->get('from'), fn($q, $from) => $q->where('orders.created_at', '>=', $from))
            ->when($request->get('to'), fn($q, $to) => $q->where('orders.created_at', '<=', $to))
            ->where('order_details.is_display', 1)
            ->owned()
            ->visibleInPlaceOrder()
            ->latest('orders.created_at')
            ->distinct();
        if (isset($waiting_to_order)) {
            $orderQuery->whereDoesntHave('orderBuyders');
        }

        if (isset($orderBuyderStatus) && $orderBuyderStatus != 'all') {
            $orderQuery->whereHas('orderBuyders', function ($q) use ($orderBuyderStatus) {
                $q->where('supplier_status', $orderBuyderStatus);
            });
        }

        if ($order_buyder_no) {
            $orderQuery->whereHas('orderBuyders', function ($q) use ($order_buyder_no, $source) {
                if ($source == 'taobao') {
                    $q->where('purchase_order_outer_id', $order_buyder_no);
                } elseif ($source == '1688') {
                    $q->where('buyder_no', $order_buyder_no);
                }
            });
        }
        $orderQuery->with([
            'packages',
            'orderNotes' => fn($q) => $q
                ->with(['staffNote:id,name'])
                ->orderByDesc('created_at'),
            'orderHistories' => fn($q) => $q
                ->with(['user:id,name'])
                ->orderByDesc('created_at'),
            'details' => fn($q) => $q
                ->whereSource($source)
                ->with([
                    'assignSupportOrders',
                    'subOrderBuyderSelf',
                    'item',
                    'orderDetailHistoryByClassification' => fn($h) => $h->orderByDesc('created_at')
                ]),
            'orderBuyders' => fn($q) => $q
                ->when(isset($orderBuyderStatus) && $orderBuyderStatus != 'all',
                    fn($qq) => $qq->where('supplier_status', $orderBuyderStatus)
                )
                ->with([
                    'staffOrder',
                    'subOrderBuyders' => fn($qq) => $qq
                        ->withoutGlobalScope('without_canceled')
                        ->with(['orderDetailSub', 'staffOrder'])
                        ->orderByDesc('created_at'),
                ]),
        ]);

        $placeOrderList = $orderQuery->paginate($perPage);
        if ($source == 'taobao') {
           return $this->formatPlaceOrderList($placeOrderList, $source);
       }
       return $this->formatPlaceOrderList1688($placeOrderList, $source);
    }
    public function handle(?array $params)
    {
        $request = request();
        $perPage = $request->get('per_page') ?? ResourceConstant::PER_PAGE_MIN;
        $page = request()->get('page', 1);
        $source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;
        $orderBuyderStatus = $request->get('order_buyder_status');
        $order_buyder_no = $request->get('order_buyder_no');
        $order_status = $request->get('order_status');
        $waiting_to_order = $request->get('waiting_to_order');
        $orderQueryFirst = Order::query();
        if ($order_status) {
            $orderQueryFirst = $orderQueryFirst->where('orders.status', $order_status);
        }
        if ($source) {
            $orderQueryFirst = $orderQueryFirst->where('order_details.source', $source);
        }
        $orderIds =
            $orderQueryFirst->select([
                'orders.id as order_id', 'orders.created_at',
                'order_details.id as order_detail_id', 'order_details.order_id', 'order_details.source'
            ])
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->when($request->get('organization_id'), function($q){
                    return $q->where('orders.organization_id', request()->get('organization_id'));
                })
                ->owned()->visibleInPlaceOrder()
                ->latest('orders.created_at')
                ->where('order_details.is_display', 1)
                /*->skip(($page - 1) * $perPage)
                ->take($perPage)*/
                ->get()->pluck('order_id');
        $orderQuery = Order::query()
            ->whereIn('id', $orderIds)
            ->when($request->get('staff_id'), fn($query, $staffId) => $query->where('staff_order_id', $staffId))
            ->when($request->get('order_code'), fn($query, $orderCode) => $query->where('code', $orderCode))
            ->when($request->get('from'), fn($query, $from) => $query->where('created_at', '>=', $from))
            ->when($request->get('to'), fn($query, $from) => $query->where('created_at', '<=', $from));

        if (isset($waiting_to_order)) {
            $orderQuery->whereDoesntHave('orderBuyders');
        }

        if (isset($orderBuyderStatus) && $orderBuyderStatus != 'all') {
            $orderQuery = $orderQuery->whereHas('orderBuyders', function ($orderBuyders) use ($orderBuyderStatus) {
                $orderBuyders->where('supplier_status', $orderBuyderStatus);
            });
        }
        if ($order_buyder_no) {
            if ($source == 'taobao') {
                $orderQuery = $orderQuery->whereHas('orderBuyders', function ($orderBuyders) use ($order_buyder_no) {
                    $orderBuyders->where('purchase_order_outer_id', $order_buyder_no);
                });
            }

            if ($source == '1688') {
                $orderQuery = $orderQuery->whereHas('orderBuyders', function ($orderBuyders) use ($order_buyder_no) {
                    $orderBuyders->where('buyder_no', $order_buyder_no);
                });
            }
        }
        $orderQuery = $orderQuery->with([
            'packages',
            /*'orderNotes' => fn($query) => $query
                ->with(['staffNote' => function ($staffNote) {
                    $staffNote->select('id', 'name');
                }])->orderBy('created_at', 'DESC'),*/
            'orderNotes' => fn($query) => $query
                ->with(['subject' => function ($q) {
                    $q->select('id', 'name');
                }])
                ->orderBy('created_at', 'DESC'),
            'orderHistories' => fn($query) => $query
                ->with(['user' => function ($staffNote) {
                    $staffNote->select('id', 'name');
                }])->orderBy('created_at', 'DESC'),
            'details' => fn($query) => $query
                ->whereSource($source)
                ->with(['assignSupportOrders', 'subOrderBuyderSelf', 'item', 'orderDetailHistoryByClassification' => function($q) {
                    return $q->orderBy('created_at', 'desc');
                }]),
            'orderBuyders' => fn($query) => $query
                ->when(
                    isset($orderBuyderStatus) && $orderBuyderStatus != 'all',
                    fn($qu) => $qu->where('supplier_status', $orderBuyderStatus),
                )->with([
                    'staffOrder',
                    'subOrderBuyders' => fn($query) => $query
                        ->withoutGlobalScope('without_canceled')
                        ->with(['orderDetailSub', 'staffOrder'])
                        ->orderByDesc('created_at'),
                ]),
        ])->orderBy('orders.id', 'DESC');
        $placeOrderList = $orderQuery->paginate($perPage);
        if ($source == 'taobao') {
            return $this->formatPlaceOrderList($placeOrderList, $source);
        }
        return $this->formatPlaceOrderList1688($placeOrderList, $source);
    }
}
