<?php

namespace App\Http\Requests\Carrier;

use App\Constants\CarrierConstant;
use App\Constants\DeliveryConstant;
use Illuminate\Foundation\Http\FormRequest;

class CarrierCreateOrderRequest extends FormRequest
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
        return [
            "payment"       => 'required|in:' . implode(',', DeliveryConstant::PAYMENTS),
            "receiver"      => 'required',
            "sizeLength"    => 'integer|min:0',
            "sizeWidth"     => 'integer|min:0',
            "sizeHeight"    => 'integer|min:0',
            "convertWeight" => 'required|numeric|between:0,99.99|min:0',
            "customer.id"   => 'required|exists:customers,id',
            "deliveryId"    => 'required|exists:deliveries,id',
            "packages.data" => 'required',
        ];
    }

    public function attributes(): array
    {
        return [
            "payment"       => "Phương thức thanh toán",
            "receiver"      => "Người nhận",
            "sizeLength"    => "Chiều dài",
            "sizeWidth"     => "Chiều rộng",
            "sizeHeight"    => "Chiều cao",
            "convertWeight" => "Khối lượng quy đổi",
            "customer.id"   => "Người dùng",
            "deliveryId"    => "Mã giao hàng",
            "packages.data" => "Kiện hàng",
        ];
    }
}
