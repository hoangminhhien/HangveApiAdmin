<?php

namespace App\Http\Resources\Package;

use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\WarehouseConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\ListResource;
use App\Http\Resources\Note\NoteResource;
use App\Http\Resources\OnlyIdNameResource;
use App\Http\Resources\ReportStatusResource;
use App\Models\Order;
use App\Models\Consignment;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\OrderPackageService;
use App\Services\ReportCustomerService;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\OrderPackageNote;
use Carbon\Carbon;

class PackageResource extends JsonResource
{
    public function toArray($request): array
    {
        $listStatusApproveUnitPrice = [];
        foreach($this->approveUnitPrices as $approveUnitPrice) {
            array_push($listStatusApproveUnitPrice, $approveUnitPrice->status);
        }
        $customer = $this->customer ?? null;

        $isWeight = $this->type_quantitative == 'volume' ? false : true;

        $user = new User();
        $checkRole = $user->checkUserHasPer('pack.change-type-order');

        list($notes, $noteOrders, $noteInternal) = $this->getNotes();
        $warehouseCode = optional($this->warehouse)->code;
        $warehouseColor = WarehouseConstant::STATUSES_COLOR[$warehouseCode] ?? null;
        $packageStatusTime = $this->statusTimes()->orderBy('id', 'DESC')->first();
        $packType = 'unconfirmed';
        if($this->order_type == Order::class) {
            $packType = 'order';
        }
        if($this->order_type == Consignment::class) {
            $packType = 'consignment';
        }
        $is_edit_package_type = true;
        if ($packType == 'consignment' && empty($this->order_code) && $checkRole) {
            $is_edit_package_type = false;
        }
        $ecommerce = data_get($this->orderR, 'ecommerce');
        $textEcommerce = OrderConstant::ECOMMERCE[$ecommerce] ?? null;

        return [
            'info' => [
                'id' => $this->id,
                'order_id' => $this->order_id,
                'is_edit_package_type' => $is_edit_package_type,
                'bill_code' => $this->bill_code,
                "unit_price" => $this->unit_price,
                'order_code' => $this->order_code,
                'is_order' => $this->order_type === Order::class,
                'transporter' => optional($this->transporterRelation)->name ?? $this->transporter,
                'warehouse_code' => $warehouseCode,
                'warehouse_color' => $warehouseColor,
                'china_shipping_cost' => $this->china_shipping_cost,
                'is_inspection' => (boolean)$this->is_inspection,
                'is_advanced_inspection' => $this->is_advanced_inspection,
                'is_insurance' => (boolean)$this->is_insurance,
                'is_woodworking' => (boolean)$this->is_woodworking,
                'is_shock_proof' => (boolean)$this->is_shock_proof,
                'is_make_up' => (boolean)$this->is_make_up,
                'official' => $this->official,
                'type_official' => $this->type_official,
                'is_delivery' => $this->is_delivery,
                'delivery_id' => $this->delivery_id,
                'wv' => [
                    'weight_real' => $this->weight_real,
                    'weight' => round($this->weight, 2) ?? 0,
                    'volume' => [
                        'width' => $this->width ?? 0,
                        'height' => $this->height ?? 0,
                        'length' => $this->length ?? 0,
                    ],
                    'volume_done' => round($this->volume ?: $this->thuongdo_volumn, 3),
                    'type' => $isWeight ? 'weight' : 'volume',
                    'result' => $isWeight ? round($this->weight, 2) : $this->volume,
                    'volume_text' => $this->length.' x '.$this->width.' x '.$this->height
                ],
                'type_quantitative' => $this->type_quantitative == null ? ($isWeight ? 'weight' : 'volume') : $this->type_quantitative,
                'code_warehouse' => $this->code_warehouse,
                'official_is_partner' => (bool) $this->official_is_partner
            ],
            'package_status_time' => $packageStatusTime ? Carbon::parse($packageStatusTime->updated_at)->format('d-m-Y H:i:s') : '',
            'customer_id' => $this->customer_id,
            'customer' => [
                'id' => optional($customer)->id,
                'name' => optional($customer)->name,
                'is_endow_ship' => optional($customer)->is_endow_ship ?? 0,
                'phone_number' => optional($customer)->phone_number,
                'source' => optional($customer)->source,
                'warehouse' => optional(optional(optional($customer)->warehouse)->province)->name,
                'province_id' => optional($this->warehouse)->province_id,
                'code' => optional($customer)->code,
                'level' => !empty($customer->order_completed_count) && $customer->order_completed_count < 5 ? 'KH mới' : '',
                'totalOrderCompleted' => $customer->order_completed_count ?? 0,
            ],
            'staffs' => [
                'order_id' => $this->staff_order_id,
                'group_id' => $this->staff_group_id
            ],
            'order' => [
                'code_po' => $this->code_po,
                'created_at' => TimeHelper::format($this->created_at),
                'package_number' => $this->package_number,
                'ecommerce' => $textEcommerce,
            ],
            'type' => $packType,
            'category' => [
                optional($this->categoryRelation)->name ?? $this->category,
            ],
            'status' => new ReportStatusResource(
                $this->status,
                PackageConstant::STATUSES,
                PackageConstant::STATUSES_COLOR
            ),
            'costs' => (new OrderPackageService())->getCost($this),
            'notes' => [
                'note' => [
                    'content' => $this->note,
                    'data' => new ListResource($notes, NoteResource::class),
                    'quantity' => count($notes),
                ],
                'order' => [
                    'content' => $this->note_ordered,
                    'data' => new ListResource($noteOrders, NoteResource::class),
                    'quantity' => count($noteOrders),
                ],
                'internal' => [
                    'content' => $this->note_internal,
                    'data' => new ListResource($noteInternal, NoteResource::class),
                    'quantity' => count($noteInternal),
                ]
            ],

            'cn_bag_real' => $this->cn_bag_real,
            'diff_count_pack' => $this->diff_count_pack,
            'diff_color' => $this->getColorDiffPack(),

            'share_fee_ship' => $this->share_fee_ship,

            'sync_note'  => $this->sync_note == 1 ? true : false,

            "reason_cant_make_delivery" => $msg = $this->getReasonCanNotMakeDelivery($this),
            'can_make_delivery' => is_null($msg),
            'is_waiting_cancel' => $this->is_waiting_cancel,
            'is_block_package' => $this->is_block_package,
            'organization_id' => $this->organization_id,
            'exchange_rate' => $this->exchange_rate,
            'exchange_rate_can_edit' => $this->exchange_rate == 0 ? true : false,
            'is_confirm' => $this->is_confirm,
            'list_status_approve_unit_price'  => array_unique($listStatusApproveUnitPrice),
        ];
    }

