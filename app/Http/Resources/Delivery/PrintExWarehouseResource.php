<?php

namespace App\Http\Resources\Delivery;

use App\Helpers\AccountingHelper;
use App\Helpers\ConvertHelper;
use App\Http\Resources\ListResource;
use App\Models\DeliveryOrder;
use App\Services\OrderPackageService;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintExWarehouseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $orders = DeliveryOrder::query()->where('delivery_id', $this->id)->groupBy('order_id')->get();
        $totalPurchaseOrder = 0;
        $totalAmountOrder = 0;

        $ordersData = [];
        $warehouseCode = '';
        foreach ($orders as $item) {
            $order = $item->order;
            $packageLatestByOrder = (new OrderPackageService())->checkPackageIsLatestByOrder($order->id, $this->orderPackages->pluck('id')->all());
            if($order->getTable() == 'consignments') {
                $totalAmount = 0;
                $depositCost = 0;
            }else {
                $totalAmount = $order->thuongdo_id ? $order->thuongdo_tong_tien_don_hang : $order->getTotalAmountNotShip();
                $depositCost = $order->thuongdo_id ? $order->thuongdo_tong_tien_tam_ung : $order->deposit_cost;
            }
            $ordersData[] = $tmp = [
                'code' => $order->code,
                'total_amount' => $totalAmount,
                'deposit_cost' => $depositCost,
                'balance' => $balance = $packageLatestByOrder ? ($totalAmount - $depositCost) : 0
            ];

            $totalPurchaseOrder += AccountingHelper::getCosts($balance);
            $totalAmountOrder += AccountingHelper::getCosts($balance);
        }
        $totalAmountPackage = 0;
        foreach ($this->orderPackages as $orderPackage)
        {
            $warehouseCode = $orderPackage->warehouse->code ?? '';
            $totalAmountPackage += AccountingHelper::getCosts($orderPackage->amount);
        }
        return [
            'print_receipt_code' => getPrintReceiptCode($warehouseCode),
            'info' => [
                'time' => "Ngày ".date('d')." tháng ".date('m')." năm ".date('Y'),
                'staff' => getCurrentUser()->name ?? ''
            ],
            'receiver' => [
                'name' => $this->customer->name ?? '',
                'address' => $this->customer->address ?? '',
                'phone_number' => $this->customer->phone_number ?? ''
            ],
            'packages' => [
                'data' => new ListResource($this->orderPackages, PrintPackageResource::class),
                'total_amount' => $totalAmountPackage
            ],
            'orders' => [
                'data' => new ListResource($ordersData, PrintOrderResource::class),
                'total_amount' => $totalAmountOrder,
                'total_purchase' => $total = $totalPurchaseOrder + $totalAmountPackage,
                'str_cost' => convert_number_to_words($total) . ' đồng'
            ],
            'transporter' => optional($this->transporter)->name,
            'organization' => optional($this->organization)->only('name', 'avatar', 'address', 'tax_code', 'phone_number'),
            'delivery_phone' => $this->shipper_phone_number
        ];
    }
}
