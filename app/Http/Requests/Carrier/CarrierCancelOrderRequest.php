<?php

namespace App\Http\Requests\Carrier;

use App\Constants\CarrierConstant;
use Illuminate\Foundation\Http\FormRequest;

class CarrierCancelOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $arrCarrier = [CarrierConstant::GHN, CarrierConstant::GHTK, CarrierConstant::VIETTEL];
        return [
            "delivery_id"        => "required",
            "carrier_short_name" => "required|in:" . implode(',', $arrCarrier),
            // "carrier_order_id"   => "required",
            "shop_id"            => "",
        ];
    }

    public function attributes()
    {
        return [
            "delivery_id"        => "Mã đơn giao hàng",
            "carrier_short_name" => "Tên hãng vận chuyển",
            "carrier_order_id"   => "Mã đơn hàng bên vận chuyển",
            "shop_id"            => "Mã cửa hàng",
        ];
    }
}
