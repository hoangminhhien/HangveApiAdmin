<?php

namespace App\Http\Resources\Order;

use App\Constants\OrderConstant;
use App\Constants\OrderNoteConstant;
use App\Http\Resources\ListResource;
use App\Models\Order;
use App\Models\OrderSupplier;
use App\Models\Supplier;
use App\Services\AccountingService;
use App\Services\NoteService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use App\Constants\PackageConstant;
use App\Constants\RoleConstant;

class ProductPaginateResource extends JsonResource
{
    public function toArray($request)
    {
        $user = auth()->user();
        return $this->resource->map(
            function ($item, $key) use ($user) {
                $order = $item->first()->order;
                $packagesOrder = $order->packagesOrder()->where('status', '!=', PackageConstant::STATUS_CANCEL)->get();
                $totalDiscountCostPacks = !empty($packagesOrder) ? $packagesOrder->sum('discount_cost') : 0;
                $exchangeRate = optional($order)->exchange_rate;
                $orderSupplier = $item->first()->supplier->orderSupplier ?? [];
                if (empty($orderSupplier)) {
                    $orderSupplier = $item->first()->order->orderSuppliers;
                }
                $totalWoodWorkingCost = !empty($orderSupplier) ? $orderSupplier->sum('woodworking_cost') : 0;
                $totalShockProofCost = !empty($orderSupplier) ? $orderSupplier->sum('shock_proof_cost') : 0;
                $totalChinaShippingCost = !empty($orderSupplier) ? $orderSupplier->sum('china_shipping_cost') : 0;
                $totalOrderFee = !empty($orderSupplier) ? $orderSupplier->sum('order_fee') : 0;
                $totalOrderCost = !empty($orderSupplier) ? $orderSupplier->sum('order_cost') : 0;
                $totalInspectionCost = !empty($orderSupplier) ? $orderSupplier->sum('inspection_cost') : 0;
                $advanced_inspection_cost = !empty($orderSupplier) ? $orderSupplier->sum('advanced_inspection_cost') : 0;
                $totalDiscountCost = !empty($orderSupplier) ? $orderSupplier->sum('discount_cost') : 0;
                $totalInternalShippingCost = !empty($orderSupplier) ? $orderSupplier->sum('international_shipping_cost') : 0;
                $orderCostReal = optional($order)->order_cost_real / optional($order)->exchange_rate;

                $accounting = [
                    'woodworking_cost' => $totalWoodWorkingCost,
                    'china_shipping_cost' => $totalChinaShippingCost,
                    'order_fee' => $totalOrderFee,
                    'order_cost' => $totalOrderCost,
                    'inspection_cost' => $totalInspectionCost + $advanced_inspection_cost,
                    'advanced_inspection_cost' => $advanced_inspection_cost,
                    'discount_cost' => $totalDiscountCost,
                    'international_shipping_cost' => $totalInternalShippingCost,
                    'discount_cost_pack' => $totalDiscountCostPacks,
                    'exchange_rate' => $exchangeRate,
                    'shock_proof_cost' => $totalShockProofCost
                ];

                $totalAmount = (
                        ($totalChinaShippingCost * $exchangeRate) + $totalOrderFee + $totalOrderCost +
                        $totalInspectionCost
                    ) - ($totalDiscountCost);

                $totalAmountOriginal = $totalAmount;
                $totalAmount = roundMoney(($totalAmount - $totalDiscountCostPacks) + $totalWoodWorkingCost + $totalInternalShippingCost);

                $supplier = $item->first()->supplier ?? [];
                if (empty($supplier)) {
                    $supplier = Supplier::query()->where('id', $item->first()->supplier_id)->first();
                }
                $orderNote = $item->first()->orderNote->where('supplier_id', $key);
                $public_note = '';
                $private_note = '';
                $order_provisional_date = '';
                $business_note = '';
                if ($orderNote->isNotEmpty()) {
                    foreach ($orderNote as $note) {
                        if ($note->is_public == 0) {
                            $private_note = $note->content;
                        } elseif ($note->is_public == 1) {
                            $public_note = $note->content;
                        } elseif ($note->is_public == OrderNoteConstant::INTERNAL_BUSINESS) {
                            $business_note = $note->content;
                        }
                        else {
                            $order_provisional_date = $note->order_provisional_date;
                        }
                    }
                }
                $suppliers = $this->getSuppliers($orderSupplier, $supplier);
                $isChinaShipping = false;
                if (!empty($suppliers['order_supplier'])) {
                    foreach ($suppliers['order_supplier'] as $supplier) {
                        if ($supplier['is_china_shipping'] == 1) {
                            $isChinaShipping = true;
                            break;
                        }
                    }
                }

                $orderCostReal = !empty($suppliers['order_supplier']) ? array_sum(array_column($suppliers['order_supplier'], 'order_cost_real')) : 0;
                $chinaShippingCostReal =  !empty($suppliers['order_supplier']) ? array_sum(array_column($suppliers['order_supplier'], 'china_shipping_cost_real')) : 0;
                $accounting['order_cost_real'] = $orderCostReal;
                $accounting['china_shipping_cost_real'] = $chinaShippingCostReal;
                $accounting['actual_payment'] = round($accounting['order_cost_real'] + $accounting['china_shipping_cost_real'], 2);
                $item = $item->sortBy('id');
                $offer_id = 0;
                $platform = '';
                foreach ($item as $i) {
                    $order_detail_link = $i->link ?? '';
                    if ($offer_id == 0 && $order_detail_link) {
                        $offer_id = getPlatformIdFromUrl($order_detail_link)
                            ?? get1688IdFromRouteParam($order_detail_link);
                    }
                    if ($platform == '' && $order_detail_link) {
                        $platform = getSourceFromProductLink($order_detail_link);
                    }
                }

                $chat_information = [
                    'seller_id' => $supplier->code ?? '',
                    'offer_id' => $offer_id,
                    'platform' => $platform
                ];
                $hasEdit = false;
                if(in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
                    $hasEdit = true;
                }
                if($order->status == OrderConstant::KEY_STATUS_ORDERED && ($user->hasRoleName(RoleConstant::ADMIN) || $user->hasRoleName(RoleConstant::TRUONG_BP_GDQT))) {
                    $hasEdit = true;
                }
                return [
                    'chat_information' => $chat_information,
                    'suppliers' => $suppliers,
                    'is_china_shipping' => $isChinaShipping,
                    'products' => new ListResource($item, OrderProductResource::class),
                    'accounting' => $accounting + ['total_amount' => $totalAmount, 'total_amount_original' => $totalAmountOriginal],
                    'note' => [
                        'public' => $public_note,
                        'private' => $private_note,
                        'business' => $business_note,
                        'order_provisional_date' => !empty($order_provisional_date) ? date('Y-m-d H:i:s', strtotime($order_provisional_date)) : '',
                        'count_order_history_note_private' => $item->first()->order_history_count ?? 0
                    ],
                    'has_edit_cost_real' => $hasEdit
                ];
            }
        )->values();
    }

