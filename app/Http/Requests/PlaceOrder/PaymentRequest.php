<?php

namespace App\Http\Requests\PlaceOrder;

use App\Models\OrderBuyder;
use App\Models\User;
use App\Rules\PlaceOrder\IsOrderBuyderPaid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PaymentRequest extends FormRequest
{
    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'platform_source' => ['required', 'string', Rule::in(BuyderServiceConstant::AVAILABLE_SOURCES)],
            'buyder_ids' => ['required', 'array'],
            'buyder_ids.*' => [
                'required',
                'numeric',
/*                'distinct',*/
                Rule::exists(OrderBuyder::class, 'buyder_id')
                    ->where('source', $this->get('platform_source')),
                new IsOrderBuyderPaid()
            ]
        ];
    }

    /**
     * Định nghĩa các validation messages khi validation fails
     *
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'platform_source.required' => 'Nguồn đặt hàng là bắt buộc',
            'platform_source.string' => 'Nguồn đặt hàng phải là dạng chuỗi',
            'platform_source.in' => 'Nguồn đặt hàng không tồn tại',

            'buyder_ids.required' => 'Danh sách ID đặt hàng là bắt buộc',
            'buyder_ids.array' => 'Danh sách ID đặt hàng phải là dạng mảng',

            'buyder_ids.*.required' => 'ID đặt hàng là bắt buộc',
            'buyder_ids.*.numeric' => 'ID đặt hàng phải là số hoặc chuỗi số',
            'buyder_ids.*.distinct' => 'ID đặt hàng không được trùng nhau',
            'buyder_ids.*.exists' => 'Không được thanh toán cùng lúc các đơn hàng khác nguồn',
        ];
    }
}
