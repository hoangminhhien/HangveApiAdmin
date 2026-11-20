<?php

namespace App\Traits\PlaceOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\OrderConstant;
use App\Constants\RoleConstant;
use App\Models\Customer;
use App\Models\OrderDetail;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

trait ListActionTrait
{
    private function formatPlaceOrderList(LengthAwarePaginator $placeOrderList, string $source): LengthAwarePaginator
    {
        $placeOrderList->getCollection()->transform(function ($order) use ($source) {
            $china_shipping_cost = data_get($order, 'china_shipping_cost');
            $total_amount = $order->details->sum(fn ($item) => $item->unit_price_cny * $item->quantity) ?? 0;
            $orderNotes = $order->orderNotes ?? [];
            $orderHistories = $order->orderHistories ?? [];
            foreach ($orderHistories as &$orderHistory) {
                $orderHistory->date = date('d-m-Y H:i:s', strtotime($orderHistory->created_at));
            }
            foreach ($orderNotes as &$note) {
                $note->date = date('d-m-Y H:i:s', strtotime($note->updated_at));
                if ($note->subject) {
                    $note->staff_note = [
                        'id' => $note->subject->id,
                        'name' => $note->subject->name ?? '',
                    ];
                } else {
                    $note->staff_note = null;
                }
            }
            $count = 0;
            $note_hang_ve = '';
            if (!empty($orderNotes)) {
                foreach ($orderNotes as $orderNote) {
                    if ($orderNote->subject_type == Customer::class) {
                        $note_hang_ve = $orderNote->content;
                        $count += 1;
                    }
                    /*if ($orderNote->subject_type == Staff::class) {
                        $count = 1;
                    }*/
                    // ghi chú nội bộ
                    if ($orderNote->is_public == 0) {
                        $count += 1;
                    }
                    // ghi chú nội bộ kd
                    if ($orderNote->is_public == 3) {
                        $count += 1;
                    }
                }
            }
            $order->order_notes = $orderNotes;
            $generalInfo = [
                'china_shipping_cost' => $china_shipping_cost,
                'total_amount' => $total_amount,
                'total_amount_all' => $china_shipping_cost + $total_amount,
                'order_id' => data_get($order, 'id'),
                'version_edit' => data_get($order, 'version_edit'),
                'customer_name' => $order->customer->name ?? '',
                'customer_source' => $order->customer->source ?? '',
                'staff_name' => $order->staffOrder->name ?? '',
                'order_code' => data_get($order, 'code'),
                'order_note_internal_count' => $count,
                'order_note_internal' => $orderHistories,
                'arr_order_note' => $orderNotes,
                'order_note_hang_ve' => $note_hang_ve,
                'order_date' => Carbon::parse($order->created_at)->format('d/m/Y'),
                'order_status' => OrderConstant::getConstantValue('STATUSES', $order->status),
                'order_status_color' => OrderConstant::getConstantValue('STATUSES_COLOR', $order->status),
                'order_status_value' => $order->status,
                'order_number_packages' => $order->packages->count(),
                'organization_id' => data_get($order, 'organization_id'),
            ];

            $orderDetails = $order->details;
            $detailIds = $orderDetails->pluck('is_from_addon', 'id');
            $products = [];
            $orderMpIds = $orderDetails->pluck('mp_id');
            $formatMethod = 'formatOrderBuyders' . ucfirst($source);
            $orderBuyders = $this->{$formatMethod}($order->orderBuyders, $orderMpIds->toArray());
            $orderBuyders = $orderBuyders->flatten(1);
            $buyerTotal = [];
            foreach ($orderBuyders as $k => $orderBuyder) {
                if ($orderBuyder['order_buyder_status_code'] == BuyderStatusConstant::ORDER_TRADE_CLOSED) {
                    unset($orderBuyders[$k]);
                }
                if ($order->id != $orderBuyder['order_id']) {
                    unset($orderBuyders[$k]);
                }
                $key = $orderBuyder['buyder_id'];
                if (!$key) {
                    unset($orderBuyders[$k]);
                }
                $buyerTotal[$key] = [
                    'platform_total_amount' => 0,
                    'platform_goods_amount' => 0,
                    'platform_domestic_post_fee' => 0,
                    'total_quantity' => 0,
                    'total_price' => 0,
                    'order_buyder_date' => $orderBuyder['order_buyder_date'],
                    'order_buyder_outer_id' => $orderBuyder['order_buyder_outer_id'],
                    'order_id' => $key,
                    'buyder_sub_status' => $orderBuyder['order_buyder_status'],
                    'buyder_sub_status_color' => $orderBuyder['order_buyder_status_color'],
                ];
            }
            $offer_id = '';
            $seller_id = '';
            foreach ($orderDetails as $orderDetail) {
                $productPrice = data_get($orderDetail, 'unit_price_cny');
                $notesByAdmin    = $orderDetail->notes->isNotEmpty() ? $orderDetail->notes->where('subject_type', Staff::class) : '';
                $lastNoteByAdmin = $notesByAdmin ? $notesByAdmin->first() : '';
                $item = $orderDetail->item;
                if ($offer_id == '') {
                    $offer_id = $item->num_iid ?? 0;
                }
                if ($seller_id == '') {
                    $seller_id = $item->seller_id ?? 0;
                }
                $product_number_customer = data_get($orderDetail, 'quantity');
                $orderSubBuyerOrderQuantity = $orderDetail->orderSubBuyerOrder
                    ->where('buyder_status', '!=', 'ORDER_TRADE_CLOSED')
                    ->where('order_id', $order->id)
                    ->sum('quantity') ?? 0;
                $orderDetailAssignedQuantity = $orderDetail->orderDetailAssigned
                    /*->where('status', '!=', BuyderStatusConstant::SUB_BUYDER_ORDER_TRADE_CLOSED)*/
                        ->whereNull('order_no')
                    ->where('order_id', $order->id)
                    ->sum('quantity') ?? 0;
                $productNumberAvailable = $product_number_customer
                    - $orderSubBuyerOrderQuantity
                    - $orderDetailAssignedQuantity;

                $productNumberAvailable = max($productNumberAvailable, 0);
                /*if ($order->id == 584876) {
                    Log::info('$productNumberAvailable  '  .  $productNumberAvailable);
                    Log::info('$product_number_customer  '  .  $product_number_customer);
                    Log::info('$orderSubBuyerOrderQuantity  '  .  $orderSubBuyerOrderQuantity);
                    Log::info('$orderDetailAssignedQuantity  '  .  $orderDetailAssignedQuantity);
                }*/
                $orderDetailHistoryByClassification = $orderDetail->orderDetailHistoryByClassification ?? null;
                $product_classification_new = '';
                if ($orderDetailHistoryByClassification && $orderDetailHistoryByClassification->count() > 0) {
                    $product_classification_new = $orderDetailHistoryByClassification->toArray()[0]['value'];
                }
                $products[] = [
                    'note_by_admin' => $notesByAdmin ? $notesByAdmin->count() : 0,
                    'text_note_last' => $orderDetail->note_staff_latest ?? data_get($lastNoteByAdmin, 'content'),
                    'text_note_priority' => $lastNoteByAdmin ? data_get($lastNoteByAdmin, 'priority') : "",
                    'is_from_addon' => data_get($orderDetail, 'is_from_addon'),
                    'order_detail_id' => data_get($orderDetail, 'id'),
                    'order_detail_source' => data_get($orderDetail, 'source'),
                    'product_mp_id' => data_get($orderDetail, 'mp_id', 0),
                    'product_mp_sku_id' => data_get($orderDetail, 'mp_sku_id', 0),
                    'product_id' => data_get($orderDetail, 'id'),
                    'product_name' => data_get($orderDetail, 'name'),
                    'product_image' => data_get($orderDetail, 'image'),
                    'product_classification' => data_get($orderDetail, 'classification'),
                    'product_classification_new' => $product_classification_new,
                    'product_url' => data_get($orderDetail, 'link'),
                    'product_note' => data_get($orderDetail, 'note'),
                    'product_number_customer' => $product_number_customer,
                    'product_number_available' => $productNumberAvailable,
                    'product_price' => $productPrice,
                    'product_total_cost' => round($productNumberAvailable * $productPrice, 2),
                    'product_sku_properties' => data_get($item, 'sku_properties', []),
                    'product_note_china' => data_get($orderDetail, 'note_china'),
                ];
            }

            $orderBuyderFormat = [];
            foreach ($orderBuyders as $buyer) {
                $key = $buyer['buyder_id'];
                $product_quantity = $buyer['product_quantity'];
                $product_cost = $buyer['product_total_cost'];
                $buyerTotal[$key]['platform_total_amount']
                    += $buyer['platform_total_amount'];
                $buyerTotal[$key]['platform_goods_amount']
                    += $buyer['platform_goods_amount'];
                $buyerTotal[$key]['platform_domestic_post_fee']
                    += $buyer['platform_domestic_post_fee'];
                $buyerTotal[$key]['total_quantity']
                    += $product_quantity;
                $buyerTotal[$key]['total_price']
                    += $product_cost;
                $buyer['is_from_addon'] = $detailIds[$buyer['order_detail_id']] ?? 0;
                $orderBuyderFormat[$key]['list_order'][] = $buyer;
            }
            foreach ($orderBuyderFormat as $key => &$format) {
                if (isset($buyerTotal[$key])) {
                    $format['total'] = $buyerTotal[$key];
                    $format['total']['total_price'] = round($buyerTotal[$key]['total_price'], 2);
                    $format['total']['amount_difference'] = round($format['total']['total_price']
                        - $buyerTotal[$key]['platform_total_amount'] - $buyerTotal[$key]['platform_domestic_post_fee'], 2);
                }
            }
            unset($format);

            // thêm nhà cung cấp để lấy thông tin cần update
            $orderSuppliers = $order->orderSuppliers ?? collect();
            // format suppliers list
            $suppliers = $orderSuppliers->map(function ($item) {
                return [
                    'id' => $item->supplier_id,
                    'delivery_type' => [
                        'value' => $item->delivery_type,
                        'label' => OrderConstant::DELIVERIES_TEXT[$item->delivery_type] ?? null
                    ],
                    'services' => [
                        'is_woodworking' => (bool)$item->is_woodworking,
                        'is_inspection' => (bool)$item->is_inspection,
                        'is_advanced_inspection' => (int)$item->is_advanced_inspection,
                        'is_shock_proof' => (bool)$item->is_shock_proof,
                        'official_is_partner' => (bool)$item->official_is_partner,
                        'is_china_shipping' => (bool)$item->is_china_shipping,
                    ],
                    'order_cost' =>(float) $item->order_cost,
                    'order_fee' => (float)$item->order_fee,
                    'china_shipping_cost' => (float)$item->china_shipping_cost,
                    'international_shipping_cost' => (float)$item->international_shipping_cost,
                    'inspection_cost' => (float)$item->inspection_cost,
                    'advanced_inspection_cost' => (float)$item->advanced_inspection_cost,
                    'discount_cost' => (float)$item->discount_cost,
                    'order_cost_real' => (float)$item->order_cost_real ?? 0,
                    'china_shipping_cost_real' => (float)$item->china_shipping_cost_real ?? 0,
                    'actual_payment' => round((float)$item->order_cost_real + (float)$item->china_shipping_cost_real, 2),
                ];
            })->values();
            // hết
            $hasEdit = false;
            $user = auth()->user();
            if(in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
                $hasEdit = true;
            }
            if($order->status == OrderConstant::KEY_STATUS_ORDERED && ($user->hasRoleName(RoleConstant::ADMIN) || $user->hasRoleName(RoleConstant::TRUONG_BP_GDQT))) {
                $hasEdit = true;
            }
            return array_merge($generalInfo, [
                'products' => !empty($products) ? $products : [],
                'chat_info' => [
                    'offer_id' => $offer_id,
                    'seller_open_id' => $seller_id,
                ],
                'order_buyders' => array_values($orderBuyderFormat),
                'suppliers' => $suppliers,
                'has_edit_cost_real' => $hasEdit
            ]);
        });
        return $placeOrderList;
    }


