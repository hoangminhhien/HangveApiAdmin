<?php

namespace App\Http\Resources\Order;

use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Helpers\MediaHelper;
use App\Http\Resources\ReportStatusResource;
use App\Models\OrderDetail;
use App\Services\AccountingService;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Constants\TimeConstant;
use Carbon\Carbon;

class OrderListRefundResource extends JsonResource
{
    public function toArray($request)
    {
        $details = $this->getDetail($this->order);
        $imageRefunds = $this->getImage($this->images_create_refund);
        $imageCancels = $this->getImage($this->images_create_cancel);
        $typeRefundText = '';
        if ($this->type_refund == OrderConstant::IS_CREATE_REFUND) {
            $typeRefundText = 'Sửa đơn';
        }
        if ($this->type_refund == OrderConstant::IS_CREATE_CANCEL) {
            $typeRefundText = 'Hủy đơn';
        }
        $moneyCn = 0;
        $money = 0;
        if($this->order) {
            $money = $this->order->getTotalAmountNotShip();
            $moneyCn = $money / $this->order->exchange_rate;
        }
        $statusText = '';
        if($this->status == 0) {
            $statusText = 'Chờ xử lý';
        }
        if($this->status == 1) {
            $statusText = 'Đã xác nhận';
        }
        if($this->status == 2) {
            $statusText = 'Đã hủy';
        }
        $orderSupplier = $this->order ? $this->order->orderSuppliers->first() : '';
        $orderCostRealOld = $orderSupplier ? (float)$orderSupplier->order_cost_real : 0;
        $chinaShippingCostRealOld = $orderSupplier ? (float)$orderSupplier->china_shipping_cost_real : 0;
        return [
            'id' => $this->id,
            'code' => $this->order->code ?? '',
            'money_refund_from_xadal' => $this->money_refund_from_xadal ?? 0,
            'order_code_from_xadal' => $this->order_code_from_xadal ?? '',
            'order_id' => $this->order->id ?? '',
            'code_parent' => $this->order->code_parent ?? '',
            'type_refund' => $this->type_refund,
            'type_refund_text' => $typeRefundText,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->format('d-m-Y') : '',
            'customer_id' => $this->order->customer->id ?? '',
            'customer_name' => $this->order->customer->name ?? '',
            'customer_phone' => $this->order->customer->phone_number ?? '',
            'status' => $this->status,
            'status_order' => new ReportStatusResource(
                $this->order->status ?? '',
                OrderConstant::STATUSES,
                OrderConstant::STATUSES_COLOR
            ),
            'packages' => $this->getPackage($this->order),
            'status_text' => $statusText,
            'status_refund' => $this->status,
            'packages_number' => $this->order ? $this->order->packagesOrder->count() : 0,
            'china_shipping_cost' => $this->order->china_shipping_cost ?? 0,
            'china_shipping_cost_refund' => $this->china_shipping_cost_refund,
            'reason_create_refund' => $this->reason_create_refund,
            'reason_cancel' => $this->reason_cancel,
            'reason_create_cancel' => $this->reason_create_cancel,
            'images_create_refund' => $imageRefunds,
            'images_create_cancel' => $imageCancels,
            'money_refund' => $this->money_refund,
            'money_refund_cn' => round($this->money_cny_refund, 2),
            'money_refund_cn_origin' => $this->money_refund_cn_origin,
            'money' => $money,
            'money_cn' => round($moneyCn, 2),
            'staff_name' => $this->user->name ?? '',
            'order_detail' => $details,
            'is_has_change_china_shipping_cost' => $this->order
                && (float)$this->order->china_shipping_cost != (float)$this->china_shipping_cost_refund,
            'order_cost_real_old' => $orderCostRealOld,
            'is_has_change_order_cost_real' => $this->order
                && (float)$orderSupplier->order_cost_real != (float)$this->order_cost_real,
            'order_cost_real' => $this->order_cost_real,
            'china_shipping_cost_real_old' => $chinaShippingCostRealOld,
            'is_has_change_china_shipping_cost_real' => $this->order
                && (float)$orderSupplier->china_shipping_cost_real != (float)$this->china_shipping_cost_real,
            'china_shipping_cost_real' => $this->china_shipping_cost_real,
            'total_payment_old' => $orderCostRealOld + $chinaShippingCostRealOld,
            'total_payment' => (float) $this->china_shipping_cost_real + (float) $this->order_cost_real ?? 0,
            'inspectioned' => $this->inspectioned
        ];
    }

    public function getDetail($order)
    {
        if (empty($order)) {
            return [];
        }
        if($this->type_refund == OrderConstant::IS_CREATE_CANCEL) {
            return [];
        }
        $details = $order->details;
        $data = [];
        $products = collect(json_decode($this->price_quantity_order_details, true));
        foreach ($details as $detail) {
            $productCheck = $products->filter(function ($product) use ($detail) {
                return $product['id'] == $detail->id;
            })->first();
            if(empty($productCheck)) {
                continue;
            }
            $productCheck = (object) $productCheck;
            $isHasQuantity = false;
            if ((int)$detail->quantity != $productCheck->quantity) {
                $isHasQuantity = true;
            }
            $isHasUnitPrice = false;
            if ((int)$detail->unit_price_cny != (int)$productCheck->unit_price_cny) {
                $isHasUnitPrice = true;
            }
            $data[] = [
                'id' => $detail->id,
                'link' => $detail->link,
                'classification' => $detail->classification,
                'image' => str_contains($detail->image, 'http') ? $detail->image :  MediaHelper::getURL($detail->image),
                'name' => $detail->name,
                'is_has_change_quantity' => $isHasQuantity,
                'is_has_change_unit_price' => $isHasUnitPrice,
                'quantity' => $detail->quantity,
                'quantity_refund' => $productCheck->quantity,
                'unit_price_cny' => $detail->unit_price_cny,
                'unit_price_cny_refund' => $productCheck->unit_price_cny,
            ];
        }

        return $data;
    }

    public function getImage($images)
    {
        if (empty($images)) {
            return [];
        }
        $images = json_decode($images, true);
        $arrayImages = [];
        foreach ($images as $image) {
            $arrayImages[] = MediaHelper::getUrl($image);
        }

        return $arrayImages;
    }
    public function getPackage($order)
    {
        if (empty($order)) {
            return [];
        }
        $orderPackages = $order->packagesOrder;
        if (empty($orderPackages)) {
            return [];
        }
        $data = [];
        foreach ($orderPackages as $package) {
            $data[] = [
                'id' => $package->id,
                'bill_code' => $package->bill_code,
                'code_warehouse' => $package->code_warehouse,
                'status' => PackageConstant::STATUSES[$package->status],
            ];
        }

        return $data;
    }
}
