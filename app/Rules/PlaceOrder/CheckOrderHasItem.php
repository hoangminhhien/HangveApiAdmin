<?php

namespace App\Rules\PlaceOrder;

use App\Constants\OrderConstant;
use App\Models\OrderDetail;
use Illuminate\Contracts\Validation\Rule;

class CheckOrderHasItem implements Rule
{
    /**
     * Mảng chứa tên cc thông tin đơn hàng thiếu khi đặt hàng
     *
     * @var array
     */
    private array $missingPlaceOrderData = [];

    /**
     * Tên sản phẩm của đơn hàng
     *
     * @var string
     */
    private string $itemName;

    /**
     * Đảm bảo đơn đặt hàng đã có đầy đủ thông tin
     *   + Nguồn đặt hàng: order_details.source
     *   + Mã sp: order_details.mp_id
     *   + Mã phân loại sp: order_details.mp_sku_id
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Định nghĩa logic validate thành công
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $orderDetail = OrderDetail::query()->find($value);
        $sku_properties = json_decode(@$orderDetail->item->sku_properties ?? '');
        $properties = $sku_properties->properties ?? [];
        $this->itemName = $orderDetail->name;
        is_null($orderDetail->source) && $this->missingPlaceOrderData[] = 'Nguồn đặt hàng';
        is_null($orderDetail->mp_id) && $this->missingPlaceOrderData[] = 'Mã sản phẩm';
        if (!empty($properties)) {
            if (!empty($properties[0]->prop_name)) {
                is_null($orderDetail->mp_sku_id) && $this->missingPlaceOrderData[] = 'Mã phân loại sản phẩm';
            }
        }
        return ! count($this->missingPlaceOrderData);
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return "Đơn hàng của sản phẩm {$this->itemName} thiếu các thông tin: "
            . implode(', ', $this->missingPlaceOrderData) . '.';
    }
}