    private function formatPlaceOrderList1688(LengthAwarePaginator $placeOrderList, string $source): LengthAwarePaginator
    {
        $placeOrderList->getCollection()->transform(function ($order) use ($source) {
            $china_shipping_cost = data_get($order, 'china_shipping_cost');
            $total_amount = $order->details->sum(fn ($item) => $item->unit_price_cny * $item->quantity) ?? 0;
            $orderNotes = $order->orderNotes ?? [];
            $orderHistories = $order->orderHistories ?? [];
            foreach ($orderHistories as &$orderHistory) {
                $orderHistory->date = date('d-m-Y H:i:s', strtotime($orderHistory->created_at));
            }
            foreach ($orderNotes as &$note) {
                $note->date = date('d-m-Y H:i:s', strtotime($note->created_at));
                if ($note->subject) {
                    $note->staff_note = [
                        'id' => $note->subject->id,
                        'name' => $note->subject->name ?? '',
                    ];
                } else {
                    $note->staff_note = null;
                }
            }
            $count = 0;
            $note_hang_ve = '';
            if (!empty($orderNotes)) {
                foreach ($orderNotes as $orderNote) {
                    /*if ($orderNote->subject_type == Staff::class) {
                        $count = 1;
                    }*/
                    // ghi chú nội bộ
                    if ($orderNote->is_public == 0) {
                        $count += 1;
                    }
                    // ghi chú nội bộ kd
                    if ($orderNote->is_public == 3) {
                        $count += 1;
                    }
                    if ($orderNote->subject_type == Customer::class) {
                        $note_hang_ve = $orderNote->content;
                        $count += 1;
                    }
                }
            }
            $order->order_notes = $orderNotes;
            $generalInfo = [
                'order_id' => data_get($order, 'id'),
                'china_shipping_cost' => $china_shipping_cost,
                'total_amount' => $total_amount,
                'total_amount_all' => $china_shipping_cost + $total_amount,
                'customer_name' => $order->customer->name ?? '',
                'customer_source' => $order->customer->source ?? '',
                'staff_name' => $order->staffOrder->name ?? '',
                'order_code' => data_get($order, 'code'),
                'order_note_internal_count' => $count,
                'arr_order_note' => $orderNotes,
                'order_note_internal' => $orderHistories,
                'order_note_hang_ve' => $note_hang_ve,
                'order_date' => Carbon::parse($order->created_at)->format('d/m/Y'),
                'order_status' => OrderConstant::getConstantValue('STATUSES', $order->status),
                'order_status_color' => OrderConstant::getConstantValue('STATUSES_COLOR', $order->status),
                'order_number_packages' => $order->packages->count(),
                'organization_id' => data_get($order, 'organization_id'),
                'version_edit' => data_get($order, 'version_edit'),
                'order_status_value' => $order->status,
            ];

            $orderDetails = $order->details;
            $detailIds = $orderDetails->pluck('is_from_addon', 'id');
            $products = [];
            $orderMpIds = $orderDetails->pluck('mp_id');
            $formatMethod = 'formatOrderBuyders' . ucfirst($source);
            $orderBuyders = $this->{$formatMethod}($order->orderBuyders, $orderMpIds->toArray(), $order->id);
            $orderBuyders = $orderBuyders->flatten(1);
            $buyerTotal = [];
            foreach ($orderBuyders as $k => $orderBuyder) {
                if ($orderBuyder['order_buyder_status_code'] == BuyderStatusConstant::ORDER_TRADE_CLOSED) {
                    unset($orderBuyders[$k]);
                }
                $key = $orderBuyder['order_buyder_id'];
                $buyerTotal[$key] = [
                    'platform_total_amount' => 0,
                    'platform_goods_amount' => 0,
                    'platform_domestic_post_fee' => 0,
                    'total_quantity' => 0,
                    'total_price' => 0,
                    'order_buyder_date' => $orderBuyder['order_buyder_date'],
                    'order_buyder_outer_id' => $key,
                    'buyder_sub_status' => $orderBuyder['order_buyder_status'],
                    'buyder_sub_status_color' => $orderBuyder['order_buyder_status_color'],
                ];
            }
            $offer_id = '';
            $seller_id = '';
            foreach ($orderDetails as $orderDetail) {
                $notesByAdmin = $orderDetail->notes->isNotEmpty() ? $orderDetail->notes->where('subject_type', Staff::class) : '';
                $lastNoteByAdmin = $notesByAdmin ? $notesByAdmin->first() : '';
                $productPrice = data_get($orderDetail, 'unit_price_cny');
                $item = $orderDetail->item;
                if ($offer_id == '') {
                    $offer_id = $item->num_iid ?? 0;
                }
                if ($seller_id == '') {
                    $seller_id = $item->seller_id ?? 0;
                }
                $product_number_customer = data_get($orderDetail, 'quantity');
                $orderSubBuyerOrderQuantity = $orderDetail->orderSubBuyerOrder->where('order_id', $order->id)->sum('quantity') ?? 0;
                $orderDetailAssignedQuantity = $orderDetail->orderDetailAssigned
                    ->whereNull('order_no')
                    ->where('order_id', $order->id)
                    /*->where('status', '!=', BuyderStatusConstant::ORDER_BUYDER_1688_SUCCESS)*/
                    ->sum('quantity') ?? 0;
                $productNumberAvailable = $product_number_customer
                    - $orderSubBuyerOrderQuantity
                    - $orderDetailAssignedQuantity;
                $productNumberAvailable = max($productNumberAvailable, 0);
                $orderDetailHistoryByClassification = $orderDetail->orderDetailHistoryByClassification ?? null;
                $product_classification_new = '';
                if ($orderDetailHistoryByClassification && $orderDetailHistoryByClassification->count() > 0) {
                    $product_classification_new = $orderDetailHistoryByClassification->toArray()[0]['value'];
                }
                $products[] = [
                    'note_by_admin' => $notesByAdmin ? $notesByAdmin->count() : 0,
                    'text_note_last' => $orderDetail->note_staff_latest ?? data_get($lastNoteByAdmin, 'content'),
                    'text_note_priority' => $lastNoteByAdmin ? data_get($lastNoteByAdmin, 'priority') : "",
                    'is_from_addon' => data_get($orderDetail, 'is_from_addon'),
                    'order_detail_id' => data_get($orderDetail, 'id'),
                    'order_detail_source' => data_get($orderDetail, 'source'),
                    'product_mp_id' => data_get($orderDetail, 'mp_id', 0),
                    'product_mp_sku_id' => data_get($orderDetail, 'mp_sku_id', 0),
                    'product_id' => data_get($orderDetail, 'id'),
                    'product_name' => data_get($orderDetail, 'name'),
                    'product_image' => data_get($orderDetail, 'image'),
                    'product_classification' => data_get($orderDetail, 'classification'),
                    'product_classification_zh' => data_get($orderDetail, 'classification_zh'),
                    'product_classification_new' => $product_classification_new,
                    'product_url' => data_get($orderDetail, 'link'),
                    'product_note' => data_get($orderDetail, 'note'),
                    'product_number_customer' => $product_number_customer,
                    'product_number_available' => $productNumberAvailable,
                    'product_price' => $productPrice,
                    'product_total_cost' => round($productNumberAvailable * $productPrice, 2),
                    'product_sku_properties' => data_get($item, 'sku_properties', []),
                    'product_note_china' => data_get($orderDetail, 'note_china'),
                ];
            }

            $orderBuyderFormat = [];
            foreach ($orderBuyders as $buyer) {
                $key = $buyer['order_buyder_id'];
                $product_quantity = $buyer['data']->sum('product_quantity');
                $total_price = $buyer['data']->sum('product_total_cost');

                $buyerTotal[$key]['platform_total_amount']
                    += $buyer['data']->sum('platform_total_amount');
                $buyerTotal[$key]['platform_goods_amount']
                    += $buyer['data']->sum('platform_goods_amount');
                $buyerTotal[$key]['platform_domestic_post_fee']
                    += $buyer['data']->sum('platform_domestic_post_fee');
                $buyerTotal[$key]['total_quantity']
                    += $product_quantity;
                $buyerTotal[$key]['total_price']
                    += $total_price;
                $data = $buyer['data'];
                $dataFormat = [];
                foreach ($data as $k => $item) {
                    $dataFormat[$k] = $item;
                    $dataFormat[$k]['is_from_addon'] = $detailIds[$item['order_detail_id']] ?? 0;
                }
                $buyer['data'] = array_values($dataFormat);
                $orderBuyderFormat[$key]['list_order'] = $buyer;
            }
            foreach ($orderBuyderFormat as $key => &$format) {
                if (isset($buyerTotal[$key])) {
                    $format['total'] = $buyerTotal[$key];
                    $format['total']['total_price'] = round($buyerTotal[$key]['total_price'], 2);
                    $format['total']['amount_difference'] = round($format['total']['total_price']
                        - $buyerTotal[$key]['platform_total_amount'] - $buyerTotal[$key]['platform_domestic_post_fee'], 2);
                }
            }
            unset($format);
            $counts = collect($products)->countBy('product_mp_sku_id');
            $products = collect($products)->map(function ($item) use ($counts) {
                $item['is_duplicate'] = $counts[$item['product_mp_sku_id']] > 1;
                return $item;
            });

            // thêm nhà cung cấp để lấy thông tin cần update
            $orderSuppliers = $order->orderSuppliers ?? collect();
            // format suppliers list
            $suppliers = $orderSuppliers->map(function ($item) {
                return [
                    'id' => $item->supplier_id,
                    'delivery_type' => [
                        'value' => $item->delivery_type,
                        'label' => OrderConstant::DELIVERIES_TEXT[$item->delivery_type] ?? null
                    ],
                    'services' => [
                        'is_woodworking' => (bool)$item->is_woodworking,
                        'is_inspection' => (bool)$item->is_inspection,
                        'is_advanced_inspection' => (int)$item->is_advanced_inspection,
                        'is_shock_proof' => (bool)$item->is_shock_proof,
                        'official_is_partner' => (bool)$item->official_is_partner,
                        'is_china_shipping' => (bool)$item->is_china_shipping,
                    ],
                    'order_cost' =>(float) $item->order_cost,
                    'order_fee' => (float)$item->order_fee,
                    'china_shipping_cost' => (float)$item->china_shipping_cost,
                    'international_shipping_cost' => (float)$item->international_shipping_cost,
                    'inspection_cost' => (float)$item->inspection_cost,
                    'advanced_inspection_cost' => (float)$item->advanced_inspection_cost,
                    'discount_cost' => (float)$item->discount_cost,
                    'order_cost_real' => (float)$item->order_cost_real ?? 0,
                    'china_shipping_cost_real' => (float)$item->china_shipping_cost_real ?? 0,
                    'actual_payment' => round((float)$item->order_cost_real + (float)$item->china_shipping_cost_real, 2),
                ];
            })->values();
            // hết
            $hasEdit = false;
            $user = auth()->user();
            if(in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
                $hasEdit = true;
            }
            if($order->status == OrderConstant::KEY_STATUS_ORDERED && ($user->hasRoleName(RoleConstant::ADMIN) || $user->hasRoleName(RoleConstant::TRUONG_BP_GDQT))) {
                $hasEdit = true;
            }
            return array_merge($generalInfo, [
                'chat_info' => [
                    'offer_id' => $offer_id,
                    'seller_open_id' => $seller_id,
                ],
                'products' => $products,
                'order_buyders' => array_values($orderBuyderFormat),
                'suppliers' => $suppliers,
                'has_edit_cost_real' => $hasEdit,
            ]);
        });
        return $placeOrderList;
    }
    private function formatOrderBuydersTaobao(Collection $orderBuyders, array $orderMpIds): Collection
    {
        $authId = auth()->id();
        return $orderBuyders->map(function ($orderBuyder) use ($orderMpIds, $authId) {
            $orderBuyder->loadMissing(['staffOrder']);

            $supplierStatus = data_get($orderBuyder, 'supplier_status');
            $statusConstant = 'BUYDER_ORDER_STATUSES';
            $colorConstant = 'BUYDER_ORDER_STATUS_COLORS';
            $mutualOrderBuyder = [
                'buyder_id' => data_get($orderBuyder, 'buyder_id'),
                'order_buyder_platform_source' => data_get($orderBuyder, 'source'),
                'order_buyder_id' => data_get($orderBuyder, 'id'),
                'order_buyder_date' => Carbon::parse($orderBuyder->created_at)->format('d/m/Y'),
                'order_buyder_code' => data_get($orderBuyder, 'buyder_no'),
                'order_buyder_outer_id' => data_get($orderBuyder, 'purchase_order_outer_id'),
                'order_buyder_status' => BuyderStatusConstant::LIST_ORDER_STATUS_NAME[$supplierStatus] ?? '',
                'order_buyder_status_color' => BuyderStatusConstant::LIST_ORDER_STATUS_COLOR[$supplierStatus] ?? '',
                'order_buyder_status_code' => data_get($orderBuyder, 'supplier_status'),
                'order_buyder_quantity_placed' => $orderBuyder->totalPlacedQuantity(),
            ];

            // chỉ lấy các order_buyder_subs thuộc order_details của orders này
            $subOrderBuyders = $orderBuyder->subOrderBuyders
                ->whereIn('mp_id', $orderMpIds)
                ->sortByDesc('created_at');
            return $subOrderBuyders->map(function ($orderBuyderSub) use (
                $mutualOrderBuyder, $orderBuyder, $authId
            ) {
                $orderDetail = $orderBuyderSub->orderDetailSub ?? [];
                $productCost = data_get($orderDetail, 'unit_price_cny');
                $productQuantity = (int)data_get($orderBuyderSub, 'quantity');
                $orderDetailQuantity = $orderDetail->quantity ?? 0;
                if ($productQuantity > $orderDetailQuantity) {
                    $productQuantity = $orderDetailQuantity;
                }
                // ở đây check để thực hiện 2 tác vụ: 'Thanh toán' hoặc 'Hủy đặt hàng'
                // có thể check nếu thỏa mãn tất cả các điều kiện
                //   + là đơn nó đặt hàng
                //   + đơn hàng ở trạng thái 'Chưa thanh toán' (Taobao và 1688)
                //   + đơn hàng con không có 'close_reason'
                $isCheckable = $authId == data_get($orderBuyderSub, 'staff_id')
                    && (
                        data_get($orderBuyder, 'supplier_status') == BuyderStatusConstant::ORDER_WAIT_BUYER_P
                        || data_get($orderBuyder, 'supplier_status') == BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_PAY
                    ) && is_null(data_get($orderBuyderSub, 'close_reason'));
                $isSimilarClassification = true;
                return array_merge($mutualOrderBuyder, [
                    'is_checkable' => $isCheckable,
                    'order_id' => $orderBuyderSub->order_id,
                    'is_similar_classification' => $isSimilarClassification,
                    'buyder_sub_id' => data_get($orderBuyderSub, 'buyder_sub_id'),
                    'buyder_sub_status' => BuyderStatusConstant::getConstantValue(
                        'SUB_BUYDER_ORDER_STATUSES',
                        data_get($orderBuyderSub, 'buyder_status')
                    ),
                    'buyder_sub_status_color' => BuyderStatusConstant::getConstantValue(
                        'SUB_BUYDER_ORDER_STATUS_COLORS',
                        data_get($orderBuyderSub, 'buyder_status')
                    ),
                    'order_detail_id' => data_get($orderDetail, 'id'),
                    'product_name' => data_get($orderDetail, 'name'),
                    'product_image' => data_get($orderDetail, 'image'),
                    'product_url' => data_get($orderDetail, 'link'),
                    'product_classification' => data_get($orderDetail, 'classification') ?? data_get($orderBuyderSub, 'classification_zh'),
                    'product_classification_zh' => data_get($orderBuyderSub, 'classification_zh'),
                    'product_cost' => $productCost,
                    'product_quantity' => $productQuantity,
                    'product_total_cost' => round($productCost * $productQuantity, 2),
                    'product_logistic_number' => data_get($orderBuyderSub, 'logistic_number'),
                    'product_delay_time' => data_get($orderBuyder, 'delay_time'),
                    'staff' => $orderBuyder->staffOrder->only(['id', 'name', 'email', 'avatar', 'code', 'phone_number']),
                    'shipping_cost' => data_get($orderBuyderSub, 'domestic_post_fee'),
                    'close_reason' => data_get($orderBuyderSub, 'close_reason'),
                    'platform_total_amount' => ((float)data_get($orderBuyderSub, 'platform_total_amount')) - ((float)data_get($orderBuyderSub, 'platform_domestic_post_fee')),
                    'platform_goods_amount' => ((float)data_get($orderBuyderSub, 'platform_goods_amount')) - ((float)data_get($orderBuyderSub, 'platform_domestic_post_fee')),
                    'platform_domestic_post_fee' => ((float)data_get($orderBuyderSub, 'platform_domestic_post_fee')),
                ]);
            });
        });
    }