    public function getColorDiffPack()
    {
        if ($this->cn_bag_real > 0) {
            if ($this->cn_bag_real == $this->package_number) {
                return 'green';
            }
            if ($this->cn_bag_real != $this->package_number) {
                return 'red';
            }
        }
        // Mặc định
        return '#000';
    }

    private function getReasonCanNotMakeDelivery($that): ?string
    {
        $isNullDelivery = is_null($that->delivery_id);
        if (!$isNullDelivery) {
            return trans('package.can_not_make_delivery.has_delivery');
        }
        if ($that->status === PackageConstant::STATUS_CANCEL) {
            return trans('package.can_not_make_delivery.cancel');
        }
        if ($statusVN = ($that->status != PackageConstant::STATUS_WAREHOUSE_VN)) {
            return trans('package.can_not_make_delivery.have_not_been_to_VN');
        }
        if (!$that->is_delivery && $statusVN) {
            return trans('package.can_not_make_delivery.is_delivery');
        }
        return null;
    }

    public function getNotes()
    {
        $notes      = [];
        $noteOrders = [];
        $noteInternal = [];

        $notesR = $this->notes;
        if ($notesR->isNotEmpty()) {
            foreach ($notesR as $item) {
                if ($item->type == PackageConstant::TYPE_NOTE_NOTE) {
                    $notes[] = $item;
                }
                if ($item->type == PackageConstant::TYPE_NOTE_ORDER) {
                    $noteOrders[] = $item;
                }
                if ($item->type == PackageConstant::TYPE_NOTE_INTERNAL) {
                    $noteInternal[] = $item;
                }
            }
        }

        return [$notes, $noteOrders, $noteInternal];
    }
}
