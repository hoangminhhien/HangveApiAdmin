<?php

namespace App\Http\Requests\PlaceOrder;

use App\Constants\Buyder\BuyderCancelReasonConstant;
use App\Models\OrderBuyder;
use App\Models\SubOrderBuyder;
use App\Rules\EnsureSameness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class CancelOrderRequest extends FormRequest
{
    /**
     * Auth user có được authorized không
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$user = $this->user()) {
            return false;
        }

        return $user->can('place_order_place_order_cancel');
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'cancel_type' => [
                'required',
                'numeric',
                Rule::in(array_keys(BuyderCancelReasonConstant::CANCEL_REASON_LIST))
            ],
            'data' => ['required', 'array'],
            'data.*.platform_source' => [
                'required',
                'string',
                Rule::in(BuyderServiceConstant::AVAILABLE_SOURCES),
                new EnsureSameness(
                    $this->get('data'),
                    'platform_source',
                    'Không được hủy cùng lúc các đơn khác nguồn đặt hàng'
                )
            ],
            'data.*.buyder_id' => [
                'required',
                'string',
/*                Rule::exists(OrderBuyder::class, 'buyder_id')*/
            ],
            'data.*.buyder_sub_ids' => ['required', 'array'],
            'data.*.buyder_sub_ids.*' => [
                'required',
                'string',
                /*Rule::exists(SubOrderBuyder::class, 'buyder_sub_id')*/
            ],
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
            'cancel_type.required' => 'Lý do hủy đơn là bắt buộc',
            'cancel_type.numeric' => 'Lý do hủy đơn phải là số hoặc chuỗi số',
            'cancel_type.in' => 'Lý do hủy đơn không tồn tại',

            'data.required' => 'Thông tin hủy đơn là bắt buộc',
            'data.array' => 'Thông tin hủy đơn phải là dạng mảng',

            'data.*.platform_source.required' => 'Nền tảng đặt hàng là bắt buộc',
            'data.*.platform_source.string' => 'Nền tảng đặt hàng phải là dạng chuỗi',
            'data.*.platform_source.in' => 'Nền tảng đặt hàng không tồn tại',

            'data.*.buyder_id.required' => 'ID đặt hàng là bắt buộc',
            'data.*.buyder_id.string' => 'ID đặt hàng phải là dạng chuỗi',
            'data.*.buyder_id.exists' => 'ID đặt hàng không tồn tại',

            'data.*.buyder_sub_ids.required' => 'Danh sách ID đặt hàng con là bắt buộc',
            'data.*.buyder_sub_ids.array' => 'Danh sách ID đặt hàng con phải là dạng mảng',
            'data.*.buyder_sub_ids.*.required' => 'ID đặt hàng con là bắt buộc',
            'data.*.buyder_sub_ids.*.string' => 'ID đặt hàng con phải là dạng chuỗi',
            'data.*.buyder_sub_ids.*.exists' => 'ID đặt hàng con không tồn tại',
        ];
    }
}
