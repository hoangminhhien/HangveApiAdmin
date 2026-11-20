<?php

namespace App\Http\Resources\Order;

use App\Constants\BuyderServiceConstant;
use App\Factories\BasePlatformFactory;
use App\Models\Category;
use App\Models\Customer;
use App\Models\OrderDetail;
use App\Models\Staff;
use App\Services\PlatformServices\BasePlatformService;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Constants\PackageConstant;
use Illuminate\Support\Facades\Log;

class OrderProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $explodeClassification = explode(';',$this->classification);
        $notesByAdmin = $this->notes->isNotEmpty() ? $this->notes->where('subject_type', Staff::class) : '';
        $lastNote = $this->notes->isNotEmpty() ? $this->notes->first() : '';
        $lastNoteByAdmin = $notesByAdmin ? $notesByAdmin->first() : '';
        $explodeClassificationZh =
            (isset($this->classification_zh) && trim($this->classification_zh) !== ''
                && isset($this->mp_sku_id) && trim($this->mp_sku_id) !== '')
                ? explode(';', $this->classification_zh)
                : explode(';', $this->classification);
        return [
            'id' => $this->id,
            'order_detail_id' => $this->id,
            'bill_code' => optional($this->orderPackage)->bill_code,
            // 'image1' => $this->image,
            'image' => $this->getImage(), // Logic mới dùng cái này
            'name' => $this->name,
            'unit_price_cny' => $this->unit_price_cny,
            'classification_from_image' => $this->classification_from_image ?? '',
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'is_edit_note_classification' => $this->order_detail_note_customer_count ?? 0,
            'amount_cny' => $this->amount_cny,
            'amount' => $this->amount,
            'note_number' => $this->note_number,
            'note_by_admin' => $notesByAdmin ? $notesByAdmin->count() : 0,
            'link' => $this->link,
            'note' => $this->note,
            'classification' => $this->classification,
            'source' => $this->source,
            'mp_id' => $this->mp_id,
            'mp_sku_id' => $this->mp_sku_id,
            'mp_sku_id_fast' => $this->mp_sku_id_fast,
            'classification_all' => !empty($explodeClassificationZh)
                ? $explodeClassificationZh
                : $explodeClassification,
            'classification_1' => !empty($explodeClassificationZh)
                ? data_get($explodeClassificationZh, '0')
                : data_get($explodeClassification, '0'),
            'classification_2' => !empty($explodeClassificationZh)
                ? data_get($explodeClassificationZh, '1')
                : data_get($explodeClassification, '1'),
            'exchange_rate' => getExchangeRate($this->order_id),
            'category' => [
                'id' => $this->category_id,
                'name' => optional(Category::query()->find($this->category_id))->name,
            ],
            'total_quantity_package' => $this->getTotalQuantityPackage(),
            'text_note_last' => $this->note_staff_latest ?? data_get($lastNoteByAdmin, 'content'),
            'text_note_priority' => $lastNoteByAdmin ? data_get($lastNoteByAdmin, 'priority') : "",
            'is_customer_note' => ($lastNote && $lastNote->subject_type == Customer::class) ? 1 : 0,
            'unit_price_old' => isset($this->orderDetailHistoriyByPrice)
                ? data_get($this->orderDetailHistoriyByPrice, '0.value_old') : 0,
            'quantity_old' => isset($this->orderDetailHistoriyByQuantity)
                ? data_get($this->orderDetailHistoriyByQuantity, '0.value_old') : 0,
            'classification_new' => isset($this->orderDetailHistoryByClassification)
                ? data_get($this->orderDetailHistoryByClassification, '0.value') : 0,
            'note_china' => $this->note_china,
            // Nếu thêm ở đây thì phải check thêm vào removeAttributeProduct ở OrderService
        ];
    }

    // Sau nâng cấp lưu biến phụ vào bảng orderDetailPackage để lấy cho nhanh
    public function getTotalQuantityPackage()
    {
        $orderPackDetailDB = $this->orderPackageDetail;
        $quantityResult = 0;
        if (count($orderPackDetailDB) > 0) {

            $pack_ids = \Arr::pluck($orderPackDetailDB, 'order_package_id');
            $packageModel = \App::make('App\Models\OrderPackage');
            $packCheckDB = $packageModel->whereIn('id', $pack_ids)
                                    ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                                    ->get();

            $pack_id_oks = [];
            if (count($packCheckDB) > 0) {
                $pack_id_oks = \Arr::pluck($packCheckDB, 'id');
            }

            foreach ($orderPackDetailDB as $key => $item) {
                if (in_array($item->order_package_id, $pack_id_oks)) {
                    $quantityResult += $item->quantity;
                }
            }
        }
        return $quantityResult;
    }
}