    private function getSuppliers($orderSupplier, $supplier): array
    {
        if (!empty($orderSupplier)) {
            $orderSupplier = $orderSupplier->first();
        }

        $services = [
            'delivery_type' => [
                'value' => optional($orderSupplier)->delivery_type ?? '',
                'label' => OrderConstant::DELIVERIES_TEXT[optional($orderSupplier)->delivery_type] ?? null
            ],
            'services' => [
                'is_woodworking' => (bool)(optional($orderSupplier)->is_woodworking ?? null),
                'is_inspection' => (bool)(optional($orderSupplier)->is_inspection ?? null),
                'is_advanced_inspection' => (int)(optional($orderSupplier)->is_advanced_inspection ?? 1),
                'is_shock_proof' => (bool)(optional($orderSupplier)->is_shock_proof ?? null),
                'official_is_partner' => (bool)(optional($orderSupplier)->official_is_partner ?? null),
                'is_china_shipping' => (bool)(optional($orderSupplier)->is_china_shipping ?? 0),
            ],
            'thuongdo_tong_tien_thanh_toan' => $this->getCaclThuongDoTongTienThanhToan($orderSupplier),
        ];
        return array_merge(
            !empty($supplier) ? $supplier->toArray() : [],
            $services
        );
    }

    public function getCaclThuongDoTongTienThanhToan($orderSupplier)
    {
        $sumNew = 0;
        $data_return = 0;
        // order_cost +  order_fee + china_shipping_cost + internal_shipping_cost + inspection_cost
        // Với các đơn trước ngày 2024-10-07 thì mới xử lý
        if ($orderSupplier && $orderSupplier->order_id < 444724) {
            $orderModel = \App::make('App\Models\Order');
            $orderDB = $orderModel->where('id', $orderSupplier->order_id)->first();
            if (!is_null($orderDB) && $orderDB->created_at > '2024-08-01 00:00:00') {
                $sumNew =
                    $orderSupplier->order_cost +
                    $orderSupplier->order_fee +
                    $orderSupplier->china_shipping_cost * $orderDB->exchange_rate +
                    $orderSupplier->international_shipping_cost +
                    $orderSupplier->inspection_cost;
            }
            $data_return = $sumNew > $orderSupplier->thuongdo_tong_tien_thanh_toan ? $sumNew : $orderSupplier->thuongdo_tong_tien_thanh_toan;
        }

        return $data_return;
    }
}
