<?php

namespace App\Http\Requests\PlaceOrder;

use App\Constants\TimeConstant;
use App\Models\OrderDetail;
use App\Rules\EnsureSameness;
use App\Rules\PlaceOrder\CheckOrderDeposited;
use App\Rules\PlaceOrder\CheckOrderHasItem;
use App\Rules\PlaceOrder\CheckPlaceOrderQuantity;
use App\Rules\PlaceOrder\EnsureCorrectOrderDetailSource;
use App\Rules\PlaceOrder\EnsureOrderBelongsToAuthUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PlaceOrderRequest extends FormRequest
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

        return $user->can('place_order_place_order');
    }

    /**
     * Chuẩn bị dữ liệu trước validation
     *
     * @return void
     */
    public function prepareForValidation(): void
    {
        // set thời gian delay khi dispatch job cập nhật mã vận đơn
        $delayTime = $this->offsetGet('delay_time') ?? TimeConstant::UPDATE_LOGISTIC_CODE_PERIOD;
        $this->offsetSet('delay_time', (int) $delayTime);
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'delay_time' => ['required', 'int', 'min:60'],
            'source' => [
                'bail',
                'required',
                'string',
                Rule::in(BuyderServiceConstant::PLACE_ORDER_PAGES)
            ],

            'data' => ['required', 'array'],
            'data.*.order_detail_id' => [
                'bail', 'required', 'distinct',
                Rule::exists(OrderDetail::class, 'id'),
                new CheckOrderDeposited(),
                new CheckOrderHasItem()
            ],
            'data.*.order_detail_source' => [
                'bail', 'required', 'string',
                Rule::in(BuyderServiceConstant::AVAILABLE_SOURCES),
                new EnsureSameness( // đảm bảo không được đặt hàng cùng lúc các order_detail từ nhiều nguồn
                    $this->get('data'),
                    'order_detail_source',
                    'Không được đặt hàng cùng lúc từ nhiều nguồn'
                ),
                new EnsureCorrectOrderDetailSource( // đảm bảo gửi lên đúng source của order_detail
                    Arr::pluck($this->get('data'), 'order_detail_id')
                )
            ],
            'data.*.assign_support_id' => [
                'bail', 'sometimes',
                Rule::requiredIf($this->get('source') == 'place_order_assign_support'),
                'string',
            ],
            'data.*.quantity' => [
                'bail', 'required', 'numeric', 'min:1',
                new CheckPlaceOrderQuantity(
                    Arr::pluck($this->get('data'), 'order_detail_id'),
                    Arr::pluck($this->get('data'), 'assign_support_id'),
                    Arr::pluck($this->get('data'), 'quantity'),
                    $this->get('source')
                )
            ],

            'receiver' => ['required', 'array'],
            'receiver.*' => ['required'],
        ];

        return $rules;
    }

    /**
     * Định nghĩa các validation messages khi validation fails
     *
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'source.required' => 'Source request là bắt buộc',
            'source.string' => 'Source request phải là dạng chuỗi',
            'source.in' => 'Source request không được hỗ trợ',

            'data.required' => 'Dữ liệu đặt hàng là bắt buộc',
            'data.array' => 'Dữ liệu đặt hàng phải là kiểu mảng',

            'data.*.order_detail_id.required' => 'Order detail ID là bắt buộc',
            /*'data.*.order_detail_id.string' => 'Order detail ID phải là kiểu chuỗi',*/
            'data.*.order_detail_id.distinct' => 'Order detail ID không được trùng nhau',
            'data.*.order_detail_id.exists' => 'Order detail không tồn tại',

            'data.*.order_detail_source.required' => 'Nguồn đặt hàng là bắt buộc',
            'data.*.order_detail_source.string' => 'Nguồn đặt hàng phải là dạng chuỗi',
            'data.*.order_detail_source.in' => 'Nguồn đặt hàng không tồn tại',

            'data.*.quantity.required' => 'Số lượng sản phẩm là bắt buộc',
            'data.*.quantity.numeric' => 'Số lượng sản phẩm phải là số hoặc chuỗi số',
            'data.*.quantity.min' => 'Số lượng sản phẩm phải lớn hơn 0',

            'receiver.required' => 'Thông tin nhận hàng là bắt buộc',
            'receiver.array' => 'Thông tin nhận hàng phải là dạng mảng',

            'receiver.zip.required' => 'Mã bưu điện (zip) là bắt buộc',
            'receiver.zip.string' => 'Mã bưu điện (zip) phải là dạng chuỗi',
            'receiver.name.required' => 'Tên người nhận (name) là bắt buộc',
            'receiver.name.string' => 'Tên người nhận (name) phải là dạng chuỗi',

            'receiver.phone.required' => 'Số điện thoại (mobile) là bắt buộc',
            'receiver.phone.string' => 'Số điện thoại (mobile) phải là dạng chuỗi',
            'receiver.mobile_phone.required' => 'Số di động (mobile_phone) là bắt buộc',
            'receiver.mobile_phone.string' => 'Số di động (mobile_phone) phải là dạng chuỗi',

            'receiver.country.required' => 'Quốc gia (country) là bắt buộc',
            'receiver.country.string' => 'Quốc gia (country) phải là dạng chuỗi',
            'receiver.state.required' => 'Bang (state) là bắt buộc',
            'receiver.state.string' => 'Bang (state) phải là dạng chuỗi',
            'receiver.city.required' => 'Thành phố (city) là bắt buộc',
            'receiver.city.string' => 'Thành phố (city) phải là dạng chuỗi',
            'receiver.district.required' => 'Quận (district) là bắt buộc',
            'receiver.district.string' => 'Quận (district) phải là dạng chuỗi',
            'receiver.address.required' => 'Địa chỉ (address) là bắt buộc',
            'receiver.address.string' => 'Địa chỉ (address) phải là dạng chuỗi',
        ];
    }
}