    private function formatOrderBuyders1688(Collection $orderBuyders, array $orderMpIds, $order_id): Collection
    {
        $authId = auth()->id();

        return $orderBuyders->map(function ($orderBuyder) use ($orderMpIds, $authId, $order_id) {
            $supplierStatus = data_get($orderBuyder, 'supplier_status');
            $statusConstant = 'ORDER_BUYDER_1688_STATUSES';
            $colorConstant = 'ORDER_BUYDER_1688_STATUS_COLORS';

            // chỉ lấy các order_buyder_subs thuộc order_details của orders này
            $subOrderBuyders = $orderBuyder->subOrderBuyders
                ->whereIn('mp_id', $orderMpIds)
                ->groupBy('order_no')
                ->sortByDesc('created_at');
            $mutualOrderBuyderData = [
                'order_buyder_id' => data_get($orderBuyder, 'buyder_id'),
                'order_buyder_date' => Carbon::parse($orderBuyder->created_at)->format('d/m/Y'),
                'order_buyder_status' => BuyderStatusConstant::LIST_ORDER_STATUS_NAME[$supplierStatus] ?? '',
                'order_buyder_status_color' => BuyderStatusConstant::LIST_ORDER_STATUS_COLOR[$supplierStatus] ?? '',
                'order_buyder_status_code' => data_get($orderBuyder, 'supplier_status'),
                'order_buyder_delay_time' => data_get($orderBuyder, 'delay_time'),
                'order_buyder_platform_total_amount' => data_get($orderBuyder, 'platform_total_amount'),
                'order_buyder_platform_goods_amount' => data_get($orderBuyder, 'platform_goods_amount'),
                'order_buyder_platform_domestic_post_fee' => data_get($orderBuyder, 'platform_domestic_post_fee'),
            ];

            $mutualOrderBuyderSubsData = [
                'order_buyder_platform_source' => data_get($orderBuyder, 'source'),
                'order_buyder_quantity_placed' => $orderBuyder->totalPlacedQuantity(),
            ];

            return $subOrderBuyders->map(function ($orderBuyderSubs) use (
                $mutualOrderBuyderSubsData, $orderBuyder, $authId, $mutualOrderBuyderData, $order_id
            ) {
                $orderBuyderSubsData = $orderBuyderSubs->map(function ($orderBuyderSub) use (
                    $mutualOrderBuyderSubsData, $orderBuyder
                ) {
                    $orderDetail = $orderBuyderSub->orderDetailSub ?? [];
                    $productCost = data_get($orderDetail, 'unit_price_cny');
                    $productQuantity = (int)data_get($orderBuyderSub, 'quantity');
                    $orderDetailQuantity = $orderDetail->quantity ?? 0;
                    if ($productQuantity > $orderDetailQuantity) {
                        $productQuantity = $orderDetailQuantity;
                    }
                    $platform_goods_amount = $orderBuyderSub->platform_goods_amount ?? 0;
                    $platform_domestic_post_fee = $orderBuyderSub->platform_domestic_post_fee ?? 0;
                    $platform_total_amount = $orderBuyderSub->platform_total_amount ?? 0;
                    return array_merge($mutualOrderBuyderSubsData, [
                        'order_detail_id' => data_get($orderDetail, 'id'),
                        'order_id' => $orderBuyderSub->order_id,
                        "platform_total_amount" => $platform_total_amount,
                        "platform_goods_amount" => $platform_goods_amount,
                        "platform_domestic_post_fee" => $platform_domestic_post_fee,
                        'product_name' => data_get($orderDetail, 'name'),
                        'product_image' => data_get($orderDetail, 'image'),
                        'product_url' => data_get($orderDetail, 'link'),
                        'product_classification' => data_get($orderDetail, 'classification') ?? data_get($orderBuyderSub, 'classification_zh'),
                        'product_classification_zh' => data_get($orderBuyderSub, 'classification_zh'),
                        'product_cost' => $productCost,
                        'product_quantity' => $productQuantity,
                        'product_total_cost' => round($productCost * $productQuantity, 2),
                        'staff' => $orderBuyder->staffOrder ?
                            $orderBuyder->staffOrder->only([
                                'id', 'name', 'email', 'avatar', 'code', 'phone_number'
                            ]) : null,
                        'close_reason' => data_get($orderBuyderSub, 'close_reason'),
                    ]);
                });

                $firstOrderBuyderSub = $orderBuyderSubs->first();
                $orderDetail = $firstOrderBuyderSub->orderDetailSub;
                $item = $orderDetail->item;
                // ở đây check để thực hiện 2 tác vụ: 'Thanh toán' hoặc 'Hủy đặt hàng'
                // có thể check nếu thỏa mãn tất cả các điều kiện
                //   + là đơn mà nó đặt hàng
                //   + có ít nhất một đơn con không có 'close_reason'
                $isCheckable = $authId == data_get($orderBuyderSubs->first(), 'staff_id')
                    && is_null($orderBuyderSubs->whereNotNull('close_reason')->first());
                $filtered_collection = $orderBuyderSubsData->filter(function ($item) use ($order_id){
                    return $item['order_id'] == $order_id;
                })->values();
                return array_merge($mutualOrderBuyderData, [
                    'is_checkable' => $isCheckable,
                    'shipping_cost' => data_get($orderBuyderSubs->first(), 'domestic_post_fee'),
                    'seller_open_id' => $item->seller_id ?? 0,
                    'offer_id' => $item->num_iid ?? 0,
                    'order_buyder_platform_source' => BuyderServiceConstant::SOURCE_1688,
                    'data' => $filtered_collection,
                ]);
            });
        });
    }

    /**
     * Kiểm tra 'order_buyder_subs.classification_zh' có giống 'order_details.classification' không
     *
     * @param string $classification
     * @param string|null $classificationZh
     * @return bool
     */
    private function checkSimilarClassification(string $classification, ?string $classificationZh): bool
    {
        if (is_null($classificationZh)) {
            return false;
        }

        $classifications = explode(';', $classification);
        $zhs = explode(';', $classificationZh);

        $result = true;
        foreach ($zhs as $zh) {
            if (!in_array($zh, $classifications)) {
                $result = false;
            }
        }

        return $result;
    }
}
