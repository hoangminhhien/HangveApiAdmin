<?php

namespace App\Http\Resources\Complain;

use App\Helpers\MediaHelper;
use App\Models\ComplainDetail;
use App\Models\OrderPackage;
use App\Models\OrderDetailPackage;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\OrderDetailImage;

class ComplainProductDetailResource extends JsonResource
{
    private $_complain;

    public function __construct($resource, $complain)
    {
        parent::__construct($resource);
        $this->_complain = $complain;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $detail = ComplainDetail::query()->where(
            ['complain_id' => $this->_complain, 'order_detail_id' => $this->id]
        )->first();
        $orderDetail = $this->orderDetail;

        $orderPackage = OrderPackage::query()->find(optional($this)->order_package_id);
        $orderDetailPackage = OrderDetailPackage::where('order_detail_id', $this->order_detail_id)->where('order_package_id', $this->order_package_id)->first();
        return [
            'id' => $orderDetail->id,
            'bill_code' => data_get($orderPackage, 'bill_code'),
            'code_po' => data_get($orderPackage, 'code_po'),
            'image' => $orderDetail->image,
            'image_v2' => str_contains($orderDetail->image, 'http') ? $orderDetail->image :  MediaHelper::getURL($orderDetail->image),
            'name' => $orderDetail->name,
            'unit_price_cny' => $orderDetail->unit_price_cny,
            'quantity' => data_get($orderDetailPackage, 'quantity', 0),
            'note' => $orderDetail->classification,
            'amount_cny' => $orderDetail->amount_cny,
            'complain_note' => optional($this)->note,
            'exchange_rate' => getExchangeRate($orderDetail->order_id),
            // 'images' => optional($this->images)->pluck('image')
            'images' => optional(
                OrderDetailImage::query()->where(
                    ['complain_id' => $this->complain_id, 'order_detail_id' => $orderDetail->id]
                )
            )->pluck('image')
        ];
    }
}
